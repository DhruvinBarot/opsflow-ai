<?php

namespace App\Jobs;

use App\Models\OperationalRequest;
use App\Services\AI\AIRequestAnalysisService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Dispatched after the submit transaction commits. ShouldBeUnique stops duplicate
 * dispatches piling up; the service's completed-check makes re-runs harmless.
 */
class AnalyzeOperationalRequestJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * Seconds to wait before retrying a transient provider failure.
     *
     * @var list<int>
     */
    public array $backoff = [10, 60];

    public int $timeout = 60;

    public int $uniqueFor = 600;

    public bool $deleteWhenMissingModels = true;

    public function __construct(public OperationalRequest $operationalRequest)
    {
        $this->onQueue('ai');
    }

    public function uniqueId(): string
    {
        return (string) $this->operationalRequest->getKey();
    }

    public function handle(AIRequestAnalysisService $analysis): void
    {
        $analysis->analyze($this->operationalRequest, isFinalAttempt: $this->attempts() >= $this->tries);
    }

    public function failed(?Throwable $exception): void
    {
        app(AIRequestAnalysisService::class)->markFailed($this->operationalRequest, $exception);
    }
}
