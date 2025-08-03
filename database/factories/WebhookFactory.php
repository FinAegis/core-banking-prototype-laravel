<?php

namespace Database\Factories;

use App\Domain\Webhook\Models\Webhook;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Webhook\Models\Webhook>
 */
class WebhookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name'        => fake()->company() . ' Webhook',
            'description' => fake()->sentence(),
            'url'         => fake()->url(),
            'events'      => fake()->randomElements(array_keys(Webhook::EVENTS), rand(1, 5)),
            'headers'     => [
                'X-Custom-Header' => fake()->word(),
            ],
            'secret'               => fake()->sha256(),
            'is_active'            => true,
            'retry_attempts'       => 3,
            'timeout_seconds'      => 30,
            'consecutive_failures' => 0,
        ];
    }

    /**
     * Indicate that the webhook is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the webhook has failed multiple times.
     */
    public function withFailures(int $count = 5): static
    {
        return $this->state(fn (array $attributes) => [
            'consecutive_failures' => $count,
            'last_failure_at'      => now(),
        ]);
    }

    /**
     * Indicate that the webhook subscribes to specific events.
     */
    public function forEvents(array $events): static
    {
        return $this->state(fn (array $attributes) => [
            'events' => $events,
        ]);
    }

    /**
     * Indicate that the webhook subscribes to all events.
     */
    public function allEvents(): static
    {
        return $this->state(fn (array $attributes) => [
            'events' => array_keys(Webhook::EVENTS),
        ]);
    }
}
