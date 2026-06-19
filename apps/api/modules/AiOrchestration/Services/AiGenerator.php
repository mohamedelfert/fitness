<?php

namespace Modules\AiOrchestration\Services;

use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use Modules\AiOrchestration\Contracts\LlmGateway;
use Modules\AiOrchestration\Contracts\SafetyScanner;
use Modules\AiOrchestration\Models\AiInteraction;
use Modules\AiOrchestration\Support\LlmRequest;
use Modules\AiOrchestration\Support\LlmResult;
use Modules\Identity\Models\Person;

/**
 * The shared AI generation engine (FR-AI-001/002/003) — the safety sandwich expressed once
 * (FR-AI-007 / INV-005):
 *
 *   build RAG request → generate → parse → resolve to real library entities →
 *   safety post-eval → reject + regenerate on fail → finalize only when clean.
 *
 * Centralising the reject-and-regenerate loop matters: it is the INV-005 boundary, and one
 * well-tested copy beats three that can drift. Every attempt — passed, rejected, or
 * unevaluable — is logged to ai_interactions OUTSIDE finalize so the audit trail survives a
 * 422. Subclasses supply the domain specifics (build/parse/resolve/finalize) and inject their
 * SafetyScanner; `finalize` deliberately returns `mixed` so a generator may persist a graph
 * (program/meal-plan) OR return un-persisted proposals (exercise alternatives).
 *
 * Per-call inputs beyond the Person (e.g. the exercise being swapped) flow through `$context`
 * rather than mutable instance state, so the engine stays reentrant.
 */
abstract class AiGenerator
{
    public function __construct(
        protected readonly LlmGateway $gateway,
        protected readonly SafetyScanner $scanner,
    ) {}

    /**
     * Drive the safety sandwich and return whatever finalize() produces.
     *
     * @param  array<string, mixed>  $context  per-call inputs the hooks need
     */
    protected function runLoop(Person $person, array $context = []): mixed
    {
        $feature = $this->feature();
        $tier = (string) config("ai.{$feature}.tier", 'strong');
        $maxAttempts = (int) config("ai.{$feature}.max_attempts", 2);

        $avoid = [];

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $request = $this->buildRequest($person, $context, $avoid, $tier);

            $startedAt = microtime(true);
            $result = $this->gateway->complete($request);
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

            $parsed = $this->parse($result->text);
            if ($parsed === null) {
                $this->log($person, $result, 'error', $latencyMs, $tier);

                continue; // malformed output — never a 500; retry then give up
            }

            $resolved = $this->resolve($parsed, $context);
            if ($resolved === null) {
                $this->log($person, $result, 'error', $latencyMs, $tier);

                continue; // hallucinated slug — retry then give up
            }

            $unsafe = $this->scanner->unsafeSlugs($person, $resolved->values());
            if ($unsafe !== []) {
                $this->log($person, $result, 'rejected', $latencyMs, $tier);
                $avoid = array_values(array_unique([...$avoid, ...$unsafe]));

                continue; // unsafe — regenerate avoiding these slugs
            }

            $this->log($person, $result, 'passed', $latencyMs, $tier);

            return $this->finalize($person, $parsed, $resolved, $context);
        }

        // Exhausted attempts without a safe, valid output. INV-005: nothing persisted.
        throw ValidationException::withMessages([$feature => $this->exhaustedMessage()]);
    }

    /** Feature key — drives config (`ai.<feature>.*`), the ai_interactions row, and the 422 key. */
    abstract protected function feature(): string;

    /** User-facing message when the safety loop is exhausted. */
    abstract protected function exhaustedMessage(): string;

    /**
     * @param  array<string, mixed>  $context
     * @param  list<string>  $avoid  slugs the previous attempt was rejected for
     */
    abstract protected function buildRequest(Person $person, array $context, array $avoid, string $tier): LlmRequest;

    /** Decode model output to a usable array, or null if it isn't the expected shape. */
    abstract protected function parse(string $text): ?array;

    /**
     * Resolve prescribed slugs to real library entities (keyed by slug), or null if the model
     * hallucinated / produced nothing usable (caller treats it as a failed attempt).
     *
     * @param  array<string, mixed>  $context
     * @return Collection<string, object>|null
     */
    abstract protected function resolve(array $parsed, array $context): ?Collection;

    /**
     * Turn a validated, safe result into the response value — persist a graph and return a
     * model, or return un-persisted proposals. Caller wraps a transaction if it persists.
     *
     * @param  Collection<string, object>  $resolved
     * @param  array<string, mixed>  $context
     */
    abstract protected function finalize(Person $person, array $parsed, Collection $resolved, array $context): mixed;

    private function log(Person $person, LlmResult $result, string $verdict, int $latencyMs, string $tier): void
    {
        AiInteraction::create([
            'person_id' => $person->id,
            'feature' => $this->feature(),
            'model' => $result->model,
            'tier' => $tier,
            'tokens_in' => $result->tokensIn,
            'tokens_out' => $result->tokensOut,
            'cost_micros' => $this->costMicros($result),
            'latency_ms' => $latencyMs,
            'safety_verdict' => $verdict,
        ]);
    }

    /** Cost in integer micro-USD (INV-006). Unknown/stub models price at 0 until Q5. */
    private function costMicros(LlmResult $result): int
    {
        $rates = config('ai.pricing.'.$result->model, config('ai.pricing.default'));

        return (int) round(
            $result->tokensIn / 1000 * ($rates['in'] ?? 0)
            + $result->tokensOut / 1000 * ($rates['out'] ?? 0)
        );
    }
}
