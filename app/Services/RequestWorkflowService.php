<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\RequestStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Jobs\AnalyzeOperationalRequestJob;
use App\Models\OperationalRequest;
use App\Models\User;
use App\Services\AI\AIRequestAnalysisService;
use Illuminate\Support\Facades\DB;

/**
 * The single place where an operational request is created, edited or changes status.
 *
 * Every transition is validated against RequestStatus::allowedTransitions(),
 * runs under a row lock so two reviewers cannot act on the same request at
 * once, and writes the status change, its history entry and its audit record
 * atomically. Submission also queues AI analysis, dispatched only after commit.
 */
class RequestWorkflowService
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly AIRequestAnalysisService $analysis,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes  validated content fields
     */
    public function create(User $creator, array $attributes): OperationalRequest
    {
        return DB::transaction(function () use ($creator, $attributes) {
            $request = new OperationalRequest($attributes);
            $request->creator()->associate($creator);
            $request->save();

            $this->audit->record(AuditAction::RequestCreated, $request, $creator, [
                'category' => $request->category,
                'priority' => $request->priority,
            ]);

            return $request->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $attributes  validated content fields
     */
    public function update(OperationalRequest $request, User $actor, array $attributes): OperationalRequest
    {
        return DB::transaction(function () use ($request, $actor, $attributes) {
            $request->fill($attributes);
            $changed = array_keys($request->getDirty());

            if ($changed === []) {
                return $request;
            }

            $priorityChange = $request->isDirty('priority')
                ? ['priority_from' => $request->getOriginal('priority'), 'priority_to' => $request->priority]
                : [];

            $request->save();

            // Field names, not values: descriptions can be long and may contain sensitive detail.
            $this->audit->record(AuditAction::RequestUpdated, $request, $actor, ['fields' => $changed, ...$priorityChange]);

            return $request;
        });
    }

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

            $this->audit->record(AuditAction::forTransitionTo($to), $locked, $actor, [
                'from' => $from,
                'to' => $to,
                'reason' => $reason,
            ]);

            if ($to === RequestStatus::Submitted) {
                // The pending row commits with the submission. The dispatch itself is deferred (not just the push),
                // because ShouldBeUnique takes its lock at dispatch time and a rollback would leave it orphaned.
                $this->analysis->queue($locked);
                DB::afterCommit(fn () => AnalyzeOperationalRequestJob::dispatch($locked));
            }

            return $locked;
        });
    }
}
