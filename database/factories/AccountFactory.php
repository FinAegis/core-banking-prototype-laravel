<?php

namespace Database\Factories;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Models\User;
use DB;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Account\Models\Account>
 */
class AccountFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Account::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid'      => Str::uuid(),
            'name'      => fake()->words(2, true) . ' Account',
            'user_uuid' => function () {
                return User::factory()->create()->uuid;
            },
            'balance' => 0, // Default to 0 to prevent automatic USD balance creation
            'frozen'  => false,
        ];
    }

    /**
     * Create an account with zero balance.
     */
    public function zeroBalance(): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => 0,
        ]);
    }

    /**
     * Create an account with a specific balance.
     */
    public function withBalance(int $balance, string $assetCode = 'USD'): static
    {
        return $this->afterCreating(function (Account $account) use ($balance, $assetCode) {
            AccountBalance::factory()->create([
                'account_uuid' => $account->uuid,
                'asset_code'   => $assetCode,
                'balance'      => $balance,
            ]);
        });
    }

    /**
     * Create an account for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_uuid' => $user->uuid,
        ]);
    }

    /**
     * Create a frozen account.
     */
    public function frozen(): static
    {
        return $this->state(fn (array $attributes) => [
            'frozen' => true,
        ]);
    }

    /**
     * Configure the model factory.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Account $account) {
            // Skip balance creation in testing to avoid transaction issues
            if (app()->environment('testing')) {
                return;
            }

            // Get the balance from the raw attributes to avoid the accessor
            $rawBalance = DB::table('accounts')
                ->where('id', $account->id)
                ->value('balance');

            // Create USD balance for backward compatibility
            if ($rawBalance && $rawBalance > 0) {
                // Check if balance already exists to avoid duplicate key errors
                $existingBalance = AccountBalance::where('account_uuid', $account->uuid)
                    ->where('asset_code', 'USD')
                    ->first();

                if (! $existingBalance) {
                    AccountBalance::create([
                        'account_uuid' => $account->uuid,
                        'asset_code'   => 'USD',
                        'balance'      => $rawBalance,
                    ]);
                }
            }
        });
    }
}
