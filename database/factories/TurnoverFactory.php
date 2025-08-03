<?php

namespace Database\Factories;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\Turnover;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Account\Models\Turnover>
 */
class TurnoverFactory extends Factory
{
    protected $model = Turnover::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $debit = fake()->randomFloat(2, 0, 5000);
        $credit = fake()->randomFloat(2, 0, 5000);
        $amount = $credit - $debit;

        return [
            'account_uuid' => Account::factory(),
            'date'         => fake()->date(),
            'count'        => fake()->numberBetween(1, 100),
            'amount'       => $amount,
            'debit'        => $debit,
            'credit'       => $credit,
        ];
    }
}
