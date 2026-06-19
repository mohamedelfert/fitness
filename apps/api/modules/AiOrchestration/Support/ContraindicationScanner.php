<?php

namespace Modules\AiOrchestration\Support;

use Modules\AiOrchestration\Contracts\SafetyScanner;
use Modules\Identity\Models\Person;
use Modules\Training\Models\Exercise;

/**
 * The training safety post-eval (FR-AI-007 / NFR-AI-002 / INV-005): the second half of the
 * safety sandwich. Given a Person's injuries and the exercises an AI-generated plan prescribes,
 * it returns the slugs that are contraindicated for that Person. A non-empty result blocks
 * the plan and triggers regeneration.
 *
 * The matching here is a deliberately simple body-part heuristic — it proves the gating
 * *mechanism*, not clinical coverage. The real clinical contraindication ruleset (mapping
 * PAR-Q+ flags / injuries → forbidden movement patterns) is sourced under Q7 and slots in
 * behind this same interface (`docs/AI_BRAIN_SPIKE.md` §6).
 */
final class ContraindicationScanner implements SafetyScanner
{
    /**
     * @param  iterable<Exercise>  $exercises
     * @return list<string> contraindicated exercise slugs (empty ⇒ safe)
     */
    public function unsafeSlugs(Person $person, iterable $exercises): array
    {
        $parts = $this->injuredBodyParts($person);
        if ($parts === []) {
            return [];
        }

        $unsafe = [];
        foreach ($exercises as $exercise) {
            foreach ($exercise->contraindications ?? [] as $contraindication) {
                $tag = strtolower((string) $contraindication);
                foreach ($parts as $part) {
                    if (str_contains($tag, $part)) {
                        $unsafe[] = $exercise->slug;

                        continue 3;
                    }
                }
            }
        }

        return array_values(array_unique($unsafe));
    }

    /**
     * Reduce the Person's stated injuries to body-part keywords, dropping laterality
     * ("left_knee" → "knee") so they match contraindication tags like "knee_injury".
     *
     * @return list<string>
     */
    private function injuredBodyParts(Person $person): array
    {
        $injuries = $person->trainingProfile()['injuries'] ?? [];

        $parts = [];
        foreach ($injuries as $injury) {
            $part = preg_replace('/^(left|right|l|r)[_\- ]/', '', strtolower(trim((string) $injury)));
            if ($part !== '' && $part !== null) {
                $parts[] = $part;
            }
        }

        return array_values(array_unique($parts));
    }
}
