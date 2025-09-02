<?php

namespace Database\Factories\Domain\Compliance;

use App\Domain\Compliance\Models\MonitoringRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Compliance\Models\MonitoringRule>
 */
class MonitoringRuleFactory extends Factory
{
    protected $model = MonitoringRule::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['amount', 'frequency', 'pattern', 'velocity', 'behavior'];
        $severities = ['low', 'medium', 'high', 'critical'];
        
        return [
            'name' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'rule_type' => $this->faker->randomElement($types),
            'conditions' => [
                'threshold' => $this->faker->randomFloat(2, 1000, 100000),
                'period' => $this->faker->randomElement(['1h', '24h', '7d', '30d']),
                'operator' => $this->faker->randomElement(['>', '<', '>=', '<=', '==', '!=']),
            ],
            'actions' => [
                'alert' => true,
                'block' => $this->faker->boolean(30),
                'notify' => $this->faker->boolean(70),
            ],
            'severity' => $this->faker->randomElement($severities),
            'enabled' => $this->faker->boolean(80),
            'priority' => $this->faker->numberBetween(1, 10),
            'metadata' => [
                'test' => true,
                'created_by' => 'factory',
            ],
            'tags' => $this->faker->randomElements(['high-risk', 'monitoring', 'compliance', 'aml', 'kyc'], rand(1, 3)),
        ];
    }

    /**
     * Indicate that the rule is enabled.
     */
    public function enabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => true,
        ]);
    }

    /**
     * Indicate that the rule is disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'enabled' => false,
        ]);
    }

    /**
     * Indicate that the rule is high priority.
     */
    public function highPriority(): static
    {
        return $this->state(fn (array $attributes) => [
            'priority' => 1,
            'severity' => $this->faker->randomElement(['high', 'critical']),
        ]);
    }

    /**
     * Indicate that the rule is for amount monitoring.
     */
    public function amountRule(): static
    {
        return $this->state(fn (array $attributes) => [
            'rule_type' => 'amount',
            'conditions' => [
                'threshold' => $this->faker->randomFloat(2, 10000, 1000000),
                'operator' => '>',
                'currency' => 'USD',
            ],
        ]);
    }

    /**
     * Indicate that the rule is for frequency monitoring.
     */
    public function frequencyRule(): static
    {
        return $this->state(fn (array $attributes) => [
            'rule_type' => 'frequency',
            'conditions' => [
                'max_transactions' => $this->faker->numberBetween(5, 50),
                'period' => $this->faker->randomElement(['1h', '24h', '7d']),
                'transaction_type' => $this->faker->randomElement(['withdrawal', 'transfer', 'any']),
            ],
        ]);
    }
}