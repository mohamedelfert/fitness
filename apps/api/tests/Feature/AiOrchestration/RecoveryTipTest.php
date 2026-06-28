<?php

namespace Tests\Feature\AiOrchestration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\AiOrchestration\Contracts\LlmGateway;
use Modules\AiOrchestration\Services\AiCreditMeter;
use Modules\AiOrchestration\Support\LlmRequest;
use Modules\AiOrchestration\Support\LlmResult;
use Modules\Identity\Models\Person;
use Modules\Wearables\Models\WearableStream;
use Tests\TestCase;

/**
 * AI recovery tips (FR-AI-005) — `GET /v1/ai/recovery`. Advisory recovery guidance grounded in
 * the Person's recent wearable data (sleep/HRV/resting-HR/steps) + an optional soreness signal.
 * Like the daily recommendation it prescribes no library entities, so there is no
 * contraindication sandwich — safety is by construction (the prompt forbids medical advice).
 * Generated fresh each call (reflects live data) and metered per call. Fake gateway (ADR-004).
 */
class RecoveryTipTest extends TestCase
{
    use RefreshDatabase;

    private function onboardedPerson(string $status = 'passed'): Person
    {
        $person = Person::factory()->create([
            'health_screen_status' => $status,
            'onboarding_state' => ['completed' => true, 'profile' => ['experience_level' => 'beginner', 'injuries' => []]],
        ]);
        app(AiCreditMeter::class)->grant($person, 10, 'test_grant');

        return $person;
    }

    private function scriptGateway(string $text, ?array &$requests = null): void
    {
        $requests = [];
        $gateway = new class($text, $requests) implements LlmGateway
        {
            public function __construct(private string $text, private ?array &$requests) {}

            public function complete(LlmRequest $request): LlmResult
            {
                $this->requests[] = $request;

                return new LlmResult($this->text, 100, 50, 'stub-cheap');
            }
        };
        $this->app->instance(LlmGateway::class, $gateway);
    }

    public function test_generates_recovery_advice(): void
    {
        $person = $this->onboardedPerson();
        $this->scriptGateway(json_encode(['message' => 'Your HRV is solid — train as planned, and prioritise 8h sleep.']));
        Sanctum::actingAs($person);

        $this->getJson('/v1/ai/recovery')
            ->assertOk()
            ->assertJsonPath('data.message', 'Your HRV is solid — train as planned, and prioritise 8h sleep.')
            ->assertJsonStructure(['data' => ['message', 'interaction_id']]);

        $this->assertDatabaseHas('ai_interactions', ['person_id' => $person->id, 'feature' => 'recovery', 'safety_verdict' => 'passed']);
        $this->assertSame(10 - (int) config('ai.credits.recovery'), app(AiCreditMeter::class)->balance($person));
    }

    public function test_grounds_on_recent_wearable_data_and_soreness(): void
    {
        $person = $this->onboardedPerson();
        WearableStream::create(['person_id' => $person->id, 'metric' => 'hrv', 'value' => 55, 'recorded_at' => now()->subHours(2)]);
        $this->scriptGateway(json_encode(['message' => 'Take it easy today.']), $requests);
        Sanctum::actingAs($person);

        $this->getJson('/v1/ai/recovery?soreness=severe')->assertOk();

        $prompt = $requests[0]->prompt;
        $this->assertStringContainsString('55', $prompt);      // the HRV reading reached the prompt
        $this->assertStringContainsString('severe', $prompt);  // soreness signal reached the prompt
    }

    public function test_works_without_any_wearable_data(): void
    {
        $person = $this->onboardedPerson();
        $this->scriptGateway(json_encode(['message' => 'Rest well and hydrate.']));
        Sanctum::actingAs($person);

        $this->getJson('/v1/ai/recovery')->assertOk()->assertJsonPath('data.message', 'Rest well and hydrate.');
    }

    public function test_blank_output_is_422_and_not_charged(): void
    {
        $person = $this->onboardedPerson();
        $this->scriptGateway('   ');
        Sanctum::actingAs($person);

        $this->getJson('/v1/ai/recovery')->assertStatus(422); // never 500
        $this->assertSame(10, app(AiCreditMeter::class)->balance($person));
    }

    public function test_rejects_invalid_soreness(): void
    {
        $person = $this->onboardedPerson();
        $this->scriptGateway(json_encode(['message' => 'x']));
        Sanctum::actingAs($person);

        $this->getJson('/v1/ai/recovery?soreness=excruciating')->assertStatus(422);
    }

    public function test_unscreened_person_is_blocked_by_the_safety_gate(): void
    {
        $person = $this->onboardedPerson('flagged');
        $this->scriptGateway(json_encode(['message' => 'x']));
        Sanctum::actingAs($person);

        $this->getJson('/v1/ai/recovery')->assertForbidden();
    }

    public function test_onboarding_incomplete_is_rejected(): void
    {
        $person = Person::factory()->create(['health_screen_status' => 'passed', 'onboarding_state' => null]);
        Sanctum::actingAs($person);

        $this->getJson('/v1/ai/recovery')->assertStatus(422);
    }

    public function test_without_credits_is_blocked_with_402(): void
    {
        $person = Person::factory()->create([
            'health_screen_status' => 'passed',
            'onboarding_state' => ['completed' => true, 'profile' => ['injuries' => []]],
        ]);
        $this->scriptGateway(json_encode(['message' => 'x']));
        Sanctum::actingAs($person);

        $this->getJson('/v1/ai/recovery')->assertStatus(402);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/v1/ai/recovery')->assertUnauthorized();
    }
}
