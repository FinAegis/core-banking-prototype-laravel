<?php

namespace Database\Factories;

use App\Domain\Account\Models\Account;
use App\Domain\Fraud\Models\FraudCase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Fraud\Models\FraudCase>
 */
class FraudCaseFactory extends Factory
{
    protected $model = FraudCase::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Generate unique case number for tests
        static $counter = 1;
        $caseNumber = 'FC-' . date('Y') . '-' . str_pad($counter++, 5, '0', STR_PAD_LEFT);

        return [
            'uuid'                 => $this->faker->uuid(),
            'case_number'          => $caseNumber,
            'status'               => $this->faker->randomElement(['pending', 'investigating', 'confirmed', 'false_positive', 'resolved']),
            'severity'             => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'type'                 => $this->faker->randomElement(array_keys(FraudCase::FRAUD_TYPES)),
            'subject_account_uuid' => function () {
                return Account::factory()->create()->uuid;
            },
            'risk_score'      => $this->faker->randomFloat(2, 0, 100),
            'amount'          => $this->faker->randomFloat(8, 100, 10000),
            'currency'        => 'USD',
            'description'     => $this->faker->paragraph(),
            'detection_rules' => ['rule' => 'high_value_transaction', 'threshold' => 5000],
            'detected_at'     => $this->faker->dateTimeBetween('-7 days', 'now'),
        ];
    }

    /**
     * Indicate that the fraud case is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FraudCase::STATUS_PENDING,
        ]);
    }

    /**
     * Indicate that the fraud case is investigating.
     */
    public function investigating(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'                   => FraudCase::STATUS_INVESTIGATING,
            'investigation_started_at' => now(),
        ]);
    }

    /**
     * Indicate that the fraud case is resolved.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'      => FraudCase::STATUS_RESOLVED,
            'resolved_at' => now(),
        ]);
    }
}
