<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Exceptions\RequestNotAssignableException;
use App\Models\OperationalRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RequestAssignmentService
{
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

            $locked->assigned_to = $assignee->id;
            $locked->save();

            $locked->assignments()->create([
                'assigned_to' => $assignee->id,
                'assigned_by' => $actor->id,
                'note' => $note,
            ]);

            return $locked;
        });
    }
}
