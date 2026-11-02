<?php

namespace Tests\Feature;

use App\Enums\AnalysisStatus;
use App\Enums\AuditAction;
use App\Enums\RequestStatus;
use App\Jobs\AnalyzeOperationalRequestJob;
use App\Models\AIAnalysis;
use App\Models\AuditLog;
use App\Models\OperationalRequest;
use App\Models\User;
use App\Services\AI\AIProviderException;
use App\Services\AI\AIRequestAnalysisService;
use App\Services\AI\AIRequestAnalyzerInterface;
use App\Services\AI\InvalidAnalysisOutputException;
use App\Services\RequestWorkflowService;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\Support\FakeRequestAnalyzer;
use Tests\TestCase;

class AIAnalysisPipelineTest extends TestCase
{
    use RefreshDatabase;

    private function useAnalyzer(FakeRequestAnalyzer $analyzer): FakeRequestAnalyzer
    {
        $this->app->instance(AIRequestAnalyzerInterface::class, $analyzer);

        return $analyzer;
    }

    private function submittedRequest(): OperationalRequest
    {
        return OperationalRequest::factory()->submitted()->create(['category' => 'general', 'priority' => 'low']);
    }

    public function test_submission_queues_analysis_on_the_ai_queue_without_running_it(): void
    {
        Queue::fake();
        $employee = User::factory()->create();
        $request = OperationalRequest::factory()->for($employee, 'creator')->create();
        Sanctum::actingAs($employee);

        $this->postJson("/api/requests/{$request->id}/submit")->assertOk()->assertJsonPath('data.status', 'submitted');

        Queue::assertPushedOn('ai', AnalyzeOperationalRequestJob::class, fn ($job) => $job->operationalRequest->is($request));
        $this->assertDatabaseHas('ai_analyses', ['operational_request_id' => $request->id, 'status' => 'pending', 'attempts' => 0]);
    }

    public function test_no_job_is_dispatched_when_the_submission_fails(): void
    {
        Queue::fake();
        $employee = User::factory()->create();
        $request = OperationalRequest::factory()->for($employee, 'creator')->submitted()->create();
        Sanctum::actingAs($employee);

        $this->postJson("/api/requests/{$request->id}/submit")->assertConflict();

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('ai_analyses', 0);
    }

    public function test_a_rolled_back_submission_neither_queues_a_job_nor_holds_the_unique_lock(): void
    {
        Queue::fake();
        $employee = User::factory()->create();
        $request = OperationalRequest::factory()->for($employee, 'creator')->create();

        try {
            DB::transaction(function () use ($request, $employee) {
                app(RequestWorkflowService::class)->submit($request, $employee);
                throw new RuntimeException('outer work failed');
            });
        } catch (RuntimeException) {
        }

        Queue::assertNothingPushed();
        $this->assertSame(RequestStatus::Draft, $request->fresh()->status);

        // A lock taken during the rolled-back attempt would silently swallow this real submission.
        app(RequestWorkflowService::class)->submit($request, $employee);
        Queue::assertPushed(AnalyzeOperationalRequestJob::class, 1);
    }

    public function test_job_persists_the_analysis_without_changing_the_request(): void
    {
        $this->useAnalyzer(new FakeRequestAnalyzer);
        $request = $this->submittedRequest();

        AnalyzeOperationalRequestJob::dispatchSync($request);

        $analysis = AIAnalysis::where('operational_request_id', $request->id)->sole();
        $this->assertSame(AnalysisStatus::Completed, $analysis->status);
        $this->assertSame('fake-provider', $analysis->provider);
        $this->assertSame('high', $analysis->priority->value);
        $this->assertSame('platform', $analysis->suggested_team->value);
        $this->assertNotNull($analysis->analyzed_at);

        // Advisory only: the human-set fields and workflow state are untouched.
        $fresh = $request->fresh();
        $this->assertSame(RequestStatus::Submitted, $fresh->status);
        $this->assertSame('general', $fresh->category->value);
        $this->assertSame('low', $fresh->priority->value);

        $this->assertDatabaseHas('audit_logs', ['entity_id' => $request->id, 'action' => 'ai_analysis_completed', 'actor_id' => null]);
    }

    public function test_running_the_job_twice_analyses_once(): void
    {
        $analyzer = $this->useAnalyzer(new FakeRequestAnalyzer);
        $request = $this->submittedRequest();

        AnalyzeOperationalRequestJob::dispatchSync($request);
        AnalyzeOperationalRequestJob::dispatchSync($request);

        $this->assertSame(1, $analyzer->calls);
        $this->assertDatabaseCount('ai_analyses', 1);
        $this->assertSame(1, AuditLog::where('action', AuditAction::AiAnalysisCompleted)->count());
    }

