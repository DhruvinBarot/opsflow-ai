<?php

namespace App\Services\AI;

use App\Enums\RequestCategory;
use App\Enums\RequestPriority;
use App\Enums\RiskLevel;
use App\Enums\Team;
use App\Models\OperationalRequest;
use Illuminate\Support\Str;

/**
 * Deterministic, rule-based analyzer. Runs with no network access, so it is the
 * default for local development and tests, and the fallback when a provider fails.
 */
class LocalRequestAnalyzer implements AIRequestAnalyzerInterface
{
    /**
     * Keyword patterns per category. Title matches weigh double.
     *
     * @var array<string, list<string>>
     */
    private const CATEGORY_SIGNALS = [
        'incident' => ['outage', 'down', 'error rate', 'errors?', 'failing', 'failed', 'latency', '5\d\d', 'crash(es|ed)?', 'degraded', 'spik(e|ing)', 'incident', 'sev ?[12]', 'timeouts?'],
        'infrastructure' => ['servers?', 'cluster', 'redis', 'database', 'disk', 'certificate', 'tls', 'ssl', 'dns', 'kubernetes', 'k8s', 'deploy(ment)?', 'capacity', 'workers?', 'provision', 'load balancer', 'replica', 'staging'],
        'access' => ['access', 'permissions?', 'vpn', 'mfa', 'sso', 'log ?in', 'password reset', 'accounts?', 'roles?', 'grant', 'revoke', 'onboard(ing)?', 'offboard(ing)?', 'github org'],
        'data' => ['export', 'backfill', 'dataset', 'reports?', 'csv', 'quer(y|ies)', 'etl', 'analytics', 'cohort', 'data pull'],
        'compliance' => ['soc ?2', 'audit', 'gdpr', 'hipaa', 'polic(y|ies)', 'retention', 'questionnaire', 'erasure', 'regulat(or|ory)', 'compliance', 'iso ?27001'],
        'billing' => ['invoices?', 'charge[ds]?', 'refunds?', 'payments?', 'billing', 'subscription', 'renewal', 'discount', 'credit card', 'overcharged'],
        'customer_escalation' => ['customers?', 'client', 'enterprise', 'key account', 'escalat(e|ed|ion)', 'complain(t|ed)?', 'churn', 'sla'],
    ];

    private const CRITICAL_SIGNALS = ['outage', 'production down', 'prod down', 'all customers', 'data breach', 'security breach', 'sev ?1'];

    private const HIGH_SIGNALS = ['production', 'prod', 'urgent', 'asap', 'blocking', 'blocked', 'customers? affected', 'latency', 'error rate', 'escalat(e|ed|ion)', 'deadline', 'today'];

    private const LOW_SIGNALS = ['when possible', 'nice to have', 'no rush', 'low priority', 'whenever'];

    private const SENSITIVE_SIGNALS = ['pii', 'personal data', 'erasure', 'delete', 'drop', 'admin access', 'root', 'privileged', 'credentials?', 'firewall', 'financial data', 'customer data'];

    public function name(): string
    {
        return 'local';
    }

    public function analyze(OperationalRequest $request): AnalysisResult
    {
        $title = Str::lower($request->title);
        $text = $title.' '.Str::lower($request->description);

        $category = $this->classify($title, $text, $request->category);
        $priority = $this->prioritise($text, $request);
        $risk = $this->assessRisk($text, $category, $priority);

        return AnalysisResult::fromArray([
            'category' => $category->value,
            'priority' => $priority->value,
            'risk_level' => $risk->value,
            'suggested_team' => Team::forCategory($category)->value,
            'summary' => $this->summarise($request),
            'suggested_action' => $this->suggestAction($category, $risk),
        ]);
    }

    /**
     * Highest keyword score wins. With no signal, or a tie involving it, the requester's own category stands.
     */
    private function classify(string $title, string $text, RequestCategory $declared): RequestCategory
    {
        $scores = [];

        foreach (self::CATEGORY_SIGNALS as $category => $patterns) {
            $scores[$category] = 2 * $this->countMatches($title, $patterns) + $this->countMatches($text, $patterns);
        }

        $best = max($scores);

        if ($best === 0 || ($scores[$declared->value] ?? 0) === $best) {
            return $declared;
        }

        return RequestCategory::from(array_search($best, $scores, true));
    }

    private function prioritise(string $text, OperationalRequest $request): RequestPriority
    {
        $priority = match (true) {
            $this->countMatches($text, self::CRITICAL_SIGNALS) > 0 => RequestPriority::Critical,
            $this->countMatches($text, self::HIGH_SIGNALS) > 0 => RequestPriority::High,
            $this->countMatches($text, self::LOW_SIGNALS) > 0 => RequestPriority::Low,
            default => RequestPriority::Medium,
        };

        // A due date inside 24 hours is urgent regardless of wording.
        if ($request->due_at?->isBefore(now()->addDay()) && in_array($priority, [RequestPriority::Low, RequestPriority::Medium], true)) {
            return RequestPriority::High;
        }

        return $priority;
    }

    private function assessRisk(string $text, RequestCategory $category, RequestPriority $priority): RiskLevel
    {
        $sensitive = $this->countMatches($text, self::SENSITIVE_SIGNALS) > 0;
        $production = $this->countMatches($text, ['production', 'prod']) > 0;

        if ($priority === RequestPriority::Critical || ($sensitive && $production)) {
            return RiskLevel::Critical;
        }

        if ($sensitive || $category === RequestCategory::Incident || $priority === RequestPriority::High) {
            return RiskLevel::High;
        }

        return $category === RequestCategory::General ? RiskLevel::Low : RiskLevel::Medium;
    }

    private function summarise(OperationalRequest $request): string
    {
        $firstSentence = Str::of($request->description)->squish()->before('. ')->rtrim('.')->limit(200);

        return Str::limit("{$request->title}: {$firstSentence}.", 300);
    }

    private function suggestAction(RequestCategory $category, RiskLevel $risk): string
    {
        $action = match ($category) {
            RequestCategory::Incident => 'Page the platform on-call, confirm the scope of impact and open an incident channel.',
            RequestCategory::Infrastructure => 'Review capacity and change impact with the platform team, then schedule the change window.',
            RequestCategory::Access => 'Confirm manager approval and grant least-privilege access with an expiry date.',
            RequestCategory::Data => 'Confirm the data owner has approved the request and the legal basis before running the extract.',
            RequestCategory::Compliance => 'Route to compliance for evidence collection and confirm the external deadline.',
            RequestCategory::Billing => 'Verify the account billing history and confirm the correction with finance.',
            RequestCategory::CustomerEscalation => 'Acknowledge the customer within SLA and assign an owner in customer success.',
            RequestCategory::General => 'Triage with operations and confirm an owner and timeline.',
        };

        return in_array($risk, [RiskLevel::High, RiskLevel::Critical], true)
            ? $action.' Requires manager sign-off before execution.'
            : $action;
    }

    /**
     * @param  list<string>  $patterns
     */
    private function countMatches(string $text, array $patterns): int
    {
        $count = 0;

        foreach ($patterns as $pattern) {
            $count += preg_match_all('/\b'.$pattern.'\b/u', $text);
        }

        return $count;
    }
}
