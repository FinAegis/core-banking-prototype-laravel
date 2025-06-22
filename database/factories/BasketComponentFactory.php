<?php

namespace Database\Factories;

use App\Models\BasketAsset;
use App\Models\BasketComponent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BasketComponent>
 */
class BasketComponentFactory extends Factory
{
    protected $model = BasketComponent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'basket_asset_id' => BasketAsset::factory(),
            'asset_code' => $this->faker->randomElement(['USD', 'EUR', 'GBP', 'CHF', 'JPY', 'BTC', 'ETH', 'XAU']),
            'weight' => $this->faker->randomFloat(2, 5, 30),
            'min_weight' => function (array $attributes) {
                return $attributes['weight'] - $this->faker->randomFloat(2, 1, 5);
            },
            'max_weight' => function (array $attributes) {
                return $attributes['weight'] + $this->faker->randomFloat(2, 1, 5);
            },
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the component is inactive.
     */
    public function inactive(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_active' => false,
            ];
        });
    }

    /**
     * Indicate that the component has fixed weight (no min/max).
     */
    public function fixed(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'min_weight' => null,
                'max_weight' => null,
            ];
        });
    }

    /**
     * Set a specific weight for the component.
     */
    public function withWeight(float $weight): static
    {
        return $this->state(function (array $attributes) use ($weight) {
            return [
                'weight' => $weight,
                'min_weight' => null,
                'max_weight' => null,
            ];
        });
    }
}