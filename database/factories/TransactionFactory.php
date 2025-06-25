<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $types = ['deposit', 'withdrawal', 'transfer_in', 'transfer_out'];
        $type = fake()->randomElement($types);
        
        // Generate amount based on type (deposits and transfers in are positive, withdrawals and transfers out are negative)
        $amount = match($type) {
            'deposit', 'transfer_in' => fake()->numberBetween(100, 100000), // $1 to $1000
            'withdrawal', 'transfer_out' => -fake()->numberBetween(100, 50000), // -$1 to -$500
        };
        
        return [
            'uuid' => Str::uuid()->toString(),
            'account_uuid' => Account::factory(),
            'amount' => $amount,
            'type' => $type,
            'reference' => fake()->optional()->bothify('REF-####-????'),
            'description' => fake()->optional()->sentence(),
            'balance_after' => fake()->numberBetween(0, 1000000), // $0 to $10,000
            'metadata' => fake()->optional()->randomElement([
                null,
                ['source' => 'ATM'],
                ['channel' => 'mobile'],
                ['branch' => fake()->city],
            ]),
        ];
    }
    
    /**
     * Indicate that the transaction is a deposit.
     */
    public function deposit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'deposit',
            'amount' => fake()->numberBetween(100, 100000),
        ]);
    }
    
    /**
     * Indicate that the transaction is a withdrawal.
     */
    public function withdrawal(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'withdrawal',
            'amount' => -fake()->numberBetween(100, 50000),
        ]);
    }
    
    /**
     * Indicate that the transaction is a transfer in.
     */
    public function transferIn(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'transfer_in',
            'amount' => fake()->numberBetween(100, 100000),
        ]);
    }
    
    /**
     * Indicate that the transaction is a transfer out.
     */
    public function transferOut(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'transfer_out',
            'amount' => -fake()->numberBetween(100, 50000),
        ]);
    }
    
    /**
     * Set a specific account for the transaction.
     */
    public function forAccount(Account $account): static
    {
        return $this->state(fn (array $attributes) => [
            'account_uuid' => $account->uuid,
        ]);
    }
}