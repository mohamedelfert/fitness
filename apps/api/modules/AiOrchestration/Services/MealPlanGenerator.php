<?php

namespace Modules\AiOrchestration\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Modules\AiOrchestration\Contracts\LlmGateway;
use Modules\AiOrchestration\Models\AiInteraction;
use Modules\AiOrchestration\Support\DietaryScanner;
use Modules\AiOrchestration\Support\LlmRequest;
use Modules\AiOrchestration\Support\LlmResult;
use Modules\Identity\Models\Person;
use Modules\Identity\Support\AiInputProfile;
use Modules\Nutrition\Models\FoodItem;
use Modules\Nutrition\Models\MealPlan;
use Modules\Nutrition\Models\MealPlanDay;
use Modules\Nutrition\Models\MealPlanItem;

/**
 * AI meal-plan generation (FR-AI-002) wrapped in the same safety sandwich as ProgramGenerator
 * (FR-AI-007 / INV-005), with the dietary post-eval standing in for the contraindication scan:
 *
 *   RAG context (the Person's Graph) → generate → parse → resolve to real foods →
 *   dietary safety post-eval → reject + regenerate on fail → persist only when clean.
 *
 * INV-005 holds identically: a MealPlan is persisted ONLY after the output passes the
 * post-eval. Every call is logged to ai_interactions, outside the persist transaction so the
 * rejected trail survives a 422. (The attempt-loop/logging shape intentionally mirrors
 * ProgramGenerator; once a third generator lands this is worth extracting to a shared base.)
 */
class MealPlanGenerator
{
    public function __construct(
        private readonly LlmGateway $gateway,
        private readonly DietaryScanner $scanner,
    ) {}

    public function generate(Person $person): MealPlan
    {
        $profile = AiInputProfile::for($person);
        $candidates = FoodItem::query()->whereNotNull('slug')->orderBy('slug')->limit(300)
            ->get(['id', 'slug', 'name_i18n']);
        $tier = (string) config('ai.meal_plan.tier', 'strong');
        $maxAttempts = (int) config('ai.meal_plan.max_attempts', 2);

        $avoid = [];

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $request = $this->buildRequest($profile, $candidates, $avoid, $tier);

            $startedAt = microtime(true);
            $result = $this->gateway->complete($request);
            $latencyMs = (int) round((microtime(true) - $startedAt) * 1000);

            $plan = $this->parse($result->text);
            if ($plan === null) {
                $this->log($person, $result, 'error', $latencyMs, $tier);

                continue; // malformed output — never a 500; retry then give up
            }

            $resolved = $this->resolveFoods($plan);
            if ($resolved === null) {
                $this->log($person, $result, 'error', $latencyMs, $tier);

                continue; // hallucinated slug — retry then give up
            }

            $unsafe = $this->scanner->unsafeSlugs($person, $resolved->values());
            if ($unsafe !== []) {
                $this->log($person, $result, 'rejected', $latencyMs, $tier);
                $avoid = array_values(array_unique([...$avoid, ...$unsafe]));

                continue; // violates a dietary restriction — regenerate avoiding these foods
            }

            $this->log($person, $result, 'passed', $latencyMs, $tier);

            return DB::transaction(fn () => $this->persist($person, $plan, $resolved));
        }

