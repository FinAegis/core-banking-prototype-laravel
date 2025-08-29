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
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $ruleTypes = ['velocity', 'threshold', 'pattern', 'behavior', 'geography'];
        $categories = ['aml', 'fraud', 'compliance', 'risk'];
        
        return [
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'rule_type' => $this->faker->randomElement($ruleTypes),
            'category' => $this->faker->randomElement($categories),
            'severity' => $this->faker->randomElement(['low', 'medium', 'high', 'critical']),
            'is_active' => $this->faker->boolean(80),
            'conditions' => [
                'threshold' => $this->faker->numberBetween(1000, 100000),
                'time_window' => $this->faker->numberBetween(1, 24),
                'count' => $this->faker->numberBetween(1, 10),
            ],
            'actions' => [
                'alert' => true,
                'block' => $this->faker->boolean(30),
                'review' => $this->faker->boolean(50),
            ],
            'parameters' => [
                'risk_multiplier' => $this->faker->randomFloat(2, 1, 3),
                'confidence_threshold' => $this->faker->randomFloat(2, 0.5, 1),
            ],
            'true_positives' => $this->faker->numberBetween(0, 100),
            'false_positives' => $this->faker->numberBetween(0, 50),
            'accuracy_rate' => $this->faker->randomFloat(2, 60, 98),
            'last_triggered_at' => $this->faker->optional()->dateTimeBetween('-30 days', 'now'),
            'trigger_count' => $this->faker->numberBetween(0, 1000),
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
            'true_positives' => $this->faker->numberBetween(80, 100),
            'false_positives' => $this->faker->numberBetween(0, 10),
            'accuracy_rate' => $this->faker->randomFloat(2, 85, 98),
        ]);
    }
}
