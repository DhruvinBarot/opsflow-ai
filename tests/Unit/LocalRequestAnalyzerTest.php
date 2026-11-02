<?php

namespace Tests\Unit;

use App\Enums\RequestCategory;
use App\Enums\RequestPriority;
use App\Enums\RiskLevel;
use App\Enums\Team;
use App\Models\OperationalRequest;
use App\Services\AI\LocalRequestAnalyzer;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LocalRequestAnalyzerTest extends TestCase
{
    private function request(string $title, string $description, string $declared = 'general', ?string $dueAt = null): OperationalRequest
    {
        $request = new OperationalRequest([
            'title' => $title,
            'description' => $description,
            'category' => $declared,
            'priority' => 'medium',
            'due_at' => $dueAt,
        ]);

        return $request;
    }

    /**
     * @return array<string, array{string, string, RequestCategory, Team}>
     */
    public static function classificationCases(): array
    {
        return [
            'incident' => ['Checkout error rate spiking', 'Since the 14:00 deploy we see 5xx errors and timeouts.', RequestCategory::Incident, Team::Platform],
            'infrastructure' => ['Provision staging Redis cluster', 'Need a new cluster with two replicas for load testing.', RequestCategory::Infrastructure, Team::Platform],
            'access' => ['Grant VPN access for new hire', 'Please grant VPN and SSO access; onboarding starts Monday.', RequestCategory::Access, Team::Identity],
            'billing' => ['Duplicate charge on renewal', 'Customer was charged twice; please refund the second payment.', RequestCategory::Billing, Team::Finance],
            'compliance' => ['Quarterly SOC 2 evidence', 'Auditors need retention policy evidence for the SOC 2 audit.', RequestCategory::Compliance, Team::Compliance],
            'data' => ['Export churn cohort to CSV', 'Analytics needs a CSV export of the Q3 churn cohort report.', RequestCategory::Data, Team::Data],
            'customer escalation' => ['Enterprise client escalation', 'Key account escalated a complaint about missed SLA.', RequestCategory::CustomerEscalation, Team::CustomerSuccess],
        ];
    }

    #[DataProvider('classificationCases')]
    public function test_classifies_by_content_not_by_the_declared_category(string $title, string $description, RequestCategory $expected, Team $team): void
    {
        $result = (new LocalRequestAnalyzer)->analyze($this->request($title, $description, declared: 'general'));

        $this->assertSame($expected, $result->category);
        $this->assertSame($team, $result->suggestedTeam);
    }

    public function test_keeps_the_declared_category_when_there_is_no_signal(): void
    {
        $result = (new LocalRequestAnalyzer)->analyze($this->request('Team offsite planning', 'Book a room for the planning session next month.', declared: 'general'));

        $this->assertSame(RequestCategory::General, $result->category);
        $this->assertSame(RiskLevel::Low, $result->riskLevel);
    }

    public function test_declared_category_wins_a_tie(): void
    {
        // "invoice" (billing) and "export" (data) score equally; the requester said billing.
        $result = (new LocalRequestAnalyzer)->analyze($this->request('Invoice export', 'Need the file.', declared: 'billing'));

        $this->assertSame(RequestCategory::Billing, $result->category);
    }

    public function test_outage_language_is_critical_priority_and_risk(): void
    {
        $result = (new LocalRequestAnalyzer)->analyze($this->request('Payments outage', 'Production down for all customers since 09:00.'));

        $this->assertSame(RequestPriority::Critical, $result->priority);
        $this->assertSame(RiskLevel::Critical, $result->riskLevel);
        $this->assertStringContainsString('manager sign-off', $result->suggestedAction);
    }

    public function test_sensitive_production_change_is_critical_risk_even_without_urgency(): void
    {
        $result = (new LocalRequestAnalyzer)->analyze($this->request('Clean up old tables', 'Please drop the legacy tables in the production database.'));

        $this->assertSame(RiskLevel::Critical, $result->riskLevel);
    }

    public function test_low_urgency_wording_lowers_priority(): void
    {
        $result = (new LocalRequestAnalyzer)->analyze($this->request('New monitor for desk', 'No rush, whenever someone has time.'));

        $this->assertSame(RequestPriority::Low, $result->priority);
    }

    public function test_due_date_within_a_day_raises_priority(): void
    {
        $result = (new LocalRequestAnalyzer)->analyze($this->request('Order badges', 'Badges for the visitors.', dueAt: now()->addHours(6)->toDateTimeString()));

        $this->assertSame(RequestPriority::High, $result->priority);
    }

    public function test_summary_is_derived_from_the_request_and_output_is_deterministic(): void
    {
        $request = $this->request('Rotate TLS certificate', 'The api gateway certificate expires Friday. Renewal needs a DNS change.');
        $analyzer = new LocalRequestAnalyzer;

        $first = $analyzer->analyze($request);

        $this->assertSame('Rotate TLS certificate: The api gateway certificate expires Friday.', $first->summary);
        $this->assertEquals($first, $analyzer->analyze($request));
    }
}
