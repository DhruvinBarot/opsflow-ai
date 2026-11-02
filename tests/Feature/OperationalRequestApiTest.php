<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Models\OperationalRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OperationalRequestApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_creates_a_draft_request(): void
    {
        $employee = User::factory()->create();
        Sanctum::actingAs($employee);

        $response = $this->postJson('/api/requests', [
            'title' => 'Production API latency above 2s',
            'description' => 'p95 latency on /checkout has been above 2 seconds since 09:00.',
            'category' => 'incident',
            'priority' => 'high',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.creator.id', $employee->id)
            ->assertJsonPath('data.allowed_transitions', ['submitted']);

        $this->assertDatabaseHas('operational_requests', [
            'title' => 'Production API latency above 2s',
            'created_by' => $employee->id,
            'status' => 'draft',
        ]);
    }

    public function test_priority_defaults_to_medium(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/requests', [
            'title' => 'Order replacement laptops',
            'description' => 'Two design laptops have failing batteries.',
            'category' => 'general',
        ])->assertCreated()->assertJsonPath('data.priority', 'medium');
    }

    public function test_creation_validates_input(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/requests', [
            'title' => 'no',
            'category' => 'not-a-category',
            'priority' => 'urgent',
            'due_at' => '2001-01-01',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'description', 'category', 'priority', 'due_at']);
    }

    public function test_protected_fields_cannot_be_mass_assigned(): void
    {
        $employee = User::factory()->create();
        $someoneElse = User::factory()->admin()->create();
        Sanctum::actingAs($employee);

        $id = $this->postJson('/api/requests', [
            'title' => 'Grant access to finance DB',
            'description' => 'Read-only access needed for month-end reporting.',
            'category' => 'access',
            'status' => 'approved',
            'created_by' => $someoneElse->id,
            'assigned_to' => $someoneElse->id,
        ])->assertCreated()->json('data.id');

        $request = OperationalRequest::findOrFail($id);
        $this->assertSame(RequestStatus::Draft, $request->status);
        $this->assertSame($employee->id, $request->created_by);
        $this->assertNull($request->assigned_to);
    }

    public function test_employee_only_lists_their_own_requests(): void
    {
        $employee = User::factory()->create();
        OperationalRequest::factory()->count(2)->for($employee, 'creator')->create();
        OperationalRequest::factory()->count(3)->create();
        Sanctum::actingAs($employee);

        $this->getJson('/api/requests')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_manager_lists_all_requests_with_filters_and_pagination(): void
    {
        OperationalRequest::factory()->count(3)->underReview()->create(['priority' => 'high']);
        OperationalRequest::factory()->count(2)->underReview()->create(['priority' => 'low']);
        OperationalRequest::factory()->count(4)->create();
        Sanctum::actingAs(User::factory()->manager()->create());

        $this->getJson('/api/requests')->assertOk()->assertJsonPath('meta.total', 9);

        $this->getJson('/api/requests?status=under_review&priority=high&per_page=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 3)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('data.0.status', 'under_review');
    }

    public function test_list_filters_are_validated(): void
    {
        Sanctum::actingAs(User::factory()->manager()->create());

        $this->getJson('/api/requests?status=bogus&per_page=500')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'per_page']);
    }

    public function test_employee_cannot_view_another_employees_request(): void
    {
        $request = OperationalRequest::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/requests/{$request->id}")->assertForbidden();
        $this->getJson("/api/requests/{$request->id}/history")->assertForbidden();
    }

    public function test_missing_request_returns_a_clean_404(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/requests/999999')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Resource not found.', 'error' => 'not_found']);
    }

    public function test_owner_can_edit_a_draft_but_not_after_submission(): void
    {
        $employee = User::factory()->create();
        $draft = OperationalRequest::factory()->for($employee, 'creator')->create();
        $submitted = OperationalRequest::factory()->for($employee, 'creator')->submitted()->create();
        Sanctum::actingAs($employee);

        $this->patchJson("/api/requests/{$draft->id}", ['title' => 'Updated title here'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Updated title here');

        $this->patchJson("/api/requests/{$submitted->id}", ['title' => 'Too late to edit'])
            ->assertForbidden()
            ->assertJsonPath('message', 'Only draft requests can be edited by their creator.');
    }

    public function test_manager_can_reprioritise_an_open_request(): void
    {
        $request = OperationalRequest::factory()->underReview()->create(['priority' => 'low']);
        Sanctum::actingAs(User::factory()->manager()->create());

        $this->patchJson("/api/requests/{$request->id}", ['priority' => 'critical'])
            ->assertOk()
            ->assertJsonPath('data.priority', 'critical');
    }

    public function test_status_cannot_be_changed_through_update(): void
    {
        $request = OperationalRequest::factory()->underReview()->create();
        Sanctum::actingAs(User::factory()->manager()->create());

        $this->patchJson("/api/requests/{$request->id}", ['status' => 'completed'])->assertOk();

        $this->assertSame(RequestStatus::UnderReview, $request->fresh()->status);
    }
}
