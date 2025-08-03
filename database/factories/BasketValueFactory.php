<?php

namespace Database\Factories;

use App\Domain\Basket\Models\BasketAsset;
use App\Domain\Basket\Models\BasketValue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Basket\Models\BasketValue>
 */
class BasketValueFactory extends Factory
{
    protected $model = BasketValue::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'basket_asset_code' => BasketAsset::factory(),
            'value'             => fake()->randomFloat(8, 0.8, 1.5),
            'calculated_at'     => fake()->dateTimeBetween('-1 month', 'now'),
            'component_values'  => [
                'USD' => [
                    'weight'         => 40.0,
                    'weighted_value' => fake()->randomFloat(8, 0.3, 0.6),
                ],
                'EUR' => [
                    'weight'         => 30.0,
                    'weighted_value' => fake()->randomFloat(8, 0.2, 0.4),
                ],
                'GBP' => [
                    'weight'         => 20.0,
                    'weighted_value' => fake()->randomFloat(8, 0.1, 0.3),
                ],
                'CHF' => [
                    'weight'         => 10.0,
                    'weighted_value' => fake()->randomFloat(8, 0.05, 0.15),
                ],
            ],
        ];
    }

    /**
     * Indicate that the basket value is for a specific date.
     */
    public function calculatedAt($date): static
    {
        return $this->state(function (array $attributes) use ($date) {
            return [
                'calculated_at' => $date,
            ];
        });
    }

    /**
     * Indicate that the basket value is for a specific basket.
     */
    public function forBasket(string $basketCode): static
    {
        return $this->state(function (array $attributes) use ($basketCode) {
            return [
                'basket_asset_code' => $basketCode,
            ];
        });
    }

    /**
     * Indicate that the basket value has specific component values.
     */
    public function withComponents(array $components): static
    {
        return $this->state(function (array $attributes) use ($components) {
            return [
                'component_values' => $components,
            ];
        });
    }
}
