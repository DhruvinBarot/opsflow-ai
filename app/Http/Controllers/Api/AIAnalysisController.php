<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AIAnalysisResource;
use App\Models\OperationalRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class AIAnalysisController extends Controller
{
    public function show(OperationalRequest $operationalRequest): AIAnalysisResource|JsonResponse
    {
        Gate::authorize('view', $operationalRequest);

        $analysis = $operationalRequest->aiAnalysis()->first();

        if ($analysis === null) {
            return response()->json([
                'message' => 'This request has not been submitted for analysis yet.',
                'error' => 'analysis_not_found',
            ], 404);
        }

        return AIAnalysisResource::make($analysis);
    }
}
