<?php

namespace Database\Factories\Domain\Subscription\Models;

use App\Domain\Subscription\Models\RevenueEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Subscription\Models\RevenueEvent>
 */
class RevenueEventFactory extends Factory
{
    protected $model = RevenueEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_type'          => RevenueEvent::TYPE_SUBSCRIPTION_RENEWAL,
            'user_id'             => null,
            'user_id_hash'        => null,
            'source_type'         => 'stripe',
            'source_event_id'     => 'evt_' . $this->faker->unique()->lexify('????????????'),
            'aggregate_id'        => null,
            'amount'              => 999,
            'amount_decimals'     => 2,
            'amount_denomination' => 'EUR',
            'payload'             => null,
            'emitted_at'          => now(),
        ];
    }

    public function refund(): static
    {
        return $this->state(fn (array $attributes): array => [
            'event_type' => RevenueEvent::TYPE_REFUND,
            'amount'     => -999,
        ]);
    }
}
