<?php

namespace Database\Factories;

use App\Models\CgoPricingRound;
use Illuminate\Database\Eloquent\Factories\Factory;

class CgoPricingRoundFactory extends Factory
{
    protected $model = CgoPricingRound::class;

    public function definition(): array
    {
        static $roundNumber = 1;
        
        return [
            'round_number' => $roundNumber++,
            'share_price' => $this->faker->randomFloat(4, 5, 20),
            'max_shares_available' => $this->faker->randomElement([100000, 200000, 500000]),
            'shares_sold' => 0,
            'total_raised' => 0,
            'started_at' => now()->subDays($this->faker->numberBetween(1, 30)),
            'ended_at' => now()->addDays($this->faker->numberBetween(30, 90)),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'ended_at' => now()->subDays($this->faker->numberBetween(1, 30)),
            'shares_sold' => $attributes['max_shares_available'],
            'total_raised' => $attributes['max_shares_available'] * $attributes['share_price'],
        ]);
    }
}