<?php

namespace App\Http\Controllers\Api;

use App\Enums\AuditAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRequestCommentRequest;
use App\Http\Resources\RequestCommentResource;
use App\Models\OperationalRequest;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class RequestCommentController extends Controller
{
    public function index(OperationalRequest $operationalRequest): AnonymousResourceCollection
    {
        Gate::authorize('view', $operationalRequest);

        return RequestCommentResource::collection(
            $operationalRequest->comments()->with('author')->paginate(50)
        );
    }

    public function store(StoreRequestCommentRequest $request, OperationalRequest $operationalRequest, AuditService $audit): JsonResponse
    {
        $comment = DB::transaction(function () use ($request, $operationalRequest, $audit) {
            $comment = $operationalRequest->comments()->make($request->validated());
            $comment->author()->associate($request->user());
            $comment->save();

            // The comment id only: the body already lives in request_comments.
            $audit->record(AuditAction::CommentAdded, $operationalRequest, $request->user(), ['comment_id' => $comment->id]);

            return $comment;
        });

        return RequestCommentResource::make($comment->load('author'))->response()->setStatusCode(201);
    }
}
