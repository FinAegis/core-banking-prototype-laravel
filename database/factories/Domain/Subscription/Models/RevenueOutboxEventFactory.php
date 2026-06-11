<?php

namespace Database\Factories\Domain\Subscription\Models;

use App\Domain\Subscription\Models\RevenueOutboxEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Subscription\Models\RevenueOutboxEvent>
 */
class RevenueOutboxEventFactory extends Factory
{
    protected $model = RevenueOutboxEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'source_event_id' => 'evt_' . $this->faker->unique()->lexify('????????????'),
            'source_type'     => RevenueOutboxEvent::SOURCE_STRIPE,
            'event_kind'      => 'invoice.payment_succeeded',
            'payload'         => ['amount' => 999, 'decimals' => 2, 'denomination' => 'EUR'],
            'status'          => RevenueOutboxEvent::STATUS_PENDING,
            'attempts'        => 0,
        ];
    }

    public function delivered(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status'       => RevenueOutboxEvent::STATUS_DELIVERED,
            'attempts'     => 1,
            'delivered_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status'        => RevenueOutboxEvent::STATUS_FAILED,
            'attempts'      => 5,
            'failed_reason' => 'Simulated projection failure',
        ]);
    }
}
