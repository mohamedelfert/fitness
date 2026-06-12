<?php

namespace Tests\Feature\Platform;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_health_endpoint_reports_database_up(): void
    {
        $this->getJson('/v1/health')
            ->assertOk()
            ->assertJsonPath('checks.database', 'ok')
            ->assertJsonStructure(['status', 'checks' => ['database', 'redis'], 'time']);
    }

    public function test_responses_carry_a_request_id_header(): void
    {
        $this->getJson('/v1/health')
            ->assertHeader('X-Request-Id');
    }

    public function test_inbound_request_id_is_echoed_back(): void
    {
        $id = '01JQREQUESTID0000000000000';

        $this->getJson('/v1/health', ['X-Request-Id' => $id])
            ->assertHeader('X-Request-Id', $id);
    }
}
