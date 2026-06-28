<?php

namespace Tests\Feature\AiOrchestration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\AiOrchestration\Contracts\LlmGateway;
use Modules\AiOrchestration\Models\DailyRecommendation;
use Modules\AiOrchestration\Services\AiCreditMeter;
use Modules\AiOrchestration\Support\LlmRequest;
use Modules\AiOrchestration\Support\LlmResult;
use Modules\Identity\Models\Person;
use Tests\TestCase;

/**
 * AI daily recommendation (FR-AI-004) — `GET /v1/ai/recommendations/today`. Unlike the plan
 * generators this is advisory-only (a motivational/behavioural nudge), so it prescribes no
 * library entities and runs no contraindication sandwich — the safety is by construction (the
 * prompt forbids specific prescriptions). It is materialised once per person per day: a second
 * request the same day returns the cached recommendation and is NOT recharged (server-side
 * cost control, NFR-AI-001). Built against the fake LlmGateway seam (ADR-004).
 */
class DailyRecommendationTest extends TestCase
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

    private function scriptGateway(string $text, ?int &$calls = null): void
    {
        $gateway = new class($text, $calls) implements LlmGateway
        {
            public function __construct(private string $text, private ?int &$calls) {}

            public function complete(LlmRequest $request): LlmResult
            {
                $this->calls = ($this->calls ?? 0) + 1;

                return new LlmResult($this->text, 120, 80, 'stub-cheap');
            }
        };

        $this->app->instance(LlmGateway::class, $gateway);
    }

    public function test_generates_todays_recommendation(): void
    {
        $person = $this->onboardedPerson();
        $this->scriptGateway(json_encode(['message' => 'Focus on consistency today — a short session beats none.']));
        Sanctum::actingAs($person);

        $this->getJson('/v1/ai/recommendations/today')
            ->assertOk()
            ->assertJsonPath('data.date', now()->toDateString())
            ->assertJsonPath('data.message', 'Focus on consistency today — a short session beats none.');

        $this->assertDatabaseHas('ai_interactions', [
            'person_id' => $person->id, 'feature' => 'daily_recommendation', 'safety_verdict' => 'passed',
        ]);
        $this->assertDatabaseHas('daily_recommendations', ['person_id' => $person->id, 'rec_date' => now()->toDateString()]);
        $this->assertSame(10 - (int) config('ai.credits.daily_recommendation'), app(AiCreditMeter::class)->balance($person));
    }

    public function test_accepts_plain_text_output(): void
    {
        $person = $this->onboardedPerson();
        $this->scriptGateway('Hydrate well and aim for your protein target.'); // not JSON
        Sanctum::actingAs($person);

        $this->getJson('/v1/ai/recommendations/today')
            ->assertOk()
            ->assertJsonPath('data.message', 'Hydrate well and aim for your protein target.');
    }

    public function test_second_request_same_day_is_cached_and_not_recharged(): void
    {
        $person = $this->onboardedPerson();
        $calls = 0;
        $this->scriptGateway(json_encode(['message' => 'Keep your streak alive.']), $calls);
        Sanctum::actingAs($person);

        $first = $this->getJson('/v1/ai/recommendations/today')->assertOk();
        $second = $this->getJson('/v1/ai/recommendations/today')->assertOk();

        $this->assertSame($first->json('data.message'), $second->json('data.message'));
        $this->assertSame(1, $calls); // model called once
        $this->assertSame(1, DailyRecommendation::where('person_id', $person->id)->count());
        // Debited exactly once for the day.
        $this->assertSame(10 - (int) config('ai.credits.daily_recommendation'), app(AiCreditMeter::class)->balance($person));
    }

    public function test_unscreened_person_is_blocked_by_the_safety_gate(): void
    {
        $person = $this->onboardedPerson('flagged');
        $this->scriptGateway(json_encode(['message' => 'x']));
        Sanctum::actingAs($person);

        $this->getJson('/v1/ai/recommendations/today')->assertForbidden();
    }

    public function test_onboarding_incomplete_is_rejected(): void
    {
        $person = Person::factory()->create(['health_screen_status' => 'passed', 'onboarding_state' => null]);
        Sanctum::actingAs($person);

        $this->getJson('/v1/ai/recommendations/today')->assertStatus(422);
    }

    public function test_blank_model_output_is_handled_gracefully(): void
    {
        $person = $this->onboardedPerson();
        $this->scriptGateway('   '); // nothing usable
        Sanctum::actingAs($person);

        $this->getJson('/v1/ai/recommendations/today')->assertStatus(422); // never 500
        $this->assertSame(10, app(AiCreditMeter::class)->balance($person)); // not charged
    }

    public function test_without_credits_is_blocked_with_402(): void
    {
        $person = Person::factory()->create([
            'health_screen_status' => 'passed',
            'onboarding_state' => ['completed' => true, 'profile' => ['injuries' => []]],
        ]); // onboarded but unfunded
        $this->scriptGateway(json_encode(['message' => 'x']));
        Sanctum::actingAs($person);

        $this->getJson('/v1/ai/recommendations/today')->assertStatus(402);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/v1/ai/recommendations/today')->assertUnauthorized();
    }
}
