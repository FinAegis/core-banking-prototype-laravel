<?php

namespace Database\Factories;

use App\Models\RampSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\RampSession>
 */
class RampSessionFactory extends Factory
{
    protected $model = RampSession::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'         => User::factory(),
            'provider'        => 'bridge',
            'type'            => 'on',
            'fiat_currency'   => 'USD',
            'fiat_amount'     => $this->faker->randomFloat(2, 10, 1000),
            'crypto_currency' => 'USDC',
            'crypto_amount'   => null,
            'status'          => RampSession::STATUS_PENDING,
            'source'          => RampSession::SOURCE_USER_INITIATED,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RampSession::STATUS_COMPLETED,
        ]);
    }

    public function withDepositInstructions(): static
    {
        return $this->state(fn (array $attributes): array => [
            'deposit_instructions' => [
                'account_number' => '123456789',
                'routing_number' => '021000021',
                'memo'           => 'BRIDGE-TEST',
            ],
        ]);
    }
}
