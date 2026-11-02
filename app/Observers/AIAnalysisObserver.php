<?php

namespace App\Observers;

use App\Models\AIAnalysis;
use App\Services\DashboardStatsService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Dashboard stats include AI pipeline counts, so analysis status changes invalidate them too.
 */
class AIAnalysisObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly DashboardStatsService $stats) {}

    public function saved(AIAnalysis $analysis): void
    {
        $this->stats->flush();
    }
}
