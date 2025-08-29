<?php

namespace Database\Factories;

use App\Domain\Account\Models\Account;
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
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $user = User::factory()->create();
        
        return [
            'uuid' => $this->faker->uuid(),
            'name' => $this->faker->words(3, true),
            'user_uuid' => $user->uuid,
            'balance' => $this->faker->numberBetween(0, 100000),
        ];
    }
}
