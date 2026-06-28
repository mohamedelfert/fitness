<?php

namespace Tests\Feature\AiOrchestration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\AiOrchestration\Contracts\LlmGateway;
use Modules\AiOrchestration\Models\CoachMessage;
use Modules\AiOrchestration\Services\AiCreditMeter;
use Modules\AiOrchestration\Support\LlmRequest;
use Modules\AiOrchestration\Support\LlmResult;
use Modules\Identity\Models\Person;
use Tests\TestCase;

/**
 * Conversational AI coach (FR-AI-008) — `POST /v1/ai/coach/chat` (+ `GET` for history).
 * A multi-turn chat grounded in the Person's profile, persisted as one implicit thread per
 * person (coach_messages). Advisory-only like the daily recommendation: it prescribes no
 * library entities, so there is no contraindication sandwich — safety is by construction (the
 * system prompt forbids specific prescriptions). Metered per message (debit-once-on-success).
 * Built against the fake LlmGateway seam (ADR-004); streaming is deferred to the real adapter.
 */
class CoachChatTest extends TestCase
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

    /**
     * Bind a gateway returning the given texts in sequence (last repeats), capturing every
     * request into $requests so a test can assert what was sent to the Brain.
     *
     * @param  list<LlmRequest>  $requests
     */
    private function scriptGateway(array $texts, ?array &$requests = null): void
    {
        $requests = [];
        $gateway = new class($texts, $requests) implements LlmGateway
        {
            private int $i = 0;

            public function __construct(private array $texts, private ?array &$requests) {}

            public function complete(LlmRequest $request): LlmResult
            {
                $this->requests[] = $request;
                $text = $this->texts[min($this->i, count($this->texts) - 1)];
                $this->i++;

                return new LlmResult($text, 100, 60, 'stub-chat');
            }
        };

        $this->app->instance(LlmGateway::class, $gateway);
    }

    public function test_sends_a_message_and_persists_both_sides(): void
    {
        $person = $this->onboardedPerson();
        $this->scriptGateway(['Great question — focus on form and consistency this week.']);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/coach/chat', ['message' => 'How do I start?'])
            ->assertOk()
            ->assertJsonPath('data.reply', 'Great question — focus on form and consistency this week.')
            ->assertJsonStructure(['data' => ['reply', 'interaction_id']]);

        $this->assertDatabaseHas('coach_messages', ['person_id' => $person->id, 'role' => 'user', 'content' => 'How do I start?']);
        $this->assertDatabaseHas('coach_messages', ['person_id' => $person->id, 'role' => 'assistant']);
        $this->assertDatabaseHas('ai_interactions', ['person_id' => $person->id, 'feature' => 'coach_chat', 'safety_verdict' => 'passed']);
        $this->assertSame(10 - (int) config('ai.credits.coach_chat'), app(AiCreditMeter::class)->balance($person));
    }

    public function test_multi_turn_replays_prior_history_into_the_prompt(): void
    {
        $person = $this->onboardedPerson();
        $this->scriptGateway(['Squats are a great start.', 'Yes, three times a week is fine.'], $requests);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/coach/chat', ['message' => 'What about squats?'])->assertOk();
        $this->postJson('/v1/ai/coach/chat', ['message' => 'How often?'])->assertOk();

        // The second call's prompt must carry the earlier exchange (multi-turn context).
        $secondPrompt = $requests[1]->prompt;
        $this->assertStringContainsString('What about squats?', $secondPrompt);
        $this->assertStringContainsString('Squats are a great start.', $secondPrompt);
    }

    public function test_history_endpoint_returns_messages_in_order(): void
    {
        $person = $this->onboardedPerson();
        $this->scriptGateway(['Reply one.']);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/coach/chat', ['message' => 'Message one.'])->assertOk();

        $history = $this->getJson('/v1/ai/coach/chat')->assertOk()->json('data');
        $this->assertSame('user', $history[0]['role']);
        $this->assertSame('Message one.', $history[0]['content']);
        $this->assertSame('assistant', $history[1]['role']);
        $this->assertSame('Reply one.', $history[1]['content']);
    }

    public function test_blank_output_is_422_and_not_charged_and_persists_nothing(): void
    {
        $person = $this->onboardedPerson();
        $this->scriptGateway(['   ']); // unusable
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/coach/chat', ['message' => 'Hello?'])->assertStatus(422); // never 500

        $this->assertSame(10, app(AiCreditMeter::class)->balance($person));         // not charged
        $this->assertSame(0, CoachMessage::where('person_id', $person->id)->count()); // nothing persisted
    }

    public function test_message_is_required(): void
    {
        $person = $this->onboardedPerson();
        $this->scriptGateway(['x']);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/coach/chat', ['message' => '  '])->assertStatus(422);
    }

    public function test_oversized_message_is_rejected_not_500(): void
    {
        $person = $this->onboardedPerson();
        $this->scriptGateway(['x']);
        Sanctum::actingAs($person);

        // A message larger than the TEXT column must be a 422, never a "Data too long" 500.
        $this->postJson('/v1/ai/coach/chat', ['message' => str_repeat('a', 70000)])->assertStatus(422);
        $this->assertSame(0, CoachMessage::where('person_id', $person->id)->count());
    }

    public function test_non_string_message_is_rejected(): void
    {
        $person = $this->onboardedPerson();
        $this->scriptGateway(['x']);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/coach/chat', ['message' => [1, 2, 3]])->assertStatus(422);
    }

    public function test_unscreened_person_is_blocked_by_the_safety_gate(): void
    {
        $person = $this->onboardedPerson('flagged');
        $this->scriptGateway(['x']);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/coach/chat', ['message' => 'hi'])->assertForbidden();
    }

    public function test_onboarding_incomplete_is_rejected(): void
    {
        $person = Person::factory()->create(['health_screen_status' => 'passed', 'onboarding_state' => null]);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/coach/chat', ['message' => 'hi'])->assertStatus(422);
    }

    public function test_without_credits_is_blocked_with_402(): void
    {
        $person = Person::factory()->create([
            'health_screen_status' => 'passed',
            'onboarding_state' => ['completed' => true, 'profile' => ['injuries' => []]],
        ]); // onboarded but unfunded
        $this->scriptGateway(['x']);
        Sanctum::actingAs($person);

        $this->postJson('/v1/ai/coach/chat', ['message' => 'hi'])->assertStatus(402);
    }

    public function test_requires_authentication(): void
    {
        $this->postJson('/v1/ai/coach/chat', ['message' => 'hi'])->assertUnauthorized();
        $this->getJson('/v1/ai/coach/chat')->assertUnauthorized();
    }
}
