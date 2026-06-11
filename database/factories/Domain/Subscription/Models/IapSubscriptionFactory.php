<?php

namespace Database\Factories\Domain\Subscription\Models;

use App\Domain\Subscription\Models\IapSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Subscription\Models\IapSubscription>
 */
class IapSubscriptionFactory extends Factory
{
    protected $model = IapSubscription::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'                  => User::factory(),
            'store'                    => $this->faker->randomElement([IapSubscription::STORE_APPLE, IapSubscription::STORE_GOOGLE]),
            'tier'                     => 'pro',
            'status'                   => IapSubscription::STATUS_ACTIVE,
            'original_transaction_id'  => $this->faker->unique()->numerify('############'),
            'current_period_starts_at' => now()->subDays(10),
            'current_period_ends_at'   => now()->addDays(20),
        ];
    }

    public function trialing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status'           => IapSubscription::STATUS_TRIALING,
            'trial_started_at' => now()->subDays(2),
            'trial_ends_at'    => now()->addDays(5),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status'     => IapSubscription::STATUS_EXPIRED,
            'expired_at' => now()->subDay(),
        ]);
    }
}
