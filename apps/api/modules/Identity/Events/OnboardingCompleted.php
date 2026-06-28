<?php

namespace Modules\Identity\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Modules\Identity\Models\Person;

/**
 * Fired once when a Person *transitions* into completed onboarding (not on every re-submit).
 * A domain event so cross-module reactions (e.g. AiOrchestration granting the free AICredit
 * starter balance) hang off Identity without Identity depending on those modules.
 */
class OnboardingCompleted
{
    use Dispatchable;

    public function __construct(public readonly Person $person) {}
}
