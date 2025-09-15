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
            'name'       => $this->faker->sentence(3),
            'type'       => $this->faker->randomElement($types),
            'rule_type'  => $this->faker->optional()->randomElement($types), // Optional nullable field
            'conditions' => [
                'threshold' => $this->faker->randomFloat(2, 1000, 100000),
                'period'    => $this->faker->randomElement(['1h', '24h', '7d', '30d']),
                'operator'  => $this->faker->randomElement(['>', '<', '>=', '<=', '==', '!=']),
            ],
            'actions'             => null, // Optional nullable field
            'metadata'            => null, // Optional nullable field
            'tags'                => null, // Optional nullable field
            'threshold'           => $this->faker->randomFloat(2, 1000, 100000),
            'severity'            => $this->faker->randomElement($severities),
            'description'         => $this->faker->paragraph(),
            'is_active'           => $this->faker->boolean(80),
            'enabled'             => $this->faker->boolean(80), // Additional enabled field
            'priority'            => $this->faker->numberBetween(1, 100),
            'effectiveness_score' => $this->faker->randomFloat(2, 0, 100),
            'false_positive_rate' => $this->faker->randomFloat(2, 0, 100),
            'trigger_count'       => 0, // New field from migration
            'true_positives'      => 0, // New field from migration
            'false_positives'     => 0, // New field from migration
            'last_triggered_at'   => $this->faker->optional()->dateTimeBetween('-1 month', 'now'),
            'created_by'          => null,
            'updated_by'          => null,
        ];
    }

    /**
     * Indicate that the rule is enabled.
     */
    public function enabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the rule is disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
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
            'type'       => 'amount',
            'conditions' => [
                'threshold' => $this->faker->randomFloat(2, 10000, 1000000),
                'operator'  => '>',
                'currency'  => 'USD',
            ],
        ]);
    }

    /**
     * Indicate that the rule is for frequency monitoring.
     */
    public function frequencyRule(): static
    {
        return $this->state(fn (array $attributes) => [
            'type'       => 'frequency',
            'conditions' => [
                'max_transactions' => $this->faker->numberBetween(5, 50),
                'period'           => $this->faker->randomElement(['1h', '24h', '7d']),
                'transaction_type' => $this->faker->randomElement(['withdrawal', 'transfer', 'any']),
            ],
        ]);
    }
}
