<?php

namespace Database\Factories;

use App\Domain\AgentProtocol\Models\Agent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\AgentProtocol\Models\Agent>
 */
class AgentFactory extends Factory
{
    protected $model = Agent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $capabilities = $this->faker->randomElements(
            ['relay', 'payment', 'escrow', 'messaging', 'analytics', 'workflow'],
            $this->faker->numberBetween(1, 4)
        );

        $endpoints = [
            'primary'   => $this->faker->url,
            'api'       => $this->faker->url . '/api',
            'websocket' => 'wss://' . $this->faker->domainName . '/ws',
        ];

        return [
            'agent_id'     => 'agent_' . $this->faker->unique()->uuid,
            'did'          => 'did:example:' . $this->faker->unique()->uuid,
            'name'         => $this->faker->company,
            'type'         => $this->faker->randomElement(['standard', 'premium', 'enterprise']),
            'status'       => $this->faker->randomElement(['active', 'inactive', 'pending']),
            'network_id'   => 'network_' . $this->faker->uuid,
            'organization' => $this->faker->company,
            'endpoints'    => $endpoints,
            'capabilities' => $capabilities,
            'metadata'     => [
                'region'  => $this->faker->countryCode,
                'version' => '1.0.0',
            ],
            'relay_score'      => $this->faker->randomFloat(2, 0, 100),
            'last_activity_at' => $this->faker->dateTimeBetween('-1 day', 'now'),
            'created_at'       => now(),
            'updated_at'       => now(),
        ];
    }

    /**
     * Indicate that the agent is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'           => 'active',
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Indicate that the agent is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Indicate that the agent is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'inactive',
        ]);
    }

    /**
     * Indicate that the agent can relay messages.
     */
    public function withRelayCapability(): static
    {
        return $this->state(fn (array $attributes) => [
            'capabilities' => array_unique(array_merge($attributes['capabilities'] ?? [], ['relay'])),
            'relay_score'  => $this->faker->randomFloat(2, 70, 100),
        ]);
    }

    /**
     * Indicate that the agent is part of an enterprise organization.
     */
    public function enterprise(): static
    {
        return $this->state(fn (array $attributes) => [
            'type'         => 'enterprise',
            'organization' => 'Enterprise Corp ' . $this->faker->numberBetween(1, 100),
        ]);
    }
}
