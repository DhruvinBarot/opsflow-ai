<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\OperationalRequest;
use App\Models\User;
use App\Services\AI\AIRequestAnalysisService;
use App\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_lifecycle_produces_an_ordered_audit_trail_with_actors(): void
    {
        Queue::fake();
        $employee = User::factory()->create();
        $manager = User::factory()->manager()->create();

        Sanctum::actingAs($employee);
        $id = $this->postJson('/api/requests', [
            'title' => 'Rotate TLS certificate',
            'description' => 'The api gateway certificate expires on Friday.',
            'category' => 'infrastructure',
        ])->json('data.id');
        $this->patchJson("/api/requests/{$id}", ['priority' => 'high'])->assertOk();
        $this->postJson("/api/requests/{$id}/submit")->assertOk();

        Sanctum::actingAs($manager);
        $this->postJson("/api/requests/{$id}/review")->assertOk();
        $this->postJson("/api/requests/{$id}/assign", ['assignee_id' => $manager->id])->assertOk();
        $this->postJson("/api/requests/{$id}/approve", ['reason' => 'Routine renewal.'])->assertOk();
        $this->postJson("/api/requests/{$id}/start")->assertOk();
        $this->postJson("/api/requests/{$id}/complete")->assertOk();

        $logs = AuditLog::where('entity_id', $id)->orderBy('id')->get();

        $this->assertSame(
            ['request_created', 'request_updated', 'request_submitted', 'status_changed', 'request_assigned', 'request_approved', 'status_changed', 'request_completed'],
            $logs->pluck('action')->map->value->all(),
        );
        $this->assertSame([$employee->id, $employee->id, $employee->id], $logs->take(3)->pluck('actor_id')->all());
        $this->assertSame($manager->id, $logs[5]->actor_id);
        $this->assertSame(['from' => 'under_review', 'to' => 'approved', 'reason' => 'Routine renewal.'], $logs[5]->metadata);
        $this->assertSame(['fields' => ['priority'], 'priority_from' => 'medium', 'priority_to' => 'high'], $logs[1]->metadata);
        $this->assertSame($manager->id, $logs[4]->metadata['assignee_id']);
    }

    public function test_rejected_transitions_leave_no_audit_record(): void
    {
        $request = OperationalRequest::factory()->submitted()->create();
        Sanctum::actingAs(User::factory()->manager()->create());

        $this->postJson("/api/requests/{$request->id}/approve")->assertConflict();

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_rejection_is_audited_with_its_reason(): void
    {
        $request = OperationalRequest::factory()->underReview()->create();
        $manager = User::factory()->manager()->create();
        Sanctum::actingAs($manager);

        $this->postJson("/api/requests/{$request->id}/reject", ['reason' => 'Duplicate request.'])->assertOk();

        $this->assertDatabaseHas('audit_logs', ['entity_id' => $request->id, 'action' => 'request_rejected', 'actor_id' => $manager->id]);
    }

    public function test_metadata_is_sanitised(): void
    {
        $request = OperationalRequest::factory()->create();

        $log = app(AuditService::class)->record(AuditAction::StatusChanged, $request, null, [
            'password' => 'hunter2',
            'api_token' => 'abc',
            'nested' => ['client_secret' => 'xyz', 'kept' => 'yes'],
            'note' => str_repeat('n', 800),
        ]);

        $this->assertSame(['nested' => ['kept' => 'yes'], 'note' => str_repeat('n', 500).'...'], $log->fresh()->metadata);
    }

    public function test_activity_feed_is_operations_only(): void
    {
        Queue::fake();
        $employee = User::factory()->create();
        $request = OperationalRequest::factory()->for($employee, 'creator')->create();
        Sanctum::actingAs($employee);
        $this->postJson("/api/requests/{$request->id}/submit")->assertOk();

        $this->getJson("/api/requests/{$request->id}/activity")->assertForbidden();

        Sanctum::actingAs(User::factory()->manager()->create());
        $this->getJson("/api/requests/{$request->id}/activity")
            ->assertOk()
            ->assertJsonPath('data.0.action', 'request_submitted')
            ->assertJsonPath('data.0.actor.id', $employee->id)
            ->assertJsonPath('data.0.metadata.to', 'submitted');
    }

    public function test_system_actions_are_attributed_to_the_system(): void
    {
        $request = OperationalRequest::factory()->submitted()->create();
        app(AIRequestAnalysisService::class)->analyze($request);
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->getJson("/api/requests/{$request->id}/activity")
            ->assertOk()
            ->assertJsonPath('data.0.action', 'ai_analysis_completed')
            ->assertJsonPath('data.0.actor', ['type' => 'system'])
            ->assertJsonPath('data.0.metadata.provider', 'local');
    }
}
