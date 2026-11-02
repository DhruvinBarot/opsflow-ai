<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\OperationalRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_request_moves_through_the_full_lifecycle_and_records_history(): void
    {
        $employee = User::factory()->create();
        $manager = User::factory()->manager()->create();
        $request = OperationalRequest::factory()->for($employee, 'creator')->create();

        Sanctum::actingAs($employee);
        $this->postJson("/api/requests/{$request->id}/submit")
            ->assertOk()
            ->assertJsonPath('data.status', 'submitted');

        Sanctum::actingAs($manager);
        $this->postJson("/api/requests/{$request->id}/review")->assertJsonPath('data.status', 'under_review');
        $this->postJson("/api/requests/{$request->id}/approve", ['reason' => 'Within policy.'])->assertJsonPath('data.status', 'approved');
        $this->postJson("/api/requests/{$request->id}/start")->assertJsonPath('data.status', 'in_progress');
        $this->postJson("/api/requests/{$request->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.status', 'completed')
            ->assertJsonPath('data.allowed_transitions', []);

        $request->refresh();
        $this->assertNotNull($request->submitted_at);
        $this->assertNotNull($request->completed_at);

        Sanctum::actingAs($employee);
        $history = $this->getJson("/api/requests/{$request->id}/history")->assertOk()->json('data');

        $this->assertSame(
            [['draft', 'submitted'], ['submitted', 'under_review'], ['under_review', 'approved'], ['approved', 'in_progress'], ['in_progress', 'completed']],
            array_map(fn (array $entry) => [$entry['from_status'], $entry['to_status']], $history),
        );
        $this->assertSame($employee->id, $history[0]['changed_by']['id']);
        $this->assertSame($manager->id, $history[2]['changed_by']['id']);
        $this->assertSame('Within policy.', $history[2]['reason']);
    }

    public function test_invalid_transition_returns_409_and_changes_nothing(): void
    {
        $request = OperationalRequest::factory()->submitted()->create();
        Sanctum::actingAs(User::factory()->manager()->create());

        $this->postJson("/api/requests/{$request->id}/approve")
            ->assertConflict()
            ->assertExactJson([
                'message' => 'A request cannot move from submitted to approved.',
                'error' => 'invalid_status_transition',
                'current_status' => 'submitted',
                'requested_status' => 'approved',
                'allowed_transitions' => ['under_review'],
            ]);

        $this->assertSame(RequestStatus::Submitted, $request->fresh()->status);
        $this->assertDatabaseCount('request_status_histories', 0);
    }

    public function test_completed_request_cannot_be_reopened_or_resubmitted(): void
    {
        $employee = User::factory()->create();
        $request = OperationalRequest::factory()->for($employee, 'creator')->status(RequestStatus::Completed)->create();

        Sanctum::actingAs($employee);
        $this->postJson("/api/requests/{$request->id}/submit")->assertConflict();

        Sanctum::actingAs(User::factory()->manager()->create());
        $this->postJson("/api/requests/{$request->id}/start")->assertConflict();
    }

    public function test_reject_requires_a_reason(): void
    {
        $request = OperationalRequest::factory()->underReview()->create();
        Sanctum::actingAs(User::factory()->manager()->create());

        $this->postJson("/api/requests/{$request->id}/reject")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');

        $this->postJson("/api/requests/{$request->id}/reject", ['reason' => 'Duplicate of an existing ticket.'])
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertDatabaseHas('request_status_histories', [
            'operational_request_id' => $request->id,
            'to_status' => 'rejected',
            'reason' => 'Duplicate of an existing ticket.',
        ]);
    }

    public function test_employee_cannot_perform_operations_transitions(): void
    {
        $employee = User::factory()->create();
        $request = OperationalRequest::factory()->for($employee, 'creator')->underReview()->create();
        Sanctum::actingAs($employee);

        $this->postJson("/api/requests/{$request->id}/approve")->assertForbidden();
        $this->postJson("/api/requests/{$request->id}/reject", ['reason' => 'self-reject'])->assertForbidden();
        $this->postJson("/api/requests/{$request->id}/start")->assertForbidden();

        $this->assertSame(RequestStatus::UnderReview, $request->fresh()->status);
    }

    public function test_only_the_creator_can_submit(): void
    {
        $request = OperationalRequest::factory()->create();

        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/requests/{$request->id}/submit")->assertForbidden();

        Sanctum::actingAs(User::factory()->manager()->create());
        $this->postJson("/api/requests/{$request->id}/submit")->assertForbidden();
    }

    public function test_managers_cannot_review_their_own_requests(): void
    {
        $manager = User::factory()->manager()->create();
        $request = OperationalRequest::factory()->for($manager, 'creator')->submitted()->create();
        Sanctum::actingAs($manager);

        $this->postJson("/api/requests/{$request->id}/review")
            ->assertForbidden()
            ->assertJsonPath('message', 'You cannot review a request you created.');
    }

    public function test_admin_can_review_and_approve(): void
    {
        $request = OperationalRequest::factory()->underReview()->create();
        Sanctum::actingAs(User::factory()->admin()->create());

        $this->postJson("/api/requests/{$request->id}/approve")->assertOk()->assertJsonPath('data.status', 'approved');
    }
}
