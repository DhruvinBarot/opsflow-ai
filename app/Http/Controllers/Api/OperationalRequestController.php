<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListOperationalRequestsRequest;
use App\Http\Requests\StoreOperationalRequestRequest;
use App\Http\Requests\UpdateOperationalRequestRequest;
use App\Http\Resources\OperationalRequestResource;
use App\Http\Resources\RequestStatusHistoryResource;
use App\Models\OperationalRequest;
use App\Services\RequestWorkflowService;
use Illuminate\Database\Eloquent\Builder;
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
            ->with(['creator', 'assignee', 'aiAnalysis'])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['priority'] ?? null, fn ($query, $priority) => $query->where('priority', $priority))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->when($filters['assigned_to'] ?? null, fn ($query, $userId) => $query->where('assigned_to', $userId))
            ->when($filters['created_by'] ?? null, fn ($query, $userId) => $query->where('created_by', $userId))
            // "!" as an explicit LIKE escape behaves identically on MySQL and SQLite, so % and _ match literally.
            ->when($filters['search'] ?? null, fn ($query, $term) => $query->whereRaw(
                "title like ? escape '!'",
                ['%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%'],
            ))
            ->tap(fn ($query) => $this->applySort($query, $filters['sort'] ?? 'newest'))
            ->paginate($filters['per_page'] ?? 15)
            ->withQueryString();

        return OperationalRequestResource::collection($requests);
    }

    /**
     * Sort keys are a fixed allow-list (validated), so the raw CASE below never contains user input.
     *
     * @param  Builder<OperationalRequest>  $query
     */
    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'oldest' => $query->oldest()->oldest('id'),
            'due_soon' => $query->orderByRaw('due_at is null')->orderBy('due_at')->latest('id'),
            'priority' => $query->orderByRaw("case priority when 'critical' then 0 when 'high' then 1 when 'medium' then 2 else 3 end")->latest()->latest('id'),
            default => $query->latest()->latest('id'),
        };
    }

    public function store(StoreOperationalRequestRequest $request, RequestWorkflowService $workflow): JsonResponse
    {
        $operationalRequest = $workflow->create($request->user(), $request->validated());

        return OperationalRequestResource::make($operationalRequest->load('creator'))
            ->response()
            ->setStatusCode(201);
    }

    public function show(OperationalRequest $operationalRequest): OperationalRequestResource
    {
        Gate::authorize('view', $operationalRequest);

        return OperationalRequestResource::make(
            $operationalRequest->load(['creator', 'assignee', 'assignments.assignee', 'assignments.assigner', 'aiAnalysis'])
        );
    }

    public function update(UpdateOperationalRequestRequest $request, OperationalRequest $operationalRequest, RequestWorkflowService $workflow): OperationalRequestResource
    {
        $operationalRequest = $workflow->update($operationalRequest, $request->user(), $request->validated());

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
