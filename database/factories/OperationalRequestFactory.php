<?php

namespace Database\Factories;

use App\Enums\RequestCategory;
use App\Enums\RequestPriority;
use App\Enums\RequestStatus;
use App\Models\OperationalRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OperationalRequest>
 */
class OperationalRequestFactory extends Factory
{
    /**
     * Realistic request titles per category, so seeded data reads like a real queue.
     *
     * @var array<string, list<string>>
     */
    private const TITLES = [
        'access' => ['Grant read access to finance reporting DB', 'Add contractor to GitHub org', 'Reset MFA for sales VP', 'Provision VPN access for new hire'],
        'infrastructure' => ['Increase API worker pool before launch', 'Provision staging Redis cluster', 'Rotate expiring TLS certificate on api gateway', 'Expand disk on reporting replica'],
        'customer_escalation' => ['Enterprise customer unable to export invoices', 'Key account reporting data mismatch', 'Customer SSO login loop after IdP change'],
        'data' => ['Export Q3 churn cohort for analytics', 'Backfill missing order events for October', 'Delete customer data under erasure request'],
        'compliance' => ['Quarterly SOC 2 access review', 'Vendor security questionnaire for Acme Corp', 'Retention policy review for audit logs'],
        'billing' => ['Duplicate charge on annual renewal', 'Apply contract discount to invoice', 'Refund failed upgrade payment'],
        'incident' => ['Production API latency above 2s', 'Checkout error rate spiking after deploy', 'Nightly ETL job failing since Tuesday'],
        'general' => ['Set up onboarding checklist for support team', 'Order replacement laptops for design team', 'Update on-call rotation for December'],
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = fake()->randomElement(RequestCategory::cases());

        return [
            'created_by' => User::factory(),
            'title' => fake()->randomElement(self::TITLES[$category->value]),
            'description' => fake()->paragraph(3),
            'category' => $category,
            'priority' => fake()->randomElement(RequestPriority::cases()),
            'status' => RequestStatus::Draft,
            'due_at' => fake()->optional(0.6)->dateTimeBetween('+1 day', '+30 days'),
        ];
    }

    /**
     * Put the request directly into a status, bypassing the workflow (tests and fixtures only).
     */
    public function status(RequestStatus $status): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $status,
            'submitted_at' => $status === RequestStatus::Draft ? null : now()->subDays(2),
            'completed_at' => $status === RequestStatus::Completed ? now() : null,
        ]);
    }

    public function submitted(): static
    {
        return $this->status(RequestStatus::Submitted);
    }

    public function underReview(): static
    {
        return $this->status(RequestStatus::UnderReview);
    }

    public function approved(): static
    {
        return $this->status(RequestStatus::Approved);
    }

    public function inProgress(): static
    {
        return $this->status(RequestStatus::InProgress);
    }
}
