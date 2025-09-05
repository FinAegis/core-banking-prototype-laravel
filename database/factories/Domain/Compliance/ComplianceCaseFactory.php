<?php

namespace Database\Factories\Domain\Compliance;

use App\Domain\Compliance\Models\ComplianceCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Compliance\Models\ComplianceCase>
 */
class ComplianceCaseFactory extends Factory
{
    protected $model = ComplianceCase::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = [
            ComplianceCase::TYPE_INVESTIGATION,
            ComplianceCase::TYPE_SAR,
            ComplianceCase::TYPE_CTR,
            ComplianceCase::TYPE_REGULATORY,
            ComplianceCase::TYPE_FRAUD,
            ComplianceCase::TYPE_AML,
        ];

        $priorities = [
            ComplianceCase::PRIORITY_LOW,
            ComplianceCase::PRIORITY_MEDIUM,
            ComplianceCase::PRIORITY_HIGH,
            ComplianceCase::PRIORITY_CRITICAL,
        ];

        $statuses = [
            ComplianceCase::STATUS_OPEN,
            ComplianceCase::STATUS_IN_PROGRESS,
            ComplianceCase::STATUS_PENDING_REVIEW,
            ComplianceCase::STATUS_RESOLVED,
            ComplianceCase::STATUS_CLOSED,
        ];

        $caseNumber = sprintf('CASE-%s-%06d', date('Y'), $this->faker->unique()->numberBetween(1, 999999));

        return [
            'case_id'          => 'CASE-' . date('Y') . '-' . str_pad((string) $this->faker->unique()->numberBetween(1, 999), 3, '0', STR_PAD_LEFT),
            'case_number'      => $caseNumber,
            'title'            => $this->faker->sentence(6),
            'description'      => $this->faker->paragraph(3),
            'type'             => $this->faker->randomElement($types),
            'priority'         => $this->faker->randomElement($priorities),
            'status'           => $this->faker->randomElement($statuses),
            'alert_count'      => $this->faker->numberBetween(1, 10),
            'total_risk_score' => $this->faker->randomFloat(2, 0, 500),
            'last_activity_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'due_date'         => $this->faker->dateTimeBetween('now', '+30 days'),
            'sla_status'       => $this->faker->randomElement([
                ComplianceCase::SLA_ON_TRACK,
                ComplianceCase::SLA_AT_RISK,
                ComplianceCase::SLA_BREACHED,
            ]),
            'metadata' => [
                'test'   => true,
                'source' => 'factory',
            ],
            'tags' => $this->faker->randomElements(['urgent', 'regulatory', 'monitoring', 'review'], rand(1, 3)),
        ];
    }

    /**
     * Indicate that the case is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => $this->faker->randomElement([
                ComplianceCase::STATUS_OPEN,
                ComplianceCase::STATUS_IN_PROGRESS,
                ComplianceCase::STATUS_PENDING_REVIEW,
            ]),
            'closed_at' => null,
            'closed_by' => null,
        ]);
    }

    /**
     * Indicate that the case is closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'         => ComplianceCase::STATUS_CLOSED,
            'closed_at'      => $this->faker->dateTimeBetween('-7 days', 'now'),
            'closure_reason' => $this->faker->randomElement([
                'resolved',
                'false_positive',
                'insufficient_evidence',
                'duplicate',
            ]),
            'closure_notes' => $this->faker->paragraph(),
        ]);
    }

    /**
     * Indicate that the case is high priority.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => $this->faker->randomElement([
                ComplianceCase::PRIORITY_HIGH,
                ComplianceCase::PRIORITY_CRITICAL,
            ]),
            'due_date' => $this->faker->dateTimeBetween('now', '+3 days'),
        ]);
    }
}
