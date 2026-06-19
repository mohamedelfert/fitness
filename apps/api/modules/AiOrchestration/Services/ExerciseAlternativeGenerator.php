<?php

namespace Modules\AiOrchestration\Services;

use Illuminate\Support\Collection;
use Modules\AiOrchestration\Contracts\LlmGateway;
use Modules\AiOrchestration\Support\ContraindicationScanner;
use Modules\AiOrchestration\Support\LlmRequest;
use Modules\Identity\Models\Person;
use Modules\Identity\Support\AiInputProfile;
use Modules\Training\Models\Exercise;

/**
 * AI exercise-alternatives (FR-AI-003). Runs the same safety sandwich as the plan generators
 * via AiGenerator — a contraindicated swap is an INV-005 hazard — reusing ContraindicationScanner
 * and a cheap model tier (config ai.exercise_alternatives.tier, the margin lever in ARCH §6).
 *
 * It is the odd shape: it takes extra per-call inputs (the exercise to replace + a count), which
 * flow through `$context` (never instance state), and it persists nothing — finalize() returns a
 * ranked list of suggestions the member applies to a program later.
 */
class ExerciseAlternativeGenerator extends AiGenerator
{
    private ?Collection $candidates = null;

    public function __construct(LlmGateway $gateway, ContraindicationScanner $scanner)
    {
        parent::__construct($gateway, $scanner);
    }

    /**
     * @return Collection<int, array{exercise: Exercise, rationale: ?string}> safe, ordered swaps
     */
    public function generate(Person $person, Exercise $source, int $count = 3): Collection
    {
        return $this->runLoop($person, ['source' => $source, 'count' => $count]);
    }

    protected function feature(): string
    {
        return 'exercise_alternatives';
    }

    protected function exhaustedMessage(): string
    {
        return 'Could not find safe alternatives for this exercise. Please try again.';
    }

    protected function parse(string $text): ?array
    {
        $decoded = json_decode($text, true);

        if (! is_array($decoded) || ! isset($decoded['alternatives']) || ! is_array($decoded['alternatives'])) {
            return null;
        }

        return $decoded;
    }

    /**
     * Resolve every suggested slug to a real Exercise, dropping the source itself. Returns null
     * if the model hallucinated a slug or suggested nothing usable (treated as a failed attempt).
     *
     * @param  array{source: Exercise, count: int}  $context
     * @return Collection<string, Exercise>|null keyed by slug
     */
    protected function resolve(array $parsed, array $context): ?Collection
    {
        $source = $context['source'];

        $slugs = collect($parsed['alternatives'])
            ->pluck('exercise_slug')
            ->filter()
            ->unique()
            ->reject(fn ($slug) => $slug === $source->slug)
            ->values();

        if ($slugs->isEmpty()) {
            return null;
        }

        $found = Exercise::whereIn('slug', $slugs)->get()->keyBy('slug');

        return $found->count() === $slugs->count() ? $found : null;
    }

    /**
     * Pair each suggestion (in model order) with its resolved Exercise + rationale. No persistence —
     * these are proposals the member applies to a program later.
     *
     * @param  Collection<string, Exercise>  $resolved
     * @param  array{source: Exercise, count: int}  $context
     * @return Collection<int, array{exercise: Exercise, rationale: ?string}>
     */
    protected function finalize(Person $person, array $parsed, Collection $resolved, array $context): Collection
    {
        return collect($parsed['alternatives'])
            ->filter(fn ($a) => isset($a['exercise_slug'], $resolved[$a['exercise_slug']]))
            ->map(fn ($a) => [
                'exercise' => $resolved[$a['exercise_slug']],
                'rationale' => isset($a['rationale']) ? (string) $a['rationale'] : null,
            ])
            ->values();
    }

    /**
     * Ground the model on the real library + the exercise being swapped and the athlete's
     * equipment/injuries, asking for similar-stimulus alternatives. Untested by the fake gateway —
     * kept minimal and real for the Claude adapter (Q5).
     *
     * @param  array{source: Exercise, count: int}  $context
     * @param  list<string>  $avoid
     */
    protected function buildRequest(Person $person, array $context, array $avoid, string $tier): LlmRequest
    {
        $source = $context['source'];
        $count = $context['count'];

        $profile = AiInputProfile::for($person);
        $this->candidates ??= Exercise::query()->whereKeyNot($source->id)->orderBy('name')->limit(200)
            ->get(['id', 'slug', 'name', 'primary_muscles', 'equipment']);

        $library = $this->candidates->map(fn (Exercise $e) => $e->slug.' — '.$e->name)->implode("\n");

        $system = 'You are a certified strength & conditioning coach. Suggest alternative exercises '
            .'that train a similar movement pattern and muscles to the target exercise, respecting '
            .'the athlete\'s available equipment and injuries. Use ONLY exercises from the provided '
            .'library, referenced by their exact slug. Respond with JSON only, matching the given '
            .'schema — no prose.';

        $prompt = 'Find up to '.$count." safe alternatives to: {$source->slug} — {$source->name}.\n\n"
            .'Athlete profile:\n'.json_encode($profile, JSON_PRETTY_PRINT)
            ."\n\nExercise library (slug — name):\n".$library;

        if ($avoid !== []) {
            $prompt .= "\n\nA previous suggestion was rejected by the safety review. Do NOT suggest "
                .'these contraindicated exercises: '.implode(', ', $avoid).'.';
        }

        return new LlmRequest(
            system: $system,
            prompt: $prompt,
            schema: $this->schema(),
            tier: $tier,
            feature: 'exercise_alternatives',
            metadata: ['source' => $source->slug, 'avoid' => $avoid],
        );
    }

    /** @return array<string, mixed> */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['alternatives'],
            'properties' => [
                'alternatives' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['exercise_slug'],
                        'properties' => [
                            'exercise_slug' => ['type' => 'string'],
                            'rationale' => ['type' => 'string'],
                        ],
                    ],
                ],
            ],
        ];
    }
}
