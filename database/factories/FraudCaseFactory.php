<?php

namespace Database\Factories;

use App\Models\FraudCase;
use App\Models\User;
use App\Models\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\FraudCase>
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
        return [
            'case_number' => FraudCase::generateCaseNumber(),
            'status' => $this->faker->randomElement(['open', 'investigating', 'resolved', 'closed']),
            'priority' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'type' => $this->faker->randomElement(array_keys(FraudCase::FRAUD_TYPES)),
            'subject_user_id' => User::factory(),
            'subject_account_id' => Account::factory(),
            'total_amount' => $this->faker->randomFloat(2, 100, 10000),
            'currency' => 'USD',
            'transaction_count' => $this->faker->numberBetween(1, 10),
            'fraud_start_date' => $this->faker->dateTimeBetween('-30 days', '-7 days'),
            'fraud_end_date' => $this->faker->dateTimeBetween('-7 days', 'now'),
            'description' => $this->faker->paragraph(),
            'detection_method' => $this->faker->randomElement(['rule_based', 'ml_model', 'manual_report', 'external_report']),
            'detection_details' => ['rule' => 'high_value_transaction', 'threshold' => 5000],
            'detected_at' => $this->faker->dateTimeBetween('-7 days', 'now'),
        ];
    }

    /**
     * Indicate that the fraud case is open.
     */
    public function open(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FraudCase::STATUS_OPEN,
        ]);
    }

    /**
     * Indicate that the fraud case is investigating.
     */
    public function investigating(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FraudCase::STATUS_INVESTIGATING,
            'investigation_started_at' => now(),
        ]);
    }

    /**
     * Indicate that the fraud case is resolved.
     */
    public function resolved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => FraudCase::STATUS_RESOLVED,
            'resolved_at' => now(),
            'resolution' => FraudCase::RESOLUTION_CONFIRMED_FRAUD,
        ]);
    }
}