    public function test_duplicate_dispatches_are_collapsed_by_the_unique_lock(): void
    {
        Queue::fake();
        $request = $this->submittedRequest();

        AnalyzeOperationalRequestJob::dispatch($request);
        AnalyzeOperationalRequestJob::dispatch($request);

        Queue::assertPushed(AnalyzeOperationalRequestJob::class, 1);
    }

    public function test_transient_failure_is_rethrown_for_retry_while_attempts_remain(): void
    {
        $this->useAnalyzer(new FakeRequestAnalyzer([AIProviderException::timeout()]));
        $request = $this->submittedRequest();

        $this->assertThrows(
            fn () => app(AIRequestAnalysisService::class)->analyze($request, isFinalAttempt: false),
            AIProviderException::class,
        );

        $this->assertDatabaseHas('ai_analyses', ['operational_request_id' => $request->id, 'status' => 'pending', 'attempts' => 1, 'error_code' => 'provider_timeout']);
    }

    public function test_final_attempt_falls_back_to_the_local_analyzer(): void
    {
        $this->useAnalyzer(new FakeRequestAnalyzer([AIProviderException::timeout(), AIProviderException::timeout()]));
        $request = $this->submittedRequest();
        $service = app(AIRequestAnalysisService::class);

        $this->assertThrows(fn () => $service->analyze($request, isFinalAttempt: false), AIProviderException::class);
        $analysis = $service->analyze($request, isFinalAttempt: true);

        $this->assertSame(AnalysisStatus::Completed, $analysis->status);
        $this->assertSame('local', $analysis->provider);
        $this->assertSame('provider_timeout', $analysis->error_code);
        $this->assertSame(2, $analysis->attempts);
    }

    public function test_invalid_provider_output_falls_back_immediately(): void
    {
        $analyzer = $this->useAnalyzer(new FakeRequestAnalyzer([['category' => 'auto_approve'] + FakeRequestAnalyzer::validOutput()]));
        $request = $this->submittedRequest();

        $analysis = app(AIRequestAnalysisService::class)->analyze($request, isFinalAttempt: false);

        $this->assertSame(1, $analyzer->calls);
        $this->assertSame('local', $analysis->provider);
        $this->assertSame('invalid_output', $analysis->error_code);
    }

    public function test_a_job_that_exhausts_its_attempts_marks_the_analysis_failed_and_leaves_the_workflow_alone(): void
    {
        // Sync queue: the first failure is final, so failed() runs. An unexpected error has no fallback.
        $this->useAnalyzer(new FakeRequestAnalyzer([new RuntimeException('database went away')]));
        $request = $this->submittedRequest();

        $this->assertThrows(
            fn () => app(Dispatcher::class)->dispatch(new AnalyzeOperationalRequestJob($request)),
            RuntimeException::class,
        );

        $this->assertDatabaseHas('ai_analyses', ['operational_request_id' => $request->id, 'status' => 'failed', 'error_code' => 'internal_error']);
        $this->assertDatabaseHas('audit_logs', ['entity_id' => $request->id, 'action' => 'ai_analysis_failed']);
        $this->assertSame(RequestStatus::Submitted, $request->fresh()->status);

        // The request keeps moving through the workflow without AI input.
        Sanctum::actingAs(User::factory()->manager()->create());
        $this->postJson("/api/requests/{$request->id}/review")->assertOk();
    }

    public function test_failed_handler_maps_provider_errors_to_safe_codes(): void
    {
        $request = $this->submittedRequest();

        (new AnalyzeOperationalRequestJob($request))->failed(new InvalidAnalysisOutputException('raw model text with customer data'));

        $analysis = AIAnalysis::where('operational_request_id', $request->id)->sole();
        $this->assertSame('invalid_output', $analysis->error_code);
        $this->assertStringNotContainsString('customer data', json_encode(AuditLog::sole()->metadata));
    }

    public function test_job_retry_policy(): void
    {
        $job = new AnalyzeOperationalRequestJob($this->submittedRequest());

        $this->assertSame(3, $job->tries);
        $this->assertSame([10, 60], $job->backoff);
        $this->assertSame('ai', $job->queue);
        $this->assertLessThan(config('queue.connections.redis.retry_after'), $job->timeout);
    }
}
