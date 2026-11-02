<?php

namespace Tests\Feature;

use App\Models\OperationalRequest;
use App\Models\User;
use App\Services\AI\AIRequestAnalysisService;
use App\Services\DashboardStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardApiExtensionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_can_be_searched_by_title_with_wildcards_treated_literally(): void
    {
        OperationalRequest::factory()->underReview()->create(['title' => 'Rotate TLS certificate']);
        OperationalRequest::factory()->underReview()->create(['title' => 'Refund 100% of invoice']);
        OperationalRequest::factory()->underReview()->create(['title' => 'Grant VPN access']);
        Sanctum::actingAs(User::factory()->manager()->create());

        $this->getJson('/api/requests?search=tls')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.title', 'Rotate TLS certificate');
        $this->getJson('/api/requests?search='.urlencode('100%'))->assertJsonPath('meta.total', 1);
        $this->getJson('/api/requests?search=%25')->assertJsonPath('meta.total', 1);
    }

    public function test_list_can_be_sorted_by_priority_and_due_date(): void
    {
        OperationalRequest::factory()->create(['priority' => 'low', 'due_at' => now()->addDays(2)]);
        OperationalRequest::factory()->create(['priority' => 'critical', 'due_at' => null]);
        OperationalRequest::factory()->create(['priority' => 'high', 'due_at' => now()->addDay()]);
        Sanctum::actingAs(User::factory()->manager()->create());

        $this->assertSame(['critical', 'high', 'low'], collect($this->getJson('/api/requests?sort=priority')->json('data'))->pluck('priority')->all());
        $this->assertSame(['high', 'low', 'critical'], collect($this->getJson('/api/requests?sort=due_soon')->json('data'))->pluck('priority')->all());
        $this->getJson('/api/requests?sort=drop_table')->assertUnprocessable()->assertJsonValidationErrors('sort');
    }

    public function test_list_embeds_ai_analysis_without_extra_queries_per_row(): void
    {
        Queue::fake();
        $requests = OperationalRequest::factory()->submitted()->count(3)->create();
        $requests->each(fn ($request) => app(AIRequestAnalysisService::class)->analyze($request));
        Sanctum::actingAs(User::factory()->manager()->create());

        $this->getJson('/api/requests')
            ->assertOk()
            ->assertJsonPath('data.0.ai_analysis.status', 'completed')
            ->assertJsonPath('data.2.ai_analysis.recommendation.suggested_team', fn ($team) => is_string($team));
    }

    public function test_activity_feed_is_for_operations_staff_and_names_the_request(): void
    {
        Queue::fake();
        $employee = User::factory()->create();
        Sanctum::actingAs($employee);
        $id = $this->postJson('/api/requests', ['title' => 'Grant VPN access', 'description' => 'Contractor starts Monday.', 'category' => 'access'])->json('data.id');

        $this->getJson('/api/activity')->assertForbidden();

        Sanctum::actingAs(User::factory()->manager()->create());
        $this->getJson('/api/activity?per_page=5')
            ->assertOk()
            ->assertJsonPath('data.0.action', 'request_created')
            ->assertJsonPath('data.0.request', ['id' => $id, 'title' => 'Grant VPN access'])
            ->assertJsonPath('data.0.actor.id', $employee->id);

        $this->getJson('/api/activity?per_page=500')->assertUnprocessable();
    }

    public function test_assignable_users_lists_only_operations_staff_for_operations_staff(): void
    {
        $manager = User::factory()->manager()->create(['name' => 'Morgan']);
        $admin = User::factory()->admin()->create(['name' => 'Avery']);
        User::factory()->create(['name' => 'Eli']);

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/users/assignable')->assertForbidden();

        Sanctum::actingAs($manager);
        $this->assertSame(['Avery', 'Morgan'], collect($this->getJson('/api/users/assignable')->json('data'))->pluck('name')->all());
        $this->assertNotNull($admin);
    }

    public function test_stats_report_ai_pipeline_counts_scoped_to_the_viewer(): void
    {
        Queue::fake();
        $employee = User::factory()->create();
        $own = OperationalRequest::factory()->for($employee, 'creator')->submitted()->create();
        $other = OperationalRequest::factory()->submitted()->create();
        $service = app(AIRequestAnalysisService::class);
        $service->analyze($own);
        $service->markFailed($other, null);

        Sanctum::actingAs($employee);
        $this->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.ai_analysis.completed', 1)
            ->assertJsonPath('data.ai_analysis.failed', 0);

        Sanctum::actingAs(User::factory()->manager()->create());
        $this->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.ai_analysis.completed', 1)
            ->assertJsonPath('data.ai_analysis.failed', 1)
            ->assertJsonPath('data.ai_analysis.fallbacks', 0);
    }

    public function test_finishing_an_analysis_invalidates_cached_stats(): void
    {
        Queue::fake();
        $manager = User::factory()->manager()->create();
        $request = OperationalRequest::factory()->submitted()->create();
        $stats = app(DashboardStatsService::class);

        $this->assertSame(0, $stats->forUser($manager)['ai_analysis']['completed']);

        app(AIRequestAnalysisService::class)->analyze($request);

        $this->assertSame(1, $stats->forUser($manager)['ai_analysis']['completed']);
    }
}
