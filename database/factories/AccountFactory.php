<?php

namespace Database\Factories;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Account\Models\Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $user = User::factory()->create();

        return [
            'uuid'      => $this->faker->uuid(),
            'name'      => $this->faker->words(3, true),
            'user_uuid' => $user->uuid,
            'balance'   => $this->faker->numberBetween(0, 100000),
            'frozen'    => false,
        ];
    }

    /**
     * Configure the model factory to create an account with zero balance.
     *
     * @return Factory
     */
    public function zeroBalance(): Factory
    {
        return $this->afterCreating(function (Account $account) {
            // Use updateOrCreate to handle existing AccountBalance records
            AccountBalance::updateOrCreate(
                [
                    'account_uuid' => $account->uuid,
                    'asset_code'   => 'USD',
                ],
                [
                    'balance' => 0,
                ]
            );
        })->state(function (array $attributes) {
            return [
                'balance' => 0,
            ];
        });
    }

    /**
     * Configure the model factory to create an account with a specific balance.
     *
     * @param int $balance
     * @return Factory
     */
    public function withBalance(int $balance): Factory
    {
        return $this->afterCreating(function (Account $account) use ($balance) {
            // Use updateOrCreate to handle existing AccountBalance records
            AccountBalance::updateOrCreate(
                [
                    'account_uuid' => $account->uuid,
                    'asset_code'   => 'USD',
                ],
                [
                    'balance' => $balance,
                ]
            );
        })->state(function (array $attributes) use ($balance) {
            return [
                'balance' => $balance,
            ];
        });
    }

    /**
     * Configure the model factory for a specific user.
     *
     * @param User $user
     * @return Factory
     */
    public function forUser(User $user): Factory
    {
        return $this->state(function (array $attributes) use ($user) {
            return [
                'user_uuid' => $user->uuid,
            ];
        });
    }
}
