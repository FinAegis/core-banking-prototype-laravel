<?php

namespace Database\Factories;

use App\Domain\AgentProtocol\Models\AgentIdentity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\AgentProtocol\Models\AgentIdentity>
 */
class AgentIdentityFactory extends Factory
{
    protected $model = AgentIdentity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id'     => 'agent_' . $this->faker->unique()->uuid,
            'did'          => 'did:example:' . $this->faker->unique()->uuid,
            'name'         => $this->faker->company,
            'type'         => $this->faker->randomElement(['autonomous', 'human', 'hybrid']),
            'status'       => $this->faker->randomElement(['active', 'inactive', 'suspended']),
            'capabilities' => $this->faker->randomElements(
                ['payment', 'escrow', 'messaging', 'analytics'],
                $this->faker->numberBetween(1, 3)
            ),
            'reputation_score' => $this->faker->randomFloat(2, 0, 100),
            'wallet_id'        => 'wallet_' . $this->faker->uuid,
            'metadata'         => [
                'created_by'  => 'test',
                'environment' => 'testing',
            ],
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * Indicate that the agent identity is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the agent identity is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'suspended',
        ]);
    }
}
