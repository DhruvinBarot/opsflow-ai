<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\RequestStatus;
use App\Exceptions\RequestNotAssignableException;
use App\Models\OperationalRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RequestAssignmentService
{
    public function __construct(private readonly AuditService $audit) {}

    /**
     * Assign (or reassign) a request and record who did it.
     *
     * @throws RequestNotAssignableException
     */
    public function assign(OperationalRequest $request, User $assignee, User $actor, ?string $note = null): OperationalRequest
    {
        return DB::transaction(function () use ($request, $assignee, $actor, $note) {
            $locked = OperationalRequest::query()->lockForUpdate()->findOrFail($request->getKey());

            if ($locked->status === RequestStatus::Draft || $locked->status->isTerminal()) {
                throw new RequestNotAssignableException($locked->status);
            }

            $previousAssignee = $locked->assigned_to;
            $locked->assigned_to = $assignee->id;
            $locked->save();

            $assignment = $locked->assignments()->create([
                'assigned_to' => $assignee->id,
                'assigned_by' => $actor->id,
                'note' => $note,
            ]);

            $this->audit->record(AuditAction::RequestAssigned, $locked, $actor, [
                'assignment_id' => $assignment->id,
                'assignee_id' => $assignee->id,
                'previous_assignee_id' => $previousAssignee,
            ]);

            return $locked;
        });
    }
}
