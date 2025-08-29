<?php

namespace Database\Factories\Domain\Compliance;

use App\Domain\Compliance\Models\ComplianceAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Compliance\Models\ComplianceAlert>
 */
class ComplianceAlertFactory extends Factory
{
    protected $model = ComplianceAlert::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = [
            ComplianceAlert::TYPE_TRANSACTION,
            ComplianceAlert::TYPE_PATTERN,
            ComplianceAlert::TYPE_VELOCITY,
            ComplianceAlert::TYPE_THRESHOLD,
            ComplianceAlert::TYPE_BEHAVIOR,
        ];

        $severities = [
            ComplianceAlert::SEVERITY_LOW,
            ComplianceAlert::SEVERITY_MEDIUM,
            ComplianceAlert::SEVERITY_HIGH,
            ComplianceAlert::SEVERITY_CRITICAL,
        ];

        $statuses = [
            ComplianceAlert::STATUS_OPEN,
            ComplianceAlert::STATUS_IN_REVIEW,
            ComplianceAlert::STATUS_ESCALATED,
            ComplianceAlert::STATUS_RESOLVED,
            ComplianceAlert::STATUS_FALSE_POSITIVE,
        ];

        $type = $this->faker->randomElement($types);

        return [
            'alert_id'         => strtoupper(substr($type, 0, 3)) . '-' . date('Ymd') . '-' . strtoupper($this->faker->unique()->bothify('????####')),
            'type'             => $type,
            'severity'         => $this->faker->randomElement($severities),
            'status'           => $this->faker->randomElement($statuses),
            'title'            => $this->faker->sentence(6),
            'description'      => $this->faker->paragraph(3),
            'source'           => $this->faker->randomElement(['system', 'rule', 'manual']),
            'risk_score'       => $this->faker->randomFloat(2, 0, 100),
            'confidence_score' => $this->faker->randomFloat(2, 0.5, 1),
            'detected_at'      => $this->faker->dateTimeBetween('-7 days', 'now'),
            'metadata'         => [
                'test'         => true,
                'random_value' => $this->faker->word(),
            ],
            'tags' => $this->faker->randomElements(['suspicious', 'high-risk', 'monitoring', 'review'], rand(1, 3)),
        ];
    }

    /**
     * Indicate that the alert is open.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'      => ComplianceAlert::STATUS_OPEN,
            'assigned_to' => null,
            'resolved_at' => null,
        ]);
    }

    /**
     * Indicate that the alert is resolved.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'                => ComplianceAlert::STATUS_RESOLVED,
            'resolved_at'           => $this->faker->dateTimeBetween('-3 days', 'now'),
            'resolution_time_hours' => $this->faker->randomFloat(2, 1, 72),
            'resolution_notes'      => $this->faker->paragraph(),
        ]);
    }

    /**
     * Indicate that the alert is high priority.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'severity' => $this->faker->randomElement([
                ComplianceAlert::SEVERITY_HIGH,
                ComplianceAlert::SEVERITY_CRITICAL,
            ]),
            'risk_score' => $this->faker->randomFloat(2, 75, 100),
        ]);
    }
}
