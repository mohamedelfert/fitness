<?php

namespace Modules\AiOrchestration\Support;

use Modules\Identity\Models\Person;
use Modules\Nutrition\Models\FoodItem;

/**
 * The dietary safety post-eval (FR-AI-007 / NFR-AI-002 / INV-005): the nutrition analog of
 * ContraindicationScanner. Given a Person's dietary_restrictions and the foods an AI-generated
 * meal plan prescribes, it returns the slugs that violate a restriction. A non-empty result
 * blocks the plan and triggers regeneration.
 *
 * Scope is deliberately the *safety* half — exclusions/allergens (dairy, nuts, pork, gluten,
 * shellfish, alcohol; halal/religious avoidance maps to these too). It does NOT enforce
 * positive *preferences* (e.g. "vegan requires a vegan-tagged food"); those are soft grounding
 * hints to the model, not hazards. Matching is a simple keyword heuristic that proves the
 * mechanism — the real licensed food-allergen ontology arrives with Q4 and slots in here.
 */
final class DietaryScanner
{
    /**
     * @param  iterable<FoodItem>  $foods
     * @return list<string> violating food slugs (empty ⇒ safe)
     */
    public function unsafeSlugs(Person $person, iterable $foods): array
    {
        $avoid = $this->restrictionKeywords($person);
        if ($avoid === []) {
            return [];
        }

        $unsafe = [];
        foreach ($foods as $food) {
            foreach ($food->dietary_tags ?? [] as $tag) {
                $tag = strtolower((string) $tag);
                foreach ($avoid as $keyword) {
                    if (str_contains($tag, $keyword)) {
                        $unsafe[] = $food->slug;

                        continue 3;
                    }
                }
            }
        }

        return array_values(array_unique($unsafe));
    }

    /**
     * Reduce the Person's dietary restrictions to lowercase exclusion keywords.
     *
     * @return list<string>
     */
    private function restrictionKeywords(Person $person): array
    {
        $restrictions = $person->trainingProfile()['dietary_restrictions'] ?? [];

        $keywords = [];
        foreach ($restrictions as $restriction) {
            $keyword = strtolower(trim((string) $restriction));
            if ($keyword !== '') {
                $keywords[] = $keyword;
            }
        }

        return array_values(array_unique($keywords));
    }
}
