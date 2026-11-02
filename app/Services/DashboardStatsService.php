<?php

namespace App\Services;

use App\Enums\AnalysisStatus;
use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Models\AIAnalysis;
use App\Models\OperationalRequest;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Operational counters for the dashboard, cached in Redis.
 *
 * Keys are scoped to what the caller may see: one shared entry for operations
 * staff (who see everything) and one per employee. Invalidation bumps a version
 * number embedded in every key, so a single write invalidates all scopes and the
 * superseded entries simply expire. MySQL stays the source of truth: if Redis is
 * unavailable the stats are computed directly instead of failing the request.
 */
class DashboardStatsService
{
    public const TTL_SECONDS = 300;

    private const VERSION_KEY = 'dashboard:stats:version';

    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user): array
    {
        try {
            $key = $this->key($user);
            $cached = Cache::get($key);
        } catch (Throwable $e) {
            $this->reportCacheFailure('read', $e);

            return $this->compute($user);
        }

        if (is_array($cached)) {
            return $cached;
        }

        $stats = $this->compute($user);

        try {
            Cache::put($key, $stats, self::TTL_SECONDS);
        } catch (Throwable $e) {
            $this->reportCacheFailure('write', $e);
        }

        return $stats;
    }

    public function flush(): void
    {
        try {
            // A random version, not a counter: if the key is evicted or flushed it can never fall back to an old value.
            Cache::forever(self::VERSION_KEY, Str::random(12));
        } catch (Throwable $e) {
            // Entries still expire within TTL_SECONDS, so a missed bump bounds staleness rather than breaking it.
            $this->reportCacheFailure('invalidate', $e);
        }
    }

    public function key(User $user): string
    {
        $scope = $user->isOperations() ? 'operations' : "user:{$user->id}";

        return 'dashboard:stats:'.Cache::get(self::VERSION_KEY, 'initial').':'.$scope;
    }

    /**
     * One grouped query, aggregated in PHP.
     *
     * @return array<string, mixed>
     */
    private function compute(User $user): array
    {
        $rows = OperationalRequest::query()
            ->visibleTo($user)
            ->toBase()
            ->select(['status', 'priority'])
            ->selectRaw('count(*) as aggregate')
            ->groupBy('status', 'priority')
            ->get();

        $byStatus = array_fill_keys(array_column(RequestStatus::cases(), 'value'), 0);
        $openStatuses = array_column(RequestStatus::open(), 'value');
        $urgent = [RequestPriority::High->value, RequestPriority::Critical->value];
        $highPriorityOpen = 0;

        foreach ($rows as $row) {
            $byStatus[$row->status] += (int) $row->aggregate;

            if (in_array($row->status, $openStatuses, true) && in_array($row->priority, $urgent, true)) {
                $highPriorityOpen += (int) $row->aggregate;
            }
        }

        $analysisRows = AIAnalysis::query()
            ->whereIn('operational_request_id', OperationalRequest::query()->visibleTo($user)->select('id'))
            ->toBase()
            ->select('status')
            ->selectRaw('count(*) as aggregate')
            ->selectRaw("sum(case when status = 'completed' and error_code is not null then 1 else 0 end) as fallbacks")
            ->groupBy('status')
            ->get();

        $aiByStatus = array_fill_keys(array_column(AnalysisStatus::cases(), 'value'), 0);
        foreach ($analysisRows as $row) {
            $aiByStatus[$row->status] = (int) $row->aggregate;
        }

        return [
            'scope' => $user->isOperations() ? 'all_requests' : 'own_requests',
            'total' => array_sum($byStatus),
            'open' => array_sum(array_intersect_key($byStatus, array_flip($openStatuses))),
            'pending_review' => $byStatus['submitted'] + $byStatus['under_review'],
            'pending_approval' => $byStatus['under_review'],
            'high_priority_open' => $highPriorityOpen,
            'completed' => $byStatus['completed'],
            'by_status' => $byStatus,
            'ai_analysis' => [
                ...$aiByStatus,
                'fallbacks' => (int) $analysisRows->sum('fallbacks'),
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function reportCacheFailure(string $operation, Throwable $e): void
    {
        Log::warning('dashboard_stats.cache_unavailable', ['operation' => $operation, 'exception' => $e::class]);
    }
}
