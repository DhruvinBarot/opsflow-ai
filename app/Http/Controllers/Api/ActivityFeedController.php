<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\AuditLog;
use App\Models\OperationalRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ActivityFeedController extends Controller
{
    /**
     * Recent audit events across all requests, for operations dashboards.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('operations');

        $validated = $request->validate(['per_page' => ['sometimes', 'integer', 'between:1,50']]);

        return AuditLogResource::collection(
            AuditLog::query()
                ->where('entity_type', OperationalRequest::AUDIT_ENTITY)
                ->with(['actor', 'operationalRequest:id,title'])
                ->latest('created_at')
                ->latest('id')
                ->paginate($validated['per_page'] ?? 15)
        );
    }
}