        // Exhausted attempts without a safe, valid plan. INV-005: nothing persisted.
        throw ValidationException::withMessages([
            'meal_plan' => 'Could not generate a safe meal plan for your profile. Please try again.',
        ]);
    }

    /** Decode model output to a plan array, or null if it isn't a usable meal-plan object. */
    private function parse(string $text): ?array
    {
        $decoded = json_decode($text, true);

        if (! is_array($decoded) || ! isset($decoded['days']) || ! is_array($decoded['days'])) {
            return null;
        }

        return $decoded;
    }

    /**
     * Resolve every prescribed food slug to a real FoodItem. Returns null if the model
     * hallucinated a slug not in the library (caller treats it as a failed attempt).
     *
     * @return Collection<string, FoodItem>|null keyed by slug
     */
    private function resolveFoods(array $plan): ?Collection
    {
        $slugs = collect($plan['days'])
            ->flatMap(fn ($d) => collect($d['meals'] ?? [])->pluck('food_slug'))
            ->filter()
            ->unique()
            ->values();

        if ($slugs->isEmpty()) {
            return null;
        }

        $found = FoodItem::whereIn('slug', $slugs)->get()->keyBy('slug');

        return $found->count() === $slugs->count() ? $found : null;
    }

    /** Persist the validated plan as a MealPlan graph. Caller wraps this in a transaction. */
    private function persist(Person $person, array $plan, Collection $resolved): MealPlan
    {
        $mealPlan = MealPlan::create([
            'person_id' => $person->id,
            'source' => 'ai',
            'name' => (string) ($plan['name'] ?? 'AI Meal Plan'),
            'daily_targets_json' => is_array($plan['daily_targets'] ?? null) ? $plan['daily_targets'] : null,
            'start_date' => now()->toDateString(),
            'status' => 'active',
        ]);

        foreach (array_values($plan['days']) as $dIndex => $d) {
            $day = MealPlanDay::create([
                'meal_plan_id' => $mealPlan->id,
                'day_index' => (int) ($d['day_index'] ?? $dIndex + 1),
                'name' => (string) ($d['name'] ?? 'Day '.($dIndex + 1)),
                'ordering' => $dIndex,
            ]);

            foreach (array_values($d['meals'] ?? []) as $mIndex => $m) {
                MealPlanItem::create([
                    'meal_plan_day_id' => $day->id,
                    'meal_type' => (string) ($m['meal_type'] ?? 'meal'),
                    'food_item_id' => $resolved[$m['food_slug']]->id,
                    'servings' => $m['servings'] ?? 1,
                    'target_kcal' => $m['target_kcal'] ?? null,
                    'target_macros_json' => is_array($m['target_macros'] ?? null) ? $m['target_macros'] : null,
                    'ordering' => $mIndex,
                    'notes' => $m['notes'] ?? null,
                ]);
            }
        }

        return $mealPlan->load(['days.items.foodItem']);
    }

    private function log(Person $person, LlmResult $result, string $verdict, int $latencyMs, string $tier): void
    {
        AiInteraction::create([
            'person_id' => $person->id,
            'feature' => 'meal_plan',
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

    /**
     * Assemble the RAG-grounded, structured-output request. Grounding on the real food library
     * (NFR-AI-003) keeps slug hallucination rare; the dietary post-eval catches the rest.
     * Untested by the fake gateway — kept minimal and real for the Claude adapter (Q5).
     *
     * @param  Collection<int, FoodItem>  $candidates
     * @param  list<string>  $avoid
     */
    private function buildRequest(array $profile, Collection $candidates, array $avoid, string $tier): LlmRequest
    {
        $library = $candidates
            ->map(fn (FoodItem $f) => $f->slug.' — '.$f->localizedName('en'))
            ->implode("\n");

        $system = 'You are a registered dietitian. Generate a safe, balanced meal plan '
            .'personalized to the athlete profile and their goals. Use ONLY foods from the '
            .'provided library, referenced by their exact slug. Respect the athlete\'s dietary '
            .'restrictions and preferences absolutely. Respond with JSON only, matching the '
            .'given schema — no prose.';

        $prompt = "Athlete profile:\n".json_encode($profile, JSON_PRETTY_PRINT)
            ."\n\nFood library (slug — name):\n".$library;

        if ($avoid !== []) {
            $prompt .= "\n\nA previous attempt was rejected by the dietary safety review. Do NOT "
                .'prescribe these foods: '.implode(', ', $avoid).'.';
        }

        return new LlmRequest(
            system: $system,
            prompt: $prompt,
            schema: $this->schema(),
            tier: $tier,
            feature: 'meal_plan',
            metadata: ['avoid' => $avoid],
        );
    }

    /** @return array<string, mixed> */
    private function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['name', 'days'],
            'properties' => [
                'name' => ['type' => 'string'],
                'daily_targets' => [
                    'type' => 'object',
                    'properties' => [
                        'kcal' => ['type' => 'number'],
                        'protein' => ['type' => 'number'],
                        'carbs' => ['type' => 'number'],
                        'fat' => ['type' => 'number'],
                    ],
                ],
                'days' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'required' => ['day_index', 'name', 'meals'],
                        'properties' => [
                            'day_index' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                            'meals' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'required' => ['meal_type', 'food_slug', 'servings'],
                                    'properties' => [
                                        'meal_type' => ['type' => 'string'],
                                        'food_slug' => ['type' => 'string'],
                                        'servings' => ['type' => 'number'],
                                        'target_kcal' => ['type' => 'number'],
                                        'target_macros' => ['type' => 'object'],
                                        'notes' => ['type' => 'string'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }
}
