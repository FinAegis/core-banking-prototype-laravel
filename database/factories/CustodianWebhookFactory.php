<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Custodian\Models\CustodianWebhook>
 */
class CustodianWebhookFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid'           => fake()->uuid(),
            'custodian_name' => fake()->randomElement(['coinbase', 'binance', 'kraken', 'gemini']),
            'event_type'     => fake()->randomElement(['transaction.completed', 'transaction.pending', 'deposit.confirmed', 'withdrawal.processed']),
            'event_id'       => fake()->uuid(),
            'headers'        => [
                'X-Webhook-ID'        => fake()->uuid(),
                'X-Webhook-Timestamp' => now()->timestamp,
                'Content-Type'        => 'application/json',
            ],
            'payload' => [
                'id'        => fake()->uuid(),
                'type'      => 'transaction',
                'amount'    => fake()->randomFloat(2, 10, 10000),
                'currency'  => fake()->randomElement(['BTC', 'ETH', 'USD', 'EUR']),
                'status'    => 'completed',
                'timestamp' => now()->toISOString(),
            ],
            'signature'            => fake()->sha256(),
            'status'               => fake()->randomElement(['pending', 'processing', 'processed', 'failed']),
            'attempts'             => fake()->numberBetween(0, 3),
            'processed_at'         => null,
            'error_message'        => null,
            'custodian_account_id' => null,
            'transaction_id'       => null,
        ];
    }

    /**
     * Indicate that the webhook is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'        => 'pending',
            'processed_at'  => null,
            'error_message' => null,
        ]);
    }

    /**
     * Indicate that the webhook has been processed.
     */
    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'        => 'processed',
            'processed_at'  => now(),
            'error_message' => null,
        ]);
    }

    /**
     * Indicate that the webhook processing failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'        => 'failed',
            'processed_at'  => null,
            'error_message' => fake()->sentence(),
        ]);
    }
}
