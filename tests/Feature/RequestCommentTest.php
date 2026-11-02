<?php

namespace Tests\Feature;

use App\Models\OperationalRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RequestCommentTest extends TestCase
{
    use RefreshDatabase;

    public function test_creator_and_operations_staff_can_discuss_a_request(): void
    {
        $employee = User::factory()->create();
        $manager = User::factory()->manager()->create();
        $request = OperationalRequest::factory()->for($employee, 'creator')->submitted()->create();

        Sanctum::actingAs($employee);
        $this->postJson("/api/requests/{$request->id}/comments", ['body' => 'Impact is limited to EU customers.'])
            ->assertCreated()
            ->assertJsonPath('data.author.id', $employee->id)
            ->assertJsonPath('data.body', 'Impact is limited to EU customers.');

        Sanctum::actingAs($manager);
        $this->postJson("/api/requests/{$request->id}/comments", ['body' => 'Thanks, routing to platform.'])->assertCreated();

        $this->getJson("/api/requests/{$request->id}/comments")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.author.id', $employee->id)
            ->assertJsonPath('data.1.author.role', 'manager');
    }

    public function test_users_who_cannot_see_the_request_cannot_read_or_comment(): void
    {
        $request = OperationalRequest::factory()->submitted()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/requests/{$request->id}/comments")->assertForbidden();
        $this->postJson("/api/requests/{$request->id}/comments", ['body' => 'Sneaky'])->assertForbidden();
        $this->assertDatabaseCount('request_comments', 0);
    }

    public function test_comment_body_is_validated(): void
    {
        $employee = User::factory()->create();
        $request = OperationalRequest::factory()->for($employee, 'creator')->create();
        Sanctum::actingAs($employee);

        $this->postJson("/api/requests/{$request->id}/comments", [])->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->postJson("/api/requests/{$request->id}/comments", ['body' => str_repeat('a', 5001)])->assertJsonValidationErrors('body');
        $this->postJson("/api/requests/{$request->id}/comments", ['body' => ['not', 'a', 'string']])->assertJsonValidationErrors('body');
    }

    public function test_author_cannot_be_spoofed(): void
    {
        $employee = User::factory()->create();
        $request = OperationalRequest::factory()->for($employee, 'creator')->create();
        $admin = User::factory()->admin()->create();
        Sanctum::actingAs($employee);

        $this->postJson("/api/requests/{$request->id}/comments", ['body' => 'Hello there', 'user_id' => $admin->id])
            ->assertCreated()
            ->assertJsonPath('data.author.id', $employee->id);
    }

    public function test_adding_a_comment_is_audited_without_copying_the_body(): void
    {
        $employee = User::factory()->create();
        $request = OperationalRequest::factory()->for($employee, 'creator')->create();
        Sanctum::actingAs($employee);

        $commentId = $this->postJson("/api/requests/{$request->id}/comments", ['body' => 'Customer phone is 555-0100'])->json('data.id');

        $this->assertDatabaseHas('audit_logs', ['action' => 'comment_added', 'actor_id' => $employee->id, 'entity_id' => $request->id]);
        $this->assertSame(['comment_id' => $commentId], $request->auditLogs()->sole()->metadata);
    }
}
