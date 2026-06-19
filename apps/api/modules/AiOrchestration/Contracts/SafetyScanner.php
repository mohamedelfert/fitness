<?php

namespace Modules\AiOrchestration\Contracts;

use Modules\Identity\Models\Person;

/**
 * The safety post-eval seam (FR-AI-007 / NFR-AI-002 / INV-005). Given a Person and the library
 * entities an AI-generated output prescribes, return the slugs that are unsafe for that Person.
 * A non-empty result blocks the output and triggers regeneration. ContraindicationScanner
 * (exercises vs injuries) and DietaryScanner (foods vs dietary restrictions) both implement it,
 * which is what lets AiGenerator run one safety loop across every generator regardless of domain.
 */
interface SafetyScanner
{
    /**
     * @param  iterable<object>  $items  library entities exposing a `slug`
     * @return list<string> unsafe slugs (empty ⇒ safe)
     */
    public function unsafeSlugs(Person $person, iterable $items): array;
}
