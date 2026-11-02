<?php

namespace App\Policies;

use App\Enums\RequestStatus;
use App\Models\OperationalRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * Decides *who* may act on a request. Whether the request's current status
 * allows the action is enforced separately by RequestWorkflowService (409).
 */
class OperationalRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, OperationalRequest $request): bool
    {
        return $user->isOperations()
            || $this->owns($user, $request)
            || $request->assigned_to === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, OperationalRequest $request): Response
    {
        if ($user->isOperations()) {
            return $request->status->isTerminal()
                ? Response::deny('Closed requests cannot be edited.')
                : Response::allow();
        }

        if (! $this->owns($user, $request)) {
            return Response::deny();
        }

        return $request->status === RequestStatus::Draft
            ? Response::allow()
            : Response::deny('Only draft requests can be edited by their creator.');
    }

    public function submit(User $user, OperationalRequest $request): bool
    {
        return $this->owns($user, $request);
    }

    /**
     * Start review, approve and reject. Nobody signs off on their own request.
     */
    public function review(User $user, OperationalRequest $request): Response
    {
        if (! $user->isOperations()) {
            return Response::deny();
        }

        return $this->owns($user, $request)
            ? Response::deny('You cannot review a request you created.')
            : Response::allow();
    }

    public function assign(User $user, OperationalRequest $request): bool
    {
        return $user->isOperations();
    }

    /**
     * Start and complete approved work.
     */
    public function work(User $user, OperationalRequest $request): bool
    {
        return $user->isOperations();
    }

    private function owns(User $user, OperationalRequest $request): bool
    {
        return $request->created_by === $user->id;
    }
}
