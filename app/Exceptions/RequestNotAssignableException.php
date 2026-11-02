<?php

namespace App\Exceptions;

use App\Enums\RequestStatus;
use DomainException;
use Illuminate\Http\JsonResponse;

class RequestNotAssignableException extends DomainException
{
    public function __construct(public readonly RequestStatus $status)
    {
        parent::__construct("A request in {$status->value} status cannot be assigned.");
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'error' => 'request_not_assignable',
            'current_status' => $this->status->value,
        ], 409);
    }
}
