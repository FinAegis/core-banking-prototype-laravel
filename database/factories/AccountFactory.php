<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\User;
use App\Models\AccountBalance;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Account>
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
            'uuid' => Str::uuid(),
            'name' => fake()->words(2, true) . ' Account',
            'user_uuid' => function () {
                return User::factory()->create()->uuid;
            },
            'balance' => fake()->numberBetween(0, 100000),
            'frozen' => false,
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
    public function withBalance(int $balance): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => $balance,
        ]);
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
            // Temporarily disabled automatic USD balance creation to fix test conflicts
            // This was causing unique constraint violations in tests
            
            // // Skip auto balance creation if explicitly requested
            // $attributes = $account->getAttributes();
            // if (isset($attributes['skip_auto_balance']) && $attributes['skip_auto_balance']) {
            //     return;
            // }
            
            // // Get the balance from the raw attributes to avoid the accessor
            // $rawBalance = \DB::table('accounts')
            //     ->where('id', $account->id)
            //     ->value('balance');
            //     
            // // Create USD balance for backward compatibility
            // if ($rawBalance && $rawBalance > 0) {
            //     AccountBalance::create([
            //         'account_uuid' => $account->uuid,
            //         'asset_code' => 'USD',
            //         'balance' => $rawBalance,
            //     ]);
            // }
        });
    }
}