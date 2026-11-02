<?php

namespace Database\Seeders;

use App\Enums\RequestStatus;
use App\Models\OperationalRequest;
use App\Models\User;
use App\Services\RequestAssignmentService;
use App\Services\RequestWorkflowService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Local development data only. Every seeded account uses the password "password".
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(RequestWorkflowService $workflow, RequestAssignmentService $assignments): void
    {
        $admin = User::factory()->admin()->create(['name' => 'Avery Admin', 'email' => 'admin@opsflow.test']);
        $manager = User::factory()->manager()->create(['name' => 'Morgan Manager', 'email' => 'manager@opsflow.test']);
        $employee = User::factory()->create(['name' => 'Eli Employee', 'email' => 'employee@opsflow.test']);

        $managers = User::factory()->manager()->count(2)->create()->push($manager);
        $employees = User::factory()->count(6)->create()->push($employee);

        // Walk each request through the real workflow so status history is consistent.
        $targets = [
            RequestStatus::Draft->value => 6,
            RequestStatus::Submitted->value => 8,
            RequestStatus::UnderReview->value => 6,
            RequestStatus::Approved->value => 4,
            RequestStatus::Rejected->value => 3,
            RequestStatus::InProgress->value => 5,
            RequestStatus::Completed->value => 8,
        ];

        foreach ($targets as $status => $count) {
            OperationalRequest::factory()
                ->count($count)
                ->sequence(fn () => ['created_by' => $employees->random()->id])
                ->create()
                ->each(fn (OperationalRequest $request) => $this->advance($request, RequestStatus::from($status), $workflow, $assignments, $managers->random(), $admin));
        }
    }

    private function advance(
        OperationalRequest $request,
        RequestStatus $target,
        RequestWorkflowService $workflow,
        RequestAssignmentService $assignments,
        User $reviewer,
        User $admin,
    ): void {
        if ($target === RequestStatus::Draft) {
            return;
        }

        $owner = User::findOrFail($request->created_by);
        $request = $workflow->submit($request, $owner);

        if ($target === RequestStatus::Submitted) {
            return;
        }

        $request = $workflow->startReview($request, $reviewer);
        $request = $assignments->assign($request, $reviewer, $admin, 'Routed by operations triage.');

        $request = match ($target) {
            RequestStatus::UnderReview => $request,
            RequestStatus::Rejected => $workflow->reject($request, $reviewer, 'Out of scope for operations; please raise with the owning team.'),
            default => $workflow->approve($request, $reviewer, 'Approved after review.'),
        };

        if (in_array($target, [RequestStatus::InProgress, RequestStatus::Completed], true)) {
            $request = $workflow->start($request, $reviewer);
        }

        if ($target === RequestStatus::Completed) {
            $workflow->complete($request, $reviewer, 'Resolved and verified with requester.');
        }
    }
}
