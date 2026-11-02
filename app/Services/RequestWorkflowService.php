<?php

namespace App\Services;

use App\Enums\RequestStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\OperationalRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * The single place where an operational request changes status.
 *
 * Every transition is validated against RequestStatus::allowedTransitions(),
 * runs under a row lock so two reviewers cannot act on the same request at
 * once, and writes the status change and its history entry atomically.
 */
class RequestWorkflowService
{
    public function submit(OperationalRequest $request, User $actor): OperationalRequest
    {
        return $this->transition($request, RequestStatus::Submitted, $actor);
    }

    public function startReview(OperationalRequest $request, User $actor): OperationalRequest
    {
        return $this->transition($request, RequestStatus::UnderReview, $actor);
    }

    public function approve(OperationalRequest $request, User $actor, ?string $reason = null): OperationalRequest
    {
        return $this->transition($request, RequestStatus::Approved, $actor, $reason);
    }

    public function reject(OperationalRequest $request, User $actor, string $reason): OperationalRequest
    {
        return $this->transition($request, RequestStatus::Rejected, $actor, $reason);
    }

    public function start(OperationalRequest $request, User $actor): OperationalRequest
    {
        return $this->transition($request, RequestStatus::InProgress, $actor);
    }

    public function complete(OperationalRequest $request, User $actor, ?string $reason = null): OperationalRequest
    {
        return $this->transition($request, RequestStatus::Completed, $actor, $reason);
    }

    /**
     * @throws InvalidStatusTransitionException
     */
    public function transition(OperationalRequest $request, RequestStatus $to, User $actor, ?string $reason = null): OperationalRequest
    {
        return DB::transaction(function () use ($request, $to, $actor, $reason) {
            // Re-read under lock: the in-memory model may be stale if another reviewer acted first.
            $locked = OperationalRequest::query()->lockForUpdate()->findOrFail($request->getKey());
            $from = $locked->status;

            if (! $from->canTransitionTo($to)) {
                throw new InvalidStatusTransitionException($from, $to);
            }

            $locked->status = $to;

            match ($to) {
                RequestStatus::Submitted => $locked->submitted_at = now(),
                RequestStatus::Completed => $locked->completed_at = now(),
                default => null,
            };

            $locked->save();

            $locked->statusHistory()->create([
                'changed_by' => $actor->id,
                'from_status' => $from,
                'to_status' => $to,
                'reason' => $reason,
            ]);

            return $locked;
        });
    }
}
