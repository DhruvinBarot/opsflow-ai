<?php

namespace Tests\Feature;

use App\Enums\RequestStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\OperationalRequest;
use App\Models\RequestStatusHistory;
use App\Models\User;
use App\Services\RequestWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class RequestWorkflowServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_change_is_rolled_back_when_history_cannot_be_written(): void
    {
        $request = OperationalRequest::factory()->create();
        RequestStatusHistory::creating(fn () => throw new RuntimeException('history store unavailable'));

        try {
            app(RequestWorkflowService::class)->submit($request, $request->creator);
            $this->fail('Expected the history failure to propagate.');
        } catch (RuntimeException $e) {
            $this->assertSame('history store unavailable', $e->getMessage());
        }

        $fresh = $request->fresh();
        $this->assertSame(RequestStatus::Draft, $fresh->status);
        $this->assertNull($fresh->submitted_at);
    }

    public function test_transition_re_reads_current_state_instead_of_trusting_a_stale_model(): void
    {
        $manager = User::factory()->manager()->create();
        $request = OperationalRequest::factory()->underReview()->create();
        $staleCopy = OperationalRequest::findOrFail($request->id);

        $service = app(RequestWorkflowService::class);
        $service->reject($request, $manager, 'Not needed.');

        // A second reviewer still holding the old "under_review" copy must not be able to approve.
        $this->expectException(InvalidStatusTransitionException::class);
        $service->approve($staleCopy, $manager);
    }
}
