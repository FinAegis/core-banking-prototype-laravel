<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CgoPricingRound>
 */
class CgoPricingRoundFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        static $roundNumber = 1;
        
        return [
            'round_number' => $roundNumber++,
            'share_price' => $this->faker->randomFloat(2, 10, 100),
            'max_shares_available' => $this->faker->numberBetween(1000, 100000),
            'shares_sold' => 0,
            'total_raised' => 0,
            'started_at' => now(),
            'ended_at' => null,
            'is_active' => false,
        ];
    }
    
    /**
     * Indicate that the round is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
    
    /**
     * Indicate that the round is closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
            'ended_at' => now()->subDays(rand(1, 30)),
        ]);
    }
}