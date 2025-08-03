<?php

namespace Database\Factories;

use App\Domain\Webhook\Models\Webhook;
use App\Domain\Webhook\Models\WebhookDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Webhook\Models\WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'webhook_uuid' => Webhook::factory(),
            'event_type'   => fake()->randomElement(array_keys(Webhook::EVENTS)),
            'payload'      => [
                'event' => 'test.event',
                'data'  => [
                    'id'        => fake()->uuid(),
                    'amount'    => fake()->numberBetween(100, 10000),
                    'timestamp' => now()->toIso8601String(),
                ],
            ],
            'attempt_number' => 1,
            'status'         => WebhookDelivery::STATUS_PENDING,
        ];
    }

    /**
     * Indicate that the delivery was successful.
     */
    public function delivered(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'           => WebhookDelivery::STATUS_DELIVERED,
            'response_status'  => 200,
            'response_body'    => json_encode(['success' => true]),
            'response_headers' => [
                'Content-Type' => 'application/json',
                'X-Request-Id' => fake()->uuid(),
            ],
            'duration_ms'  => fake()->numberBetween(50, 500),
            'delivered_at' => now(),
        ]);
    }

    /**
     * Indicate that the delivery failed.
     */
    public function failed(string $error = null): static
    {
        return $this->state(fn (array $attributes) => [
            'status'          => WebhookDelivery::STATUS_FAILED,
            'error_message'   => $error ?? 'Connection timeout',
            'response_status' => fake()->randomElement([0, 500, 502, 503]),
            'next_retry_at'   => now()->addMinutes(5),
        ]);
    }

    /**
     * Indicate that this is a retry attempt.
     */
    public function retry(int $attemptNumber = 2): static
    {
        return $this->state(fn (array $attributes) => [
            'attempt_number' => $attemptNumber,
        ]);
    }
}
