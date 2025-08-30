<?php

namespace Database\Factories\Domain\Compliance;

use App\Domain\Compliance\Models\TransactionMonitoringRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Compliance\Models\TransactionMonitoringRule>
 */
class TransactionMonitoringRuleFactory extends Factory
{
    protected $model = TransactionMonitoringRule::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $categories = ['velocity', 'threshold', 'pattern', 'behavior', 'geography'];
        $riskLevels = ['low', 'medium', 'high'];

        static $counter = 1;
        $ruleCode = 'TMR-' . str_pad($counter++, 3, '0', STR_PAD_LEFT);

        return [
            'rule_code'   => $ruleCode,
            'name'        => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'category'    => $this->faker->randomElement($categories),
            'risk_level'  => $this->faker->randomElement($riskLevels),
            'is_active'   => $this->faker->boolean(80),
            'conditions'  => [
                'threshold'   => $this->faker->numberBetween(1000, 100000),
                'time_window' => $this->faker->numberBetween(1, 24),
                'count'       => $this->faker->numberBetween(1, 10),
            ],
            'actions' => [
                'alert'  => true,
                'block'  => $this->faker->boolean(30),
                'review' => $this->faker->boolean(50),
                'report' => $this->faker->boolean(20),
            ],
            'parameters' => [
                'risk_multiplier'      => $this->faker->randomFloat(2, 1, 3),
                'confidence_threshold' => $this->faker->randomFloat(2, 0.5, 1),
            ],
            'time_window'                  => $this->faker->randomElement(['1h', '24h', '7d', '30d']),
            'threshold_amount'             => $this->faker->optional()->randomFloat(2, 1000, 1000000),
            'threshold_count'              => $this->faker->optional()->numberBetween(1, 100),
            'auto_escalate'                => $this->faker->boolean(30),
            'escalation_level'             => $this->faker->optional()->randomElement(['compliance_team', 'management']),
            'applies_to_customer_types'    => $this->faker->optional()->randomElements(['individual', 'business'], 2),
            'applies_to_risk_levels'       => $this->faker->optional()->randomElements(['low', 'medium', 'high'], 2),
            'applies_to_countries'         => null,
            'applies_to_currencies'        => null,
            'applies_to_transaction_types' => null,
            'triggers_count'               => $this->faker->numberBetween(0, 1000),
            'true_positives'               => $this->faker->numberBetween(0, 100),
            'false_positives'              => $this->faker->numberBetween(0, 50),
            'accuracy_rate'                => $this->faker->randomFloat(2, 60, 98),
            'last_triggered_at'            => $this->faker->optional()->dateTimeBetween('-30 days', 'now'),
            'created_by'                   => \App\Models\User::factory(),
            'last_modified_by'             => null,
            'last_reviewed_at'             => null,
            'tuning_history'               => null,
        ];
    }

    /**
     * Indicate that the rule is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the rule is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the rule is high accuracy.
     */
    public function highAccuracy(): static
    {
        return $this->state(fn (array $attributes) => [
            'true_positives'  => $this->faker->numberBetween(80, 100),
            'false_positives' => $this->faker->numberBetween(0, 10),
            'accuracy_rate'   => $this->faker->randomFloat(2, 85, 98),
        ]);
    }
}
