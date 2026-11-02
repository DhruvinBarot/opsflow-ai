<?php

namespace Tests\Feature;

use App\Models\OperationalRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_landing_page_is_public_and_makes_no_fabricated_claims(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Operational workflows.')
            ->assertSee('Product preview with example data.')
            ->assertSee(route('login'), false)
            ->assertDontSee('testimonial', false);
    }

    public function test_github_link_only_appears_when_a_repository_is_configured(): void
    {
        config(['app.repository_url' => null]);
        $this->get('/')->assertDontSee('View on GitHub');

        config(['app.repository_url' => 'https://github.com/example/opsflow-ai']);
        $this->get('/')->assertSee('https://github.com/example/opsflow-ai', false);
    }

    public function test_guests_are_redirected_to_sign_in(): void
    {
        $this->get('/app')->assertRedirect(route('login'));
        $this->get('/app/review')->assertRedirect(route('login'));
    }

    public function test_user_signs_in_with_a_session_and_out_again(): void
    {
        $user = User::factory()->create(['email' => 'eli@example.com']);

        $this->post('/login', ['email' => 'eli@example.com', 'password' => 'password'])
            ->assertRedirect(route('app.overview'));
        $this->assertAuthenticatedAs($user);

        $this->post('/logout')->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_failed_sign_in_shows_a_generic_error(): void
    {
        User::factory()->create(['email' => 'eli@example.com']);

        $this->from('/login')->post('/login', ['email' => 'eli@example.com', 'password' => 'wrong'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['email' => __('auth.failed')]);
        $this->assertGuest();
    }

    public function test_signed_in_users_skip_the_login_page(): void
    {
        $this->actingAs(User::factory()->create())->get('/login')->assertRedirect(route('app.overview'));
    }

    public function test_employee_pages_and_navigation_hide_operations_areas(): void
    {
        $employee = User::factory()->create();

        $this->actingAs($employee)->get('/app')
            ->assertOk()
            ->assertSee('My requests')
            ->assertDontSee('Review queue')
            ->assertDontSee(route('app.admin'), false);

        $this->get('/app/requests')->assertOk();
        $this->get('/app/requests/new')->assertOk();
        $this->get('/app/review')->assertForbidden();
        $this->get('/app/all-requests')->assertForbidden();
        $this->get('/app/admin')->assertForbidden();
    }

    public function test_manager_reaches_operations_pages_but_not_admin(): void
    {
        $this->actingAs(User::factory()->manager()->create());

        $this->get('/app/review')->assertOk()->assertSee('Review queue');
        $this->get('/app/all-requests')->assertOk();
        $this->get('/app/admin')->assertForbidden();
    }

    public function test_admin_reaches_the_operations_console(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get('/app/admin')->assertOk()->assertSee('Operations console');
    }

    public function test_request_pages_apply_the_request_policy(): void
    {
        $owner = User::factory()->create();
        $draft = OperationalRequest::factory()->for($owner, 'creator')->create();
        $submitted = OperationalRequest::factory()->for($owner, 'creator')->submitted()->create();

        $this->actingAs($owner)->get("/app/requests/{$draft->id}")->assertOk();
        $this->get("/app/requests/{$draft->id}/edit")->assertOk();
        $this->get("/app/requests/{$submitted->id}/edit")->assertForbidden();

        $this->actingAs(User::factory()->create())->get("/app/requests/{$draft->id}")->assertForbidden();
        $this->get('/app/requests/999999')->assertNotFound();
    }

    public function test_the_browser_session_authenticates_api_calls(): void
    {
        $user = User::factory()->create();
        OperationalRequest::factory()->for($user, 'creator')->create();

        $this->actingAs($user, 'web')
            ->getJson('/api/requests', ['Referer' => config('app.url')])
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_token_logout_rejects_session_callers_instead_of_crashing(): void
    {
        $this->actingAs(User::factory()->create(), 'web')
            ->postJson('/api/auth/logout', [], ['Referer' => config('app.url')])
            ->assertStatus(400);
    }
}
