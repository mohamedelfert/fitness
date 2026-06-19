<?php

namespace Modules\AiOrchestration\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Modules\AiOrchestration\Contracts\LlmGateway;
use Modules\AiOrchestration\Support\DietaryScanner;
use Modules\AiOrchestration\Support\LlmRequest;
use Modules\Identity\Models\Person;
use Modules\Identity\Support\AiInputProfile;
use Modules\Nutrition\Models\FoodItem;
use Modules\Nutrition\Models\MealPlan;
use Modules\Nutrition\Models\MealPlanDay;
use Modules\Nutrition\Models\MealPlanItem;

/**
 * AI meal-plan generation (FR-AI-002) — the nutrition twin of ProgramGenerator. The safety
 * sandwich lives in AiGenerator; this supplies the food-library RAG request, the `days→meals`
 * shape, food-slug resolution, the DietaryScanner (dietary restrictions standing in for
 * contraindications), and persisting the meal_plans→days→items graph. INV-005: a MealPlan
 * persists only via finalize(), reached only after the dietary post-eval clears the output.
 */
class MealPlanGenerator extends AiGenerator
{
    private ?Collection $candidates = null;

    public function __construct(LlmGateway $gateway, DietaryScanner $scanner)
    {
        parent::__construct($gateway, $scanner);
    }

    public function generate(Person $person): MealPlan
    {
        return $this->runLoop($person);
    }

    protected function feature(): string
    {
        return 'meal_plan';
    }

    protected function exhaustedMessage(): string
    {
        return 'Could not generate a safe meal plan for your profile. Please try again.';
    }

    protected function parse(string $text): ?array
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
    protected function resolve(array $parsed, array $context): ?Collection
    {
        $slugs = collect($parsed['days'])
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

    /** Persist the validated plan as a MealPlan graph in one transaction. */
    protected function finalize(Person $person, array $parsed, Collection $resolved, array $context): MealPlan
    {
        return DB::transaction(function () use ($person, $parsed, $resolved) {
            $mealPlan = MealPlan::create([
                'person_id' => $person->id,
                'source' => 'ai',
                'name' => (string) ($parsed['name'] ?? 'AI Meal Plan'),
                'daily_targets_json' => is_array($parsed['daily_targets'] ?? null) ? $parsed['daily_targets'] : null,
                'start_date' => now()->toDateString(),
                'status' => 'active',
            ]);

            foreach (array_values($parsed['days']) as $dIndex => $d) {
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
        });
    }

    /**
     * Assemble the RAG-grounded, structured-output request. Grounding on the real food library
     * (NFR-AI-003) keeps slug hallucination rare; the dietary post-eval catches the rest.
     * Untested by the fake gateway — kept minimal and real for the Claude adapter (Q5).
     *
     * @param  array<string, mixed>  $context
     * @param  list<string>  $avoid
     */
    protected function buildRequest(Person $person, array $context, array $avoid, string $tier): LlmRequest
    {
        $profile = AiInputProfile::for($person);
        $this->candidates ??= FoodItem::query()->whereNotNull('slug')->orderBy('slug')->limit(300)
            ->get(['id', 'slug', 'name_i18n']);

        $library = $this->candidates
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
