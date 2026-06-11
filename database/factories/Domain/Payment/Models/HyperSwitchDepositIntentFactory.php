<?php

namespace Database\Factories\Domain\Payment\Models;

use App\Domain\Payment\Models\HyperSwitchDepositIntent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Payment\Models\HyperSwitchDepositIntent>
 */
class HyperSwitchDepositIntentFactory extends Factory
{
    protected $model = HyperSwitchDepositIntent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hyperswitch_payment_id' => 'pay_' . $this->faker->unique()->lexify('????????????'),
            'deposit_uuid'           => (string) Str::uuid(),
            'account_uuid'           => (string) Str::uuid(),
            'user_uuid'              => (string) Str::uuid(),
            'amount_cents'           => $this->faker->numberBetween(100, 100000),
            'currency'               => 'USD',
            'status'                 => HyperSwitchDepositIntent::STATUS_PENDING,
        ];
    }

    public function completionFailed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => HyperSwitchDepositIntent::STATUS_COMPLETION_FAILED,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => HyperSwitchDepositIntent::STATUS_COMPLETED,
        ]);
    }
}
