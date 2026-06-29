<?php

namespace Tests\Feature\Web;

use App\Livewire\Auth\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Modules\Identity\Models\Person;
use Tests\TestCase;

/**
 * Member web scaffold (E1.11) — web-guard session login + the auth-gated Today screen.
 * Page-render assertions use withoutVite() since assets only build in Docker/CI.
 */
class MemberWebTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_valid_credentials_log_in_and_redirect_to_today(): void
    {
        $person = Person::factory()->create(); // factory password = 'password'

        Livewire::test(Login::class)
            ->set('email', $person->email)
            ->set('password', 'password')
            ->call('authenticate')
            ->assertHasNoErrors()
            ->assertRedirect(route('today'));

        $this->assertAuthenticatedAs($person, 'web');
    }

    public function test_invalid_credentials_are_rejected(): void
    {
        $person = Person::factory()->create();

        Livewire::test(Login::class)
            ->set('email', $person->email)
            ->set('password', 'wrong-password')
            ->call('authenticate')
            ->assertHasErrors('email');

        $this->assertGuest('web');
    }

    public function test_today_renders_for_authenticated_member(): void
    {
        $person = Person::factory()->create(['display_name' => 'Sara Tester']);

        $this->actingAs($person, 'web')
            ->withoutVite()
            ->get('/')
            ->assertOk()
            ->assertSee('Sara Tester')
            ->assertSee('Level');
    }
}
