<?php

namespace Tests\Feature\AiOrchestration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\AiOrchestration\Contracts\LlmGateway;
use Modules\AiOrchestration\Services\AiCreditMeter;
use Modules\AiOrchestration\Support\LlmRequest;
use Modules\AiOrchestration\Support\LlmResult;
use Modules\Identity\Models\Person;
use Modules\Training\Models\Exercise;
use Tests\TestCase;

/**
 * AICredit metering (FR-SAS-004 / NFR-OPS-002). Every AI generation debits the Person's
 * credit wallet; an exhausted wallet is a 402 (API_SPECIFICATION §4). Built against the
 * fake LlmGateway seam (ADR-004) like ProgramGenerationTest — these prove the *metering*
 * mechanism: debit-once-on-success, no-charge-on-failure, no-negative-balance.
 */
class AiCreditTest extends TestCase
{
    use RefreshDatabase;

    private function onboardedPerson(string $status = 'passed', array $injuries = []): Person
    {
        return Person::factory()->create([
            'health_screen_status' => $status,
            'onboarding_state' => [
                'completed' => true,
                'profile' => ['experience_level' => 'beginner', 'equipment' => ['bodyweight'], 'injuries' => $injuries],
            ],
        ]);
    }

    private function grant(Person $person, int $credits): void
    {
        app(AiCreditMeter::class)->grant($person, $credits, 'test_grant');
    }

    private function programJson(array $slugs): string
    {
        return json_encode([
            'name' => 'AI Starter Program',
            'workouts' => [[
                'day_index' => 1,
                'name' => 'Full Body',
                'exercises' => array_map(fn ($s) => [
                    'exercise_slug' => $s, 'target_sets' => 3, 'target_reps' => '8-12', 'rest_sec' => 90,
                ], $slugs),
            ]],
        ]);
    }

    /** Bind a gateway that returns the given response texts in sequence (last repeats). */
    private function scriptGateway(array $texts): void
    {
        $gateway = new class($texts) implements LlmGateway
        {
            private int $i = 0;

            public function __construct(private array $texts) {}

            public function complete(LlmRequest $request): LlmResult
            {
                $text = $this->texts[min($this->i, count($this->texts) - 1)];
                $this->i++;

                return new LlmResult($text, 400, 600, 'stub-1');
            }
        };

        $this->app->instance(LlmGateway::class, $gateway);
    }

    public function test_generating_a_program_debits_one_credit_and_records_a_ledger_entry(): void
    {
        $person = $this->onboardedPerson();
        $this->grant($person, 10);
        Exercise::factory()->create(['slug' => 'push-up', 'name' => 'Push-up', 'contraindications' => []]);
        $this->scriptGateway([$this->programJson(['push-up'])]);
        Sanctum::actingAs($person);

        $cost = (int) config('ai.credits.program');

        $this->postJson('/v1/ai/program')->assertCreated();

        $this->assertSame(10 - $cost, app(AiCreditMeter::class)->balance($person));
        $this->assertDatabaseHas('ai_credit_ledger', [
            'reason' => 'program_generation',
            'delta' => -$cost,
            'balance_after' => 10 - $cost,
        ]);
    }

    public function test_generation_without_credits_is_blocked_with_402(): void
    {
        $person = $this->onboardedPerson();
        Exercise::factory()->create(['slug' => 'push-up', 'name' => 'Push-up', 'contraindications' => []]);
        $this->scriptGateway([$this->programJson(['push-up'])]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/program')->assertStatus(402);

        $this->assertDatabaseCount('programs', 0);
        $this->assertDatabaseMissing('ai_credit_ledger', ['reason' => 'program_generation']);
    }

    public function test_balance_below_cost_is_blocked_with_402(): void
    {
        config(['ai.credits.program' => 2]); // make the >= boundary non-trivial
        $person = $this->onboardedPerson();
        $this->grant($person, 1); // funded, but strictly less than one generation costs
        Exercise::factory()->create(['slug' => 'push-up', 'name' => 'Push-up', 'contraindications' => []]);
        $this->scriptGateway([$this->programJson(['push-up'])]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/program')->assertStatus(402);
        $this->assertDatabaseCount('programs', 0);
        $this->assertSame(1, app(AiCreditMeter::class)->balance($person)); // untouched
    }

    public function test_failed_generation_does_not_debit_credits(): void
    {
        $person = $this->onboardedPerson();
        $this->grant($person, 10);
        $this->scriptGateway(['this is not json at all']); // malformed → 422
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/program')->assertStatus(422);

        $this->assertSame(10, app(AiCreditMeter::class)->balance($person)); // unchanged
        $this->assertDatabaseMissing('ai_credit_ledger', ['reason' => 'program_generation']);
    }

    public function test_reject_and_regenerate_debits_exactly_once(): void
    {
        $person = $this->onboardedPerson('passed', ['left_knee']);
        $this->grant($person, 10);
        Exercise::factory()->create(['slug' => 'back-squat', 'name' => 'Back Squat', 'contraindications' => ['knee_injury']]);
        Exercise::factory()->create(['slug' => 'push-up', 'name' => 'Push-up', 'contraindications' => []]);
        // First attempt unsafe, second safe — internally two Brain calls, ONE persisted plan.
        $this->scriptGateway([$this->programJson(['back-squat']), $this->programJson(['push-up'])]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/program')->assertCreated();

        $cost = (int) config('ai.credits.program');
        $this->assertSame(10 - $cost, app(AiCreditMeter::class)->balance($person));
        $this->assertDatabaseCount('ai_credit_ledger', 2); // one grant + one debit
    }

    public function test_wallet_balance_endpoint_returns_current_balance(): void
    {
        $person = $this->onboardedPerson();
        $this->grant($person, 7);
        Sanctum::actingAs($person);

        $this->getJson('/v1/me/ai-credits')
            ->assertOk()
            ->assertJsonPath('data.balance', 7);
    }

    public function test_wallet_balance_endpoint_auto_provisions_an_empty_wallet(): void
    {
        $person = $this->onboardedPerson();
        Sanctum::actingAs($person);

        $this->getJson('/v1/me/ai-credits')
            ->assertOk()
            ->assertJsonPath('data.balance', 0);
    }

    public function test_ai_credits_balance_requires_authentication(): void
    {
        $this->getJson('/v1/me/ai-credits')->assertUnauthorized();
    }
}
