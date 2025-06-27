<?php

namespace Database\Factories;

use App\Models\BasketAsset;
use App\Models\BasketPerformance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BasketPerformance>
 */
class BasketPerformanceFactory extends Factory
{
    protected $model = BasketPerformance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startValue = fake()->randomFloat(4, 0.8, 1.2);
        $endValue = fake()->randomFloat(4, 0.8, 1.2);
        $returnValue = $endValue - $startValue;
        $returnPercentage = $startValue > 0 ? ($returnValue / $startValue) * 100 : 0;
        
        $periodType = fake()->randomElement(['hour', 'day', 'week', 'month', 'quarter', 'year']);
        $now = now();
        
        [$periodStart, $periodEnd] = match($periodType) {
            'hour' => [$now->copy()->subHour(), $now],
            'day' => [$now->copy()->subDay(), $now],
            'week' => [$now->copy()->subWeek(), $now],
            'month' => [$now->copy()->subMonth(), $now],
            'quarter' => [$now->copy()->subQuarter(), $now],
            'year' => [$now->copy()->subYear(), $now],
        };

        return [
            'basket_asset_code' => BasketAsset::factory(),
            'period_type' => $periodType,
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'start_value' => $startValue,
            'end_value' => $endValue,
            'high_value' => fake()->randomFloat(4, $endValue, $endValue * 1.1),
            'low_value' => fake()->randomFloat(4, $startValue * 0.9, $startValue),
            'average_value' => ($startValue + $endValue) / 2,
            'return_value' => $returnValue,
            'return_percentage' => round($returnPercentage, 4),
            'volatility' => fake()->randomFloat(4, 0, 30),
            'sharpe_ratio' => fake()->randomFloat(4, -2, 3),
            'max_drawdown' => fake()->randomFloat(4, 0, 20),
            'value_count' => fake()->numberBetween(1, 100),
            'metadata' => [
                'calculation_date' => now()->toIso8601String(),
                'data_points' => fake()->numberBetween(1, 100),
            ],
        ];
    }

    /**
     * Indicate that the performance is positive.
     */
    public function positive(): static
    {
        return $this->state(function (array $attributes) {
            $startValue = $attributes['start_value'];
            $gain = fake()->randomFloat(4, 0.01, 0.20); // 1% to 20% gain
            $endValue = $startValue * (1 + $gain);
            
            return [
                'end_value' => $endValue,
                'return_value' => $endValue - $startValue,
                'return_percentage' => $gain * 100,
                'high_value' => $endValue * 1.05,
                'average_value' => ($startValue + $endValue) / 2,
            ];
        });
    }

    /**
     * Indicate that the performance is negative.
     */
    public function negative(): static
    {
        return $this->state(function (array $attributes) {
            $startValue = $attributes['start_value'];
            $loss = fake()->randomFloat(4, 0.01, 0.20); // 1% to 20% loss
            $endValue = $startValue * (1 - $loss);
            
            return [
                'end_value' => $endValue,
                'return_value' => $endValue - $startValue,
                'return_percentage' => -($loss * 100),
                'low_value' => $endValue * 0.95,
                'average_value' => ($startValue + $endValue) / 2,
            ];
        });
    }

    /**
     * Indicate that the performance is for a specific period type.
     */
    public function forPeriod(string $periodType): static
    {
        return $this->state(function (array $attributes) use ($periodType) {
            $now = now();
            
            [$periodStart, $periodEnd] = match($periodType) {
                'hour' => [$now->copy()->subHour(), $now],
                'day' => [$now->copy()->subDay(), $now],
                'week' => [$now->copy()->subWeek(), $now],
                'month' => [$now->copy()->subMonth(), $now],
                'quarter' => [$now->copy()->subQuarter(), $now],
                'year' => [$now->copy()->subYear(), $now],
                'all_time' => [$now->copy()->subYears(2), $now],
                default => [$now->copy()->subDay(), $now],
            };
            
            return [
                'period_type' => $periodType,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ];
        });
    }
}