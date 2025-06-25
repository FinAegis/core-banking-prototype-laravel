<?php

namespace Database\Factories;

use App\Models\BasketPerformance;
use App\Models\ComponentPerformance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ComponentPerformance>
 */
class ComponentPerformanceFactory extends Factory
{
    protected $model = ComponentPerformance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startWeight = fake()->randomFloat(2, 5, 40);
        $endWeight = fake()->randomFloat(2, max(0, $startWeight - 5), min(100, $startWeight + 5));
        $averageWeight = ($startWeight + $endWeight) / 2;
        
        $contributionPercentage = fake()->randomFloat(4, -5, 5);
        $returnPercentage = fake()->randomFloat(4, -10, 10);
        
        return [
            'basket_performance_id' => BasketPerformance::factory(),
            'asset_code' => fake()->randomElement(['USD', 'EUR', 'GBP', 'CHF', 'JPY', 'BTC', 'ETH', 'XAU']),
            'start_weight' => $startWeight,
            'end_weight' => $endWeight,
            'average_weight' => $averageWeight,
            'contribution_value' => fake()->randomFloat(4, -0.1, 0.1),
            'contribution_percentage' => $contributionPercentage,
            'return_value' => fake()->randomFloat(4, -0.2, 0.2),
            'return_percentage' => $returnPercentage,
        ];
    }

    /**
     * Indicate that the component is a positive contributor.
     */
    public function positiveContributor(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'contribution_percentage' => fake()->randomFloat(4, 0.1, 5),
                'return_percentage' => fake()->randomFloat(4, 0.5, 10),
                'contribution_value' => fake()->randomFloat(4, 0.01, 0.1),
                'return_value' => fake()->randomFloat(4, 0.01, 0.2),
            ];
        });
    }

    /**
     * Indicate that the component is a negative contributor.
     */
    public function negativeContributor(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'contribution_percentage' => fake()->randomFloat(4, -5, -0.1),
                'return_percentage' => fake()->randomFloat(4, -10, -0.5),
                'contribution_value' => fake()->randomFloat(4, -0.1, -0.01),
                'return_value' => fake()->randomFloat(4, -0.2, -0.01),
            ];
        });
    }

    /**
     * Indicate that the component maintained its weight.
     */
    public function stableWeight(): static
    {
        return $this->state(function (array $attributes) {
            $weight = fake()->randomFloat(2, 5, 40);
            
            return [
                'start_weight' => $weight,
                'end_weight' => $weight,
                'average_weight' => $weight,
            ];
        });
    }

    /**
     * Indicate that the component is for a specific asset.
     */
    public function forAsset(string $assetCode): static
    {
        return $this->state(function (array $attributes) use ($assetCode) {
            return [
                'asset_code' => $assetCode,
            ];
        });
    }
}