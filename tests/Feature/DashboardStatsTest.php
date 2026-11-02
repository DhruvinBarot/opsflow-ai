<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\OperationalRequest;
use App\Models\User;
use App\Services\DashboardStatsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
    }

    public function test_operations_staff_see_counts_across_all_requests(): void
    {
        OperationalRequest::factory()->count(2)->create();
        OperationalRequest::factory()->submitted()->create(['priority' => 'critical']);
        OperationalRequest::factory()->underReview()->count(2)->create(['priority' => 'low']);
        OperationalRequest::factory()->inProgress()->create(['priority' => 'high']);
        OperationalRequest::factory()->status(RequestStatus::Completed)->create(['priority' => 'high']);
        Sanctum::actingAs(User::factory()->manager()->create());

        $this->getJson('/api/dashboard/stats')
            ->assertOk()
            ->assertJsonPath('data.scope', 'all_requests')
            ->assertJsonPath('data.total', 7)
            ->assertJsonPath('data.open', 4)
            ->assertJsonPath('data.pending_review', 3)
            ->assertJsonPath('data.pending_approval', 2)
            ->assertJsonPath('data.high_priority_open', 2)
            ->assertJsonPath('data.completed', 1)
            ->assertJsonPath('data.by_status.draft', 2);
    }

    public function test_employees_only_see_their_own_requests_and_never_each_others(): void
    {
        [$alice, $bob] = User::factory()->count(2)->create();
        OperationalRequest::factory()->for($alice, 'creator')->submitted()->count(3)->create();
        OperationalRequest::factory()->for($bob, 'creator')->create();
        OperationalRequest::factory()->underReview()->count(5)->create();

        Sanctum::actingAs($alice);
        $this->getJson('/api/dashboard/stats')
            ->assertJsonPath('data.scope', 'own_requests')
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.pending_review', 3);

        // Bob must not be served Alice's cached entry.
        Sanctum::actingAs($bob);
        $this->getJson('/api/dashboard/stats')->assertJsonPath('data.total', 1)->assertJsonPath('data.by_status.draft', 1);

        $stats = app(DashboardStatsService::class);
        $this->assertNotSame($stats->key($alice), $stats->key($bob));
        $this->assertSame($stats->key(User::factory()->manager()->create()), $stats->key(User::factory()->admin()->create()));
    }

    public function test_second_read_is_served_from_cache(): void
    {
        OperationalRequest::factory()->count(3)->create();
        $manager = User::factory()->manager()->create();
        $service = app(DashboardStatsService::class);

        $first = $service->forUser($manager);

        DB::enableQueryLog();
        $second = $service->forUser($manager);

        $this->assertSame($first, $second);
        $this->assertSame([], DB::getQueryLog(), 'A cache hit must not touch MySQL.');
        $this->assertTrue(Cache::has($service->key($manager)));
    }

    public function test_cache_is_invalidated_when_requests_change(): void
    {
        $employee = User::factory()->create();
        $manager = User::factory()->manager()->create();
        $request = OperationalRequest::factory()->for($employee, 'creator')->create();

        Sanctum::actingAs($manager);
        $this->getJson('/api/dashboard/stats')->assertJsonPath('data.pending_review', 0)->assertJsonPath('data.total', 1);

        Sanctum::actingAs($employee);
        $this->postJson("/api/requests/{$request->id}/submit")->assertOk();
        $this->postJson('/api/requests', [
            'title' => 'Second request here',
            'description' => 'Another thing that needs doing.',
            'category' => 'general',
        ])->assertCreated();

        Sanctum::actingAs($manager);
        $this->getJson('/api/dashboard/stats')->assertJsonPath('data.pending_review', 1)->assertJsonPath('data.total', 2);
    }

    public function test_stats_are_still_served_when_the_cache_is_unavailable(): void
    {
        OperationalRequest::factory()->count(2)->create();
        $admin = User::factory()->admin()->create();
        Cache::shouldReceive('get')->andThrow(new RuntimeException('Connection refused [tcp://redis:6379]'));
        Cache::shouldReceive('forever')->andThrow(new RuntimeException('Connection refused [tcp://redis:6379]'));

        $service = app(DashboardStatsService::class);

        $this->assertSame(2, $service->forUser($admin)['total']);
        $service->flush(); // must not throw either
    }

    public function test_rolled_back_changes_do_not_invalidate_the_cache(): void
    {
        $manager = User::factory()->manager()->create();
        $service = app(DashboardStatsService::class);
        $key = $service->key($manager);

        try {
            DB::transaction(function () {
                OperationalRequest::factory()->create();
                throw new RuntimeException('abort');
            });
        } catch (RuntimeException) {
        }

        $this->assertSame($key, $service->key($manager));
    }

    public function test_stats_require_authentication(): void
    {
        $this->getJson('/api/dashboard/stats')->assertUnauthorized();
    }
}
