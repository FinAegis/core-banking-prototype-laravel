<?php

namespace Database\Factories;

use App\Models\Stablecoin;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Stablecoin>
 */
class StablecoinFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Stablecoin::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $code = strtoupper($this->faker->unique()->lexify('F???'));
        $pegAsset = $this->faker->randomElement(['USD', 'EUR', 'GBP']);
        
        return [
            'code' => $code,
            'name' => 'FinAegis ' . $pegAsset,
            'symbol' => $code,
            'peg_asset_code' => $pegAsset,
            'peg_ratio' => 1.0,
            'target_price' => 1.0,
            'stability_mechanism' => 'collateralized',
            'collateral_ratio' => 1.5, // 150%
            'min_collateral_ratio' => 1.2, // 120%
            'liquidation_penalty' => 0.05, // 5%
            'total_supply' => 0,
            'max_supply' => 1000000000, // 10M
            'total_collateral_value' => 0,
            'mint_fee' => 0.001, // 0.1%
            'burn_fee' => 0.001, // 0.1%
            'precision' => 8,
            'is_active' => true,
            'minting_enabled' => true,
            'burning_enabled' => true,
            'metadata' => null,
        ];
    }

    /**
     * Indicate that the stablecoin is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that minting is disabled.
     */
    public function mintingDisabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'minting_enabled' => false,
        ]);
    }

    /**
     * Indicate that burning is disabled.
     */
    public function burningDisabled(): static
    {
        return $this->state(fn (array $attributes) => [
            'burning_enabled' => false,
        ]);
    }
}