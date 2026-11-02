<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\OperationalRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RequestAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_manager_assigns_and_reassigns_a_request(): void
    {
        $manager = User::factory()->manager()->create();
        $first = User::factory()->manager()->create();
        $second = User::factory()->admin()->create();
        $request = OperationalRequest::factory()->underReview()->create();
        Sanctum::actingAs($manager);

        $this->postJson("/api/requests/{$request->id}/assign", ['assignee_id' => $first->id, 'note' => 'Platform on-call'])
            ->assertOk()
            ->assertJsonPath('data.assignee.id', $first->id);

        $this->postJson("/api/requests/{$request->id}/assign", ['assignee_id' => $second->id])
            ->assertOk()
            ->assertJsonPath('data.assignee.id', $second->id)
            ->assertJsonCount(2, 'data.assignments')
            ->assertJsonPath('data.assignments.0.assignee.id', $second->id)
            ->assertJsonPath('data.assignments.1.note', 'Platform on-call')
            ->assertJsonPath('data.assignments.1.assigned_by.id', $manager->id);

        $this->assertSame($second->id, $request->fresh()->assigned_to);
    }

    public function test_assignee_must_be_operations_staff(): void
    {
        $request = OperationalRequest::factory()->submitted()->create();
        Sanctum::actingAs(User::factory()->manager()->create());

        $this->postJson("/api/requests/{$request->id}/assign", ['assignee_id' => User::factory()->create()->id])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['assignee_id' => 'The assignee must be an existing manager or admin.']);

        $this->postJson("/api/requests/{$request->id}/assign", ['assignee_id' => 999999])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('assignee_id');
    }

    public function test_employee_cannot_assign_even_with_invalid_payload(): void
    {
        $employee = User::factory()->create();
        $request = OperationalRequest::factory()->for($employee, 'creator')->submitted()->create();
        Sanctum::actingAs($employee);

        $this->postJson("/api/requests/{$request->id}/assign", [])->assertForbidden();
    }

    public function test_drafts_and_closed_requests_cannot_be_assigned(): void
    {
        $assignee = User::factory()->manager()->create();
        Sanctum::actingAs(User::factory()->manager()->create());

        $draft = OperationalRequest::factory()->create();
        $this->postJson("/api/requests/{$draft->id}/assign", ['assignee_id' => $assignee->id])
            ->assertConflict()
            ->assertJsonPath('error', 'request_not_assignable');

        $rejected = OperationalRequest::factory()->status(RequestStatus::Rejected)->create();
        $this->postJson("/api/requests/{$rejected->id}/assign", ['assignee_id' => $assignee->id])->assertConflict();

        $this->assertDatabaseCount('request_assignments', 0);
    }

    public function test_requests_can_be_filtered_by_assignee(): void
    {
        $manager = User::factory()->manager()->create();
        $request = OperationalRequest::factory()->submitted()->create(['assigned_to' => $manager->id]);
        OperationalRequest::factory()->submitted()->create();
        Sanctum::actingAs($manager);

        $this->getJson("/api/requests?assigned_to={$manager->id}")
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $request->id);
    }
}
