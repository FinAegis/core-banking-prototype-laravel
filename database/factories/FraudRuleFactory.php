<?php

namespace Database\Factories;

use App\Domain\Fraud\Models\FraudRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Fraud\Models\FraudRule>
 */
class FraudRuleFactory extends Factory
{
    protected $model = FraudRule::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $category = $this->faker->randomElement([
            FraudRule::CATEGORY_VELOCITY,
            FraudRule::CATEGORY_PATTERN,
            FraudRule::CATEGORY_AMOUNT,
            FraudRule::CATEGORY_GEOGRAPHY,
            FraudRule::CATEGORY_DEVICE,
            FraudRule::CATEGORY_BEHAVIOR,
        ]);

        $severity = $this->faker->randomElement([
            FraudRule::SEVERITY_LOW,
            FraudRule::SEVERITY_MEDIUM,
            FraudRule::SEVERITY_HIGH,
            FraudRule::SEVERITY_CRITICAL,
        ]);

        return [
            'code'                    => FraudRule::generateRuleCode($category),
            'name'                    => $this->faker->sentence(3),
            'description'             => $this->faker->paragraph(),
            'category'                => $category,
            'severity'                => $severity,
            'is_active'               => $this->faker->boolean(80),
            'is_blocking'             => $severity === FraudRule::SEVERITY_CRITICAL,
            'conditions'              => $this->generateConditions(),
            'thresholds'              => $this->generateThresholds(),
            'time_window'             => $this->faker->randomElement(['1h', '24h', '7d', '30d']),
            'min_occurrences'         => $this->faker->numberBetween(1, 10),
            'base_score'              => $this->faker->numberBetween(10, 100),
            'weight'                  => $this->faker->randomFloat(2, 0.5, 2.0),
            'actions'                 => $this->generateActions($severity),
            'notification_channels'   => $this->faker->randomElements(['email', 'sms', 'slack'], 2),
            'triggers_count'          => $this->faker->numberBetween(0, 1000),
            'true_positives'          => $this->faker->numberBetween(0, 500),
            'false_positives'         => $this->faker->numberBetween(0, 100),
            'precision_rate'          => $this->faker->randomFloat(2, 50, 95),
            'last_triggered_at'       => $this->faker->optional()->dateTimeBetween('-7 days', 'now'),
            'ml_enabled'              => $this->faker->boolean(30),
            'ml_model_id'             => $this->faker->optional()->uuid(),
            'ml_features'             => $this->faker->optional()->randomElements(['velocity', 'amount', 'location', 'time'], 3),
            'ml_confidence_threshold' => $this->faker->optional()->randomFloat(2, 0.5, 0.95),
        ];
    }

    /**
     * Generate conditions for the rule.
     */
    private function generateConditions(): array
    {
        return [
            [
                'field'    => 'transaction_amount',
                'operator' => 'greater_than',
                'value'    => $this->faker->numberBetween(1000, 10000),
            ],
            [
                'field'    => 'transaction_count',
                'operator' => 'greater_than',
                'value'    => $this->faker->numberBetween(5, 20),
            ],
        ];
    }

    /**
     * Generate thresholds for the rule.
     */
    private function generateThresholds(): array
    {
        return [
            'amount_threshold'     => $this->faker->numberBetween(1000, 50000),
            'velocity_threshold'   => $this->faker->numberBetween(5, 50),
            'risk_score_threshold' => $this->faker->numberBetween(60, 90),
        ];
    }

    /**
     * Generate actions based on severity.
     */
    private function generateActions(string $severity): array
    {
        $actions = [FraudRule::ACTION_FLAG];

        if (in_array($severity, [FraudRule::SEVERITY_HIGH, FraudRule::SEVERITY_CRITICAL])) {
            $actions[] = FraudRule::ACTION_BLOCK;
            $actions[] = FraudRule::ACTION_NOTIFY;
        }

        if ($severity === FraudRule::SEVERITY_MEDIUM) {
            $actions[] = FraudRule::ACTION_REVIEW;
        }

        return $actions;
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
     * Indicate that the rule is blocking.
     */
    public function blocking(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_blocking' => true,
            'severity'    => FraudRule::SEVERITY_CRITICAL,
        ]);
    }
}
