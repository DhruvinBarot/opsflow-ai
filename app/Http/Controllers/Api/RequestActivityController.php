<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\OperationalRequest;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class RequestActivityController extends Controller
{
    public function index(OperationalRequest $operationalRequest): AnonymousResourceCollection
    {
        Gate::authorize('viewActivity', $operationalRequest);

        return AuditLogResource::collection(
            $operationalRequest->auditLogs()->with('actor')->paginate(50)
        );
    }
}
