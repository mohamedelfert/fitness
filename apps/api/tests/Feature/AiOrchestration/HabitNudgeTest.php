<?php

namespace Tests\Feature\AiOrchestration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\AiOrchestration\Contracts\LlmGateway;
use Modules\AiOrchestration\Services\AiCreditMeter;
use Modules\AiOrchestration\Support\LlmRequest;
use Modules\AiOrchestration\Support\LlmResult;
use Modules\Engagement\Models\Habit;
use Modules\Engagement\Models\HabitLog;
use Modules\Identity\Models\Person;
use Tests\TestCase;

/**
 * AI habit nudge (FR-ENG-002 — "behavioural nudges beyond raw streaks") — `GET /v1/ai/habit-nudge`.
 * Advisory contextual nudge grounded in the Person's active habits + each habit's streak / whether
 * it's been logged today. Like recovery/daily-rec it prescribes no library entities → no
 * contraindication sandwich (safety by construction). Fresh each call, metered. Fake gateway.
 */
class HabitNudgeTest extends TestCase
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

                return new LlmResult($this->text, 90, 40, 'stub-cheap');
            }
        };
        $this->app->instance(LlmGateway::class, $gateway);
    }

    public function test_generates_a_habit_nudge(): void
    {
        $person = $this->onboardedPerson();
        $this->scriptGateway(json_encode(['message' => "You're on a 3-day stretch streak — keep it going today!"]));
        Sanctum::actingAs($person);

        $this->getJson('/v1/ai/habit-nudge')
            ->assertOk()
            ->assertJsonPath('data.message', "You're on a 3-day stretch streak — keep it going today!")
            ->assertJsonStructure(['data' => ['message', 'interaction_id']]);

        $this->assertDatabaseHas('ai_interactions', ['person_id' => $person->id, 'feature' => 'habit_nudge', 'safety_verdict' => 'passed']);
        $this->assertSame(10 - (int) config('ai.credits.habit_nudge'), app(AiCreditMeter::class)->balance($person));
    }

    public function test_grounds_on_the_persons_habits(): void
    {
        $person = $this->onboardedPerson();
        $habit = Habit::factory()->for($person)->create(['name' => 'Drink water']);
        HabitLog::create(['habit_id' => $habit->id, 'person_id' => $person->id, 'logged_at' => now()]);
        $this->scriptGateway(json_encode(['message' => 'ok']), $requests);
        Sanctum::actingAs($person);

        $this->getJson('/v1/ai/habit-nudge')->assertOk();

        $this->assertStringContainsString('Drink water', $requests[0]->prompt);
    }

    public function test_unscreened_person_is_blocked_by_the_safety_gate(): void
    {
        $person = $this->onboardedPerson('flagged');
        $this->scriptGateway(json_encode(['message' => 'x']));
        Sanctum::actingAs($person);

        $this->getJson('/v1/ai/habit-nudge')->assertForbidden();
    }

    public function test_onboarding_incomplete_is_rejected(): void
    {
        $person = Person::factory()->create(['health_screen_status' => 'passed', 'onboarding_state' => null]);
        Sanctum::actingAs($person);

        $this->getJson('/v1/ai/habit-nudge')->assertStatus(422);
    }

    public function test_blank_model_output_is_handled_gracefully(): void
    {
        $person = $this->onboardedPerson();
        $this->scriptGateway('   ');
        Sanctum::actingAs($person);

        $this->getJson('/v1/ai/habit-nudge')->assertStatus(422); // never 500
        $this->assertSame(10, app(AiCreditMeter::class)->balance($person)); // not charged
    }

    public function test_without_credits_is_blocked_with_402(): void
    {
        $person = Person::factory()->create([
            'health_screen_status' => 'passed',
            'onboarding_state' => ['completed' => true, 'profile' => ['injuries' => []]],
        ]);
        $this->scriptGateway(json_encode(['message' => 'x']));
        Sanctum::actingAs($person);

        $this->getJson('/v1/ai/habit-nudge')->assertStatus(402);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/v1/ai/habit-nudge')->assertUnauthorized();
    }
}
