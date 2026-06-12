<?php

namespace Tests\Feature\Admin;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Platform\Models\PlatformUser;
use Tests\TestCase;

/**
 * Super-admin (Filament) smoke tests. Filament rendering requires ext-intl,
 * which is absent on some dev hosts — these auto-skip there and run in CI/Docker
 * (PHP 8.3 + intl). They verify the panel renders and is guard-protected.
 */
class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! extension_loaded('intl')) {
            $this->markTestSkipped('Filament requires the intl extension (present in CI/Docker).');
        }
    }

    public function test_login_page_renders(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_platform_admin_can_reach_the_dashboard(): void
    {
        $admin = PlatformUser::factory()->create();

        $this->actingAs($admin, 'admin')
            ->get('/admin')
            ->assertOk();
    }
}
