<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AssignOperationalRequestRequest;
use App\Http\Resources\OperationalRequestResource;
use App\Models\OperationalRequest;
use App\Models\User;
use App\Services\RequestAssignmentService;

class RequestAssignmentController extends Controller
{
    public function store(
        AssignOperationalRequestRequest $request,
        OperationalRequest $operationalRequest,
        RequestAssignmentService $assignments,
    ): OperationalRequestResource {
        $assigned = $assignments->assign(
            $operationalRequest,
            User::findOrFail($request->validated('assignee_id')),
            $request->user(),
            $request->validated('note'),
        );

        return OperationalRequestResource::make(
            $assigned->load(['creator', 'assignee', 'assignments.assignee', 'assignments.assigner'])
        );
    }
}
