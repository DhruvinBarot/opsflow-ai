<?php

namespace Tests\Support;

use App\Models\OperationalRequest;
use App\Services\AI\AIRequestAnalyzerInterface;
use App\Services\AI\AnalysisResult;
use Throwable;

/**
 * Scriptable stand-in for an external provider: each call consumes the next
 * scripted outcome (a Throwable to throw, or an array of raw model output).
 */
class FakeRequestAnalyzer implements AIRequestAnalyzerInterface
{
    public int $calls = 0;

    /**
     * @param  list<Throwable|array<string, mixed>>  $outcomes
     */
    public function __construct(private array $outcomes = []) {}

    public function name(): string
    {
        return 'fake-provider';
    }

    public function analyze(OperationalRequest $request): AnalysisResult
    {
        $this->calls++;
        $outcome = array_shift($this->outcomes) ?? self::validOutput();

        if ($outcome instanceof Throwable) {
            throw $outcome;
        }

        return AnalysisResult::fromArray($outcome);
    }

    /**
     * @return array<string, string>
     */
    public static function validOutput(): array
    {
        return [
            'category' => 'infrastructure',
            'priority' => 'high',
            'risk_level' => 'high',
            'suggested_team' => 'platform',
            'summary' => 'Production API latency has increased significantly.',
            'suggested_action' => 'Investigate application and database latency.',
        ];
    }
}
