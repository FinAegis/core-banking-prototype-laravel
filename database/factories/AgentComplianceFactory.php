<?php

namespace Database\Factories;

use App\Domain\AgentProtocol\Models\AgentCompliance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\AgentProtocol\Models\AgentCompliance>
 */
class AgentComplianceFactory extends Factory
{
    protected $model = AgentCompliance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'compliance_id'      => 'comp_' . $this->faker->unique()->uuid(),
            'agent_id'           => 'agent_' . $this->faker->uuid(),
            'status'             => $this->faker->randomElement(['pending', 'verified', 'failed', 'expired']),
            'level'              => $this->faker->randomElement(['basic', 'standard', 'enhanced']),
            'risk_score'         => $this->faker->numberBetween(0, 100),
            'linked_customer_id' => null,
            'linked_at'          => null,
            'link_metadata'      => json_encode([]),
            'transaction_limits' => json_encode([
                'daily_limit'       => 1000,
                'transaction_limit' => 500,
                'monthly_limit'     => 10000,
            ]),
            'metadata'   => json_encode([]),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Indicate that the compliance is verified.
     */
    public function verified(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'     => 'verified',
            'risk_score' => $this->faker->numberBetween(0, 30),
        ]);
    }

    /**
     * Indicate that the compliance is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate high risk.
     */
    public function highRisk(): static
    {
        return $this->state(fn (array $attributes) => [
            'risk_score' => $this->faker->numberBetween(70, 100),
        ]);
    }
}
