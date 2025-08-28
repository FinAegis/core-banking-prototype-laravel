<?php

namespace Database\Factories;

use App\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => $this->faker->uuid(),
            'user_id' => User::factory(),
            'number' => $this->faker->unique()->numerify('ACC########'),
            'name' => $this->faker->words(3, true),
            'type' => $this->faker->randomElement(['savings', 'checking', 'investment']),
            'currency' => $this->faker->randomElement(['USD', 'EUR', 'GBP']),
            'is_active' => true,
            'balance' => $this->faker->randomFloat(2, 0, 100000),
            'available_balance' => $this->faker->randomFloat(2, 0, 100000),
        ];
    }
}
