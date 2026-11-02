<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListOperationalRequestsRequest;
use App\Http\Requests\StoreOperationalRequestRequest;
use App\Http\Requests\UpdateOperationalRequestRequest;
use App\Http\Resources\OperationalRequestResource;
use App\Http\Resources\RequestStatusHistoryResource;
use App\Models\OperationalRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class OperationalRequestController extends Controller
{
    public function index(ListOperationalRequestsRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $requests = OperationalRequest::query()
            ->visibleTo($request->user())
            ->with(['creator', 'assignee'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn ($query, $priority) => $query->where('priority', $priority))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->when($filters['assigned_to'] ?? null, fn ($query, $userId) => $query->where('assigned_to', $userId))
            ->when($filters['created_by'] ?? null, fn ($query, $userId) => $query->where('created_by', $userId))
            ->latest()
            ->latest('id')
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return OperationalRequestResource::collection($requests);
    }

    public function store(StoreOperationalRequestRequest $request): JsonResponse
    {
        $operationalRequest = new OperationalRequest($request->validated());
        $operationalRequest->creator()->associate($request->user());
        $operationalRequest->save();

        return OperationalRequestResource::make($operationalRequest->refresh()->load('creator'))
            ->response()
            ->setStatusCode(201);
    }

    public function show(OperationalRequest $operationalRequest): OperationalRequestResource
    {
        Gate::authorize('view', $operationalRequest);

        return OperationalRequestResource::make(
            $operationalRequest->load(['creator', 'assignee', 'assignments.assignee', 'assignments.assigner'])
        );
    }

    public function update(UpdateOperationalRequestRequest $request, OperationalRequest $operationalRequest): OperationalRequestResource
    {
        $operationalRequest->update($request->validated());

        return OperationalRequestResource::make($operationalRequest->load(['creator', 'assignee']));
    }

    public function history(OperationalRequest $operationalRequest): AnonymousResourceCollection
    {
        Gate::authorize('view', $operationalRequest);

        return RequestStatusHistoryResource::collection(
            $operationalRequest->statusHistory()->with('actor')->get()
        );
    }
}
