<?php

namespace Tests\Feature\AiOrchestration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\AiOrchestration\Listeners\GrantFreeAiCredits;
use Modules\AiOrchestration\Services\AiCreditMeter;
use Modules\Identity\Events\OnboardingCompleted;
use Modules\Identity\Models\Person;
use Tests\TestCase;

/**
 * Free AICredit starter grant on onboarding completion (FR-SAS-004, pre-billing stopgap).
 * Wallets start empty, so this is what lets a brand-new Person use any AI feature on first run.
 * Identity fires OnboardingCompleted; AiOrchestration's listener funds the wallet once.
 */
class FreeGrantTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'experience_level' => 'beginner',
            'equipment' => ['bodyweight'],
            'training_days_per_week' => 3,
            'injuries' => [],
            'goals' => [['type' => 'fat_loss']],
        ], $overrides);
    }

    public function test_completing_onboarding_grants_the_free_starter_credits(): void
    {
        $person = Person::factory()->create(['onboarding_state' => null]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/onboarding', $this->payload())->assertCreated();

        $this->assertSame((int) config('ai.credits.free_grant'), app(AiCreditMeter::class)->balance($person));
        $this->assertDatabaseHas('ai_credit_ledger', ['reason' => 'free_grant']);
    }

    public function test_resubmitting_onboarding_does_not_grant_twice(): void
    {
        $person = Person::factory()->create(['onboarding_state' => null]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/onboarding', $this->payload())->assertCreated();
        $this->postJson('/v1/onboarding', $this->payload())->assertCreated(); // retry/edit

        $this->assertSame((int) config('ai.credits.free_grant'), app(AiCreditMeter::class)->balance($person));
        $this->assertDatabaseCount('ai_credit_ledger', 1);
    }

    /** The ledger guard, exercised directly — the transition gate alone would never re-fire. */
    public function test_listener_is_idempotent_on_a_double_fire(): void
    {
        $person = Person::factory()->create();
        $listener = app(GrantFreeAiCredits::class);

        $listener->handle(new OnboardingCompleted($person));
        $listener->handle(new OnboardingCompleted($person));

        $this->assertSame((int) config('ai.credits.free_grant'), app(AiCreditMeter::class)->balance($person));
        $this->assertDatabaseCount('ai_credit_ledger', 1);
    }
}
