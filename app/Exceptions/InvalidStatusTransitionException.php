<?php

namespace App\Exceptions;

use App\Enums\RequestStatus;
use DomainException;
use Illuminate\Http\JsonResponse;

class InvalidStatusTransitionException extends DomainException
{
    public function __construct(public readonly RequestStatus $from, public readonly RequestStatus $to)
    {
        parent::__construct("A request cannot move from {$from->value} to {$to->value}.");
    }

    /**
     * Render the exception as a 409: the request is valid, the resource's state is not.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'invalid_status_transition',
            'current_status' => $this->from->value,
            'requested_status' => $this->to->value,
            'allowed_transitions' => array_map(fn (RequestStatus $status) => $status->value, $this->from->allowedTransitions()),
        ], 409);
    }
}
