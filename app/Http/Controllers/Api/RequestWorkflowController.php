<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\TransitionOperationalRequestRequest;
use App\Http\Resources\OperationalRequestResource;
use App\Models\OperationalRequest;
use App\Services\RequestWorkflowService;
use Illuminate\Support\Facades\Gate;

class RequestWorkflowController extends Controller
{
    public function __construct(private readonly RequestWorkflowService $workflow) {}

    public function submit(TransitionOperationalRequestRequest $request, OperationalRequest $operationalRequest): OperationalRequestResource
    {
        Gate::authorize('submit', $operationalRequest);

        return $this->respond($this->workflow->submit($operationalRequest, $request->user()));
    }

    public function startReview(TransitionOperationalRequestRequest $request, OperationalRequest $operationalRequest): OperationalRequestResource
    {
        Gate::authorize('review', $operationalRequest);

        return $this->respond($this->workflow->startReview($operationalRequest, $request->user()));
    }

    public function approve(TransitionOperationalRequestRequest $request, OperationalRequest $operationalRequest): OperationalRequestResource
    {
        Gate::authorize('review', $operationalRequest);

        return $this->respond($this->workflow->approve($operationalRequest, $request->user(), $request->validated('reason')));
    }

    public function reject(TransitionOperationalRequestRequest $request, OperationalRequest $operationalRequest): OperationalRequestResource
    {
        Gate::authorize('review', $operationalRequest);

        return $this->respond($this->workflow->reject($operationalRequest, $request->user(), $request->validated('reason')));
    }

    public function start(TransitionOperationalRequestRequest $request, OperationalRequest $operationalRequest): OperationalRequestResource
    {
        Gate::authorize('work', $operationalRequest);

        return $this->respond($this->workflow->start($operationalRequest, $request->user()));
    }

    public function complete(TransitionOperationalRequestRequest $request, OperationalRequest $operationalRequest): OperationalRequestResource
    {
        Gate::authorize('work', $operationalRequest);

        return $this->respond($this->workflow->complete($operationalRequest, $request->user(), $request->validated('reason')));
    }

    private function respond(OperationalRequest $operationalRequest): OperationalRequestResource
    {
        return OperationalRequestResource::make($operationalRequest->load(['creator', 'assignee']));
    }
}
