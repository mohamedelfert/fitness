<?php

namespace Tests\Feature\AiOrchestration;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Modules\AiOrchestration\Contracts\LlmGateway;
use Modules\AiOrchestration\Models\WeeklyReport;
use Modules\AiOrchestration\Services\AiCreditMeter;
use Modules\AiOrchestration\Support\LlmRequest;
use Modules\AiOrchestration\Support\LlmResult;
use Modules\Biometrics\Models\Biometric;
use Modules\Identity\Models\Person;
use Modules\Training\Models\Session;
use Tests\TestCase;

/**
 * Weekly AI report (FR-AN-005) — `GET /v1/me/reports/weekly`. Advisory narrative grounded in the
 * Person's progress + adherence read-models. Like the daily recommendation it prescribes no
 * library entities → no contraindication sandwich (safety by construction via the prompt).
 * Materialised once per ISO week (unique person+iso_week): a same-week refresh is cache-served,
 * free, no second model call (NFR-AI-001). Built against the fake LlmGateway seam (ADR-004).
 */
class WeeklyReportTest extends TestCase
{
    use RefreshDatabase;

    private function isoWeek(): string
    {
        return now()->format('o-\WW');
    }

    private function onboardedPerson(string $status = 'passed'): Person
    {
        $person = Person::factory()->create([
            'health_screen_status' => $status,
            'onboarding_state' => ['completed' => true, 'profile' => ['experience_level' => 'beginner', 'injuries' => []]],
        ]);
        app(AiCreditMeter::class)->grant($person, 10, 'test_grant');

        return $person;
    }

    private function scriptGateway(string $text, ?int &$calls = null, ?array &$requests = null): void
    {
        $requests = [];
        $gateway = new class($text, $calls, $requests) implements LlmGateway
        {
            public function __construct(private string $text, private ?int &$calls, private ?array &$requests) {}

            public function complete(LlmRequest $request): LlmResult
            {
                $this->calls = ($this->calls ?? 0) + 1;
                $this->requests[] = $request;

                return new LlmResult($this->text, 200, 120, 'stub-default');
            }
        };
        $this->app->instance(LlmGateway::class, $gateway);
    }

    public function test_generates_weekly_report(): void
    {
        $person = $this->onboardedPerson();
        $this->scriptGateway(json_encode(['summary' => 'Strong week — 3 sessions logged and weight trending down. Keep the streak going.']));
        Sanctum::actingAs($person);

        $this->getJson('/v1/me/reports/weekly')
            ->assertOk()
            ->assertJsonPath('data.iso_week', $this->isoWeek())
            ->assertJsonPath('data.summary', 'Strong week — 3 sessions logged and weight trending down. Keep the streak going.')
            ->assertJsonStructure(['data' => ['iso_week', 'week_start', 'summary']]);

        $this->assertDatabaseHas('ai_interactions', ['person_id' => $person->id, 'feature' => 'weekly_report', 'safety_verdict' => 'passed']);
        $this->assertDatabaseHas('weekly_reports', ['person_id' => $person->id, 'iso_week' => $this->isoWeek()]);
        $this->assertSame(10 - (int) config('ai.credits.weekly_report'), app(AiCreditMeter::class)->balance($person));
    }

    public function test_grounds_on_progress_and_adherence(): void
    {
        $person = $this->onboardedPerson();
        Biometric::create(['person_id' => $person->id, 'type' => 'weight', 'value' => 80, 'unit' => 'kg', 'measured_at' => now()->subDays(14)]);
        Biometric::create(['person_id' => $person->id, 'type' => 'weight', 'value' => 78, 'unit' => 'kg', 'measured_at' => now()]);
        Session::create(['person_id' => $person->id, 'started_at' => now()->subDays(2)]);

        $this->scriptGateway(json_encode(['summary' => 'ok']), $calls, $requests);
        Sanctum::actingAs($person);

        $this->getJson('/v1/me/reports/weekly')->assertOk();

        $prompt = $requests[0]->prompt;
        $this->assertStringContainsString('weight', $prompt);        // progress: the metric they logged
        $this->assertStringContainsString('"sessions": 1', $prompt); // adherence: the 1 session they logged
    }

    public function test_second_request_same_week_is_cached_and_not_recharged(): void
    {
        $person = $this->onboardedPerson();
        $calls = 0;
        $this->scriptGateway(json_encode(['summary' => 'Consistent week.']), $calls);
        Sanctum::actingAs($person);

        $first = $this->getJson('/v1/me/reports/weekly')->assertOk();
        $second = $this->getJson('/v1/me/reports/weekly')->assertOk();

        $this->assertSame($first->json('data.summary'), $second->json('data.summary'));
        $this->assertSame(1, $calls); // model called once for the week
        $this->assertSame(1, WeeklyReport::where('person_id', $person->id)->count());
        $this->assertSame(10 - (int) config('ai.credits.weekly_report'), app(AiCreditMeter::class)->balance($person));
    }

    public function test_accepts_plain_text_output(): void
    {
        $person = $this->onboardedPerson();
        $this->scriptGateway('A solid, consistent week. Keep it up.'); // not JSON
        Sanctum::actingAs($person);

        $this->getJson('/v1/me/reports/weekly')->assertOk()
            ->assertJsonPath('data.summary', 'A solid, consistent week. Keep it up.');
    }

    public function test_unscreened_person_is_blocked_by_the_safety_gate(): void
    {
        $person = $this->onboardedPerson('flagged');
        $this->scriptGateway(json_encode(['summary' => 'x']));
        Sanctum::actingAs($person);

        $this->getJson('/v1/me/reports/weekly')->assertForbidden();
    }

    public function test_onboarding_incomplete_is_rejected(): void
    {
        $person = Person::factory()->create(['health_screen_status' => 'passed', 'onboarding_state' => null]);
        Sanctum::actingAs($person);

        $this->getJson('/v1/me/reports/weekly')->assertStatus(422);
    }

    public function test_blank_model_output_is_handled_gracefully(): void
    {
        $person = $this->onboardedPerson();
        $this->scriptGateway('   '); // nothing usable
        Sanctum::actingAs($person);

        $this->getJson('/v1/me/reports/weekly')->assertStatus(422); // never 500
        $this->assertSame(10, app(AiCreditMeter::class)->balance($person)); // not charged
    }

    public function test_without_credits_is_blocked_with_402(): void
    {
        $person = Person::factory()->create([
            'health_screen_status' => 'passed',
            'onboarding_state' => ['completed' => true, 'profile' => ['injuries' => []]],
        ]); // onboarded but unfunded
        $this->scriptGateway(json_encode(['summary' => 'x']));
        Sanctum::actingAs($person);

        $this->getJson('/v1/me/reports/weekly')->assertStatus(402);
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/v1/me/reports/weekly')->assertUnauthorized();
    }
}
