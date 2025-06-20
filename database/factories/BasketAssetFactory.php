<?php

namespace Database\Factories;

use App\Models\BasketAsset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BasketAsset>
 */
class BasketAssetFactory extends Factory
{
    protected $model = BasketAsset::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('BASKET_???')),
            'name' => $this->faker->words(3, true) . ' Basket',
            'description' => $this->faker->sentence(),
            'type' => $this->faker->randomElement(['fixed', 'dynamic']),
            'rebalance_frequency' => $this->faker->randomElement(['daily', 'weekly', 'monthly', 'quarterly', 'never']),
            'last_rebalanced_at' => $this->faker->optional(0.3)->dateTimeBetween('-30 days', 'now'),
            'is_active' => $this->faker->boolean(90), // 90% chance of being active
            'created_by' => null,
            'metadata' => [
                'risk_level' => $this->faker->randomElement(['low', 'medium', 'high']),
                'category' => $this->faker->randomElement(['conservative', 'balanced', 'aggressive']),
            ],
        ];
    }

    /**
     * Indicate that the basket is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the basket is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the basket is fixed (no rebalancing).
     */
    public function fixed(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'fixed',
            'rebalance_frequency' => 'never',
        ]);
    }

    /**
     * Indicate that the basket is dynamic.
     */
    public function dynamic(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'dynamic',
        ]);
    }

    /**
     * Indicate that the basket needs daily rebalancing.
     */
    public function dailyRebalance(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'dynamic',
            'rebalance_frequency' => 'daily',
        ]);
    }

    /**
     * Indicate that the basket was recently rebalanced.
     */
    public function recentlyRebalanced(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_rebalanced_at' => now()->subHours(2),
        ]);
    }

    /**
     * Indicate that the basket needs rebalancing.
     */
    public function needsRebalancing(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'dynamic',
            'rebalance_frequency' => 'daily',
            'last_rebalanced_at' => now()->subDays(2),
        ]);
    }

    /**
     * Create a stable currency basket.
     */
    public function stableCurrencyBasket(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'STABLE_BASKET',
            'name' => 'Stable Currency Basket',
            'description' => 'A diversified basket of major stable fiat currencies',
            'type' => 'fixed',
            'metadata' => [
                'risk_level' => 'low',
                'category' => 'conservative',
                'target_market' => 'risk-averse investors',
            ],
        ]);
    }

    /**
     * Create a crypto index basket.
     */
    public function cryptoIndexBasket(): static
    {
        return $this->state(fn (array $attributes) => [
            'code' => 'CRYPTO_INDEX',
            'name' => 'Crypto Index Basket',
            'description' => 'Top cryptocurrencies by market cap',
            'type' => 'dynamic',
            'rebalance_frequency' => 'weekly',
            'metadata' => [
                'risk_level' => 'high',
                'category' => 'aggressive',
                'target_market' => 'crypto enthusiasts',
            ],
        ]);
    }

    /**
     * Configure the model factory with components.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (BasketAsset $basket) {
            // Only create components for specific basket types
            if (in_array($basket->code, ['STABLE_BASKET', 'CRYPTO_INDEX'])) {
                return;
            }

            // Create random components that sum to 100%
            $remainingWeight = 100.0;
            $numComponents = $this->faker->numberBetween(2, 5);
            $assets = ['USD', 'EUR', 'GBP', 'JPY', 'CHF', 'XAU'];
            $selectedAssets = $this->faker->randomElements($assets, $numComponents);

            foreach ($selectedAssets as $index => $assetCode) {
                if ($index === count($selectedAssets) - 1) {
                    // Last component gets remaining weight
                    $weight = $remainingWeight;
                } else {
                    // Random weight between 10% and remaining weight
                    $maxWeight = min($remainingWeight - (10 * (count($selectedAssets) - $index - 1)), 50);
                    $weight = $this->faker->randomFloat(2, 10, $maxWeight);
                    $remainingWeight -= $weight;
                }

                $basket->components()->create([
                    'asset_code' => $assetCode,
                    'weight' => round($weight, 2),
                    'min_weight' => $basket->type === 'dynamic' ? round($weight * 0.8, 2) : null,
                    'max_weight' => $basket->type === 'dynamic' ? round($weight * 1.2, 2) : null,
                    'is_active' => true,
                ]);
            }
        });
    }
}