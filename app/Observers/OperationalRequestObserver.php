<?php

namespace App\Observers;

use App\Models\OperationalRequest;
use App\Services\DashboardStatsService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Runs after commit, so a rolled-back change never invalidates the cache and a
 * concurrent reader can never re-cache pre-commit data after the flush.
 */
class OperationalRequestObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly DashboardStatsService $stats) {}

    public function saved(OperationalRequest $request): void
    {
        $this->stats->flush();
    }

    public function deleted(OperationalRequest $request): void
    {
        $this->stats->flush();
    }
}
