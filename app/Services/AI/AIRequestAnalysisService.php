<?php

namespace App\Services\AI;

use App\Enums\AnalysisStatus;
use App\Enums\AuditAction;
use App\Models\AIAnalysis;
use App\Models\OperationalRequest;
use App\Services\AuditService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs an analyzer for a request and stores the advisory result.
 *
 * Guarantees: at most one analysis row per request (unique key + createOrFirst),
 * a completed analysis is never redone, transient provider errors are retried by
 * the queue, and the last attempt or a non-transient error falls back to the
 * local analyzer. The operational request itself is never modified.
 */
class AIRequestAnalysisService
{
    public function __construct(
        private readonly AIRequestAnalyzerInterface $analyzer,
        private readonly LocalRequestAnalyzer $fallback,
        private readonly AuditService $audit,
    ) {}

    /**
     * Ensure a pending analysis row exists. Called inside the submit transaction.
     */
    public function queue(OperationalRequest $request): AIAnalysis
    {
        // Through the relation, so the foreign key is set without being mass-assignable.
        return $request->aiAnalysis()->createOrFirst();
    }

    /**
     * @throws AIProviderException for a transient provider failure when more attempts remain
     */
    public function analyze(OperationalRequest $request, bool $isFinalAttempt = true): AIAnalysis
    {
        $analysis = $this->queue($request);

        if ($analysis->status === AnalysisStatus::Completed) {
            return $analysis;
        }

        $analysis->forceFill(['status' => AnalysisStatus::Processing, 'attempts' => $analysis->attempts + 1])->save();
        $startedAt = microtime(true);
        $fallbackReason = null;

        try {
            $result = $this->analyzer->analyze($request);
            $provider = $this->analyzer->name();
        } catch (AIProviderException|InvalidAnalysisOutputException $e) {
            $retryable = $e instanceof AIProviderException && $e->transient && ! $isFinalAttempt;

            if ($retryable || $this->analyzer instanceof LocalRequestAnalyzer) {
                $analysis->forceFill(['status' => AnalysisStatus::Pending, 'error_code' => $e->errorCode])->save();

                throw $e;
            }

            $fallbackReason = $e->errorCode;
            $result = $this->fallback->analyze($request);
            $provider = $this->fallback->name();
        }

        DB::transaction(function () use ($analysis, $request, $result, $provider, $fallbackReason) {
            $analysis->forceFill([
                ...$result->toArray(),
                'status' => AnalysisStatus::Completed,
                'provider' => $provider,
                'error_code' => $fallbackReason,
                'analyzed_at' => now(),
            ])->save();

            $this->audit->record(AuditAction::AiAnalysisCompleted, $request, null, [
                'analysis_id' => $analysis->id,
                'provider' => $provider,
                'fallback_reason' => $fallbackReason,
                'suggested_priority' => $result->priority,
                'risk_level' => $result->riskLevel,
                'suggested_team' => $result->suggestedTeam,
            ]);
        });

        // Identifiers and timings only: request content never goes to the logs.
        Log::info('ai_analysis.completed', [
            'request_id' => $request->getKey(),
            'provider' => $provider,
            'fallback_reason' => $fallbackReason,
            'attempt' => $analysis->attempts,
            'duration_ms' => (int) ((microtime(true) - $startedAt) * 1000),
        ]);

        return $analysis;
    }

    /**
     * Record a permanent failure. The request's workflow state is untouched, so
     * reviewers can carry on without AI input.
     */
    public function markFailed(OperationalRequest $request, ?Throwable $exception): void
    {
        $errorCode = match (true) {
            $exception instanceof AIProviderException, $exception instanceof InvalidAnalysisOutputException => $exception->errorCode,
            default => 'internal_error',
        };

        DB::transaction(function () use ($request, $errorCode) {
            $analysis = $this->queue($request);

            if ($analysis->status === AnalysisStatus::Completed) {
                return;
            }

            $analysis->forceFill(['status' => AnalysisStatus::Failed, 'error_code' => $errorCode])->save();

            $this->audit->record(AuditAction::AiAnalysisFailed, $request, null, [
                'analysis_id' => $analysis->id,
                'error_code' => $errorCode,
                'attempts' => $analysis->attempts,
            ]);
        });

        Log::warning('ai_analysis.failed', [
            'request_id' => $request->getKey(),
            'error_code' => $errorCode,
            'exception' => $exception ? $exception::class : null,
        ]);
    }
}
