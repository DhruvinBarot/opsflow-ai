<?php

namespace Tests\Feature;

use App\Models\OperationalRequest;
use App\Models\User;
use App\Services\AI\AIRequestAnalysisService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AIAnalysisApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_analysis_is_reported_while_the_job_is_queued(): void
    {
        Queue::fake();
        $employee = User::factory()->create();
        $request = OperationalRequest::factory()->for($employee, 'creator')->create();
        Sanctum::actingAs($employee);

        $this->postJson("/api/requests/{$request->id}/submit")->assertOk();

        $this->getJson("/api/requests/{$request->id}/ai-analysis")
            ->assertOk()
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.recommendation', null)
            ->assertJsonMissingPath('data.error');
    }

    public function test_completed_analysis_returns_the_validated_recommendation_only(): void
    {
        $employee = User::factory()->create();
        $request = OperationalRequest::factory()->for($employee, 'creator')->create([
            'title' => 'Checkout error rate spiking',
            'description' => 'Since the deploy we see 5xx errors in production.',
            'category' => 'general',
        ]);
        Sanctum::actingAs($employee);

        // Sync queue in tests: the local analyzer runs right after the submit commits.
        $this->postJson("/api/requests/{$request->id}/submit")->assertOk();

        $response = $this->getJson("/api/requests/{$request->id}/ai-analysis")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.advisory', true)
            ->assertJsonPath('data.provider', 'local')
            ->assertJsonPath('data.recommendation.category', 'incident')
            ->assertJsonPath('data.recommendation.suggested_team', 'platform')
            ->assertJsonPath('data.recommendation.risk_level', 'high');

        $this->assertSame(
            ['status', 'advisory', 'provider', 'recommendation', 'attempts', 'analyzed_at', 'updated_at'],
            array_keys($response->json('data')),
        );
        $this->getJson("/api/requests/{$request->id}")->assertJsonPath('data.ai_analysis.status', 'completed');
    }

    public function test_failed_analysis_returns_a_safe_error(): void
    {
        Queue::fake();
        $manager = User::factory()->manager()->create();
        $request = OperationalRequest::factory()->submitted()->create();
        app(AIRequestAnalysisService::class)->markFailed($request, new \RuntimeException('SQLSTATE[HY000] secret internals'));
        Sanctum::actingAs($manager);

        $response = $this->getJson("/api/requests/{$request->id}/ai-analysis")
            ->assertOk()
            ->assertJsonPath('data.status', 'failed')
            ->assertJsonPath('data.error.code', 'internal_error')
            ->assertJsonPath('data.recommendation', null);

        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
    }

    public function test_draft_request_has_no_analysis(): void
    {
        $employee = User::factory()->create();
        $request = OperationalRequest::factory()->for($employee, 'creator')->create();
        Sanctum::actingAs($employee);

        $this->getJson("/api/requests/{$request->id}/ai-analysis")
            ->assertNotFound()
            ->assertJsonPath('error', 'analysis_not_found');
    }

    public function test_other_employees_cannot_read_the_analysis(): void
    {
        $request = OperationalRequest::factory()->submitted()->create();
        app(AIRequestAnalysisService::class)->analyze($request);
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/requests/{$request->id}/ai-analysis")->assertForbidden();
    }
}
