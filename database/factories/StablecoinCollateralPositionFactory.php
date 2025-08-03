<?php

namespace Database\Factories;

use App\Domain\Stablecoin\Models\StablecoinCollateralPosition;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Stablecoin\Models\StablecoinCollateralPosition>
 */
class StablecoinCollateralPositionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = StablecoinCollateralPosition::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid'                     => $this->faker->unique()->uuid(),
            'account_uuid'             => 'acc-' . Str::random(10),
            'stablecoin_code'          => 'USDS',
            'collateral_asset_code'    => $this->faker->randomElement(['ETH', 'BTC', 'WBTC', 'USDC']),
            'collateral_amount'        => $this->faker->numberBetween(1000000, 1000000000000000000),
            'debt_amount'              => $this->faker->numberBetween(100000000, 10000000000),
            'collateral_ratio'         => $this->faker->randomFloat(4, 1.2, 3.0),
            'liquidation_price'        => $this->faker->randomFloat(8, 100, 50000),
            'interest_accrued'         => 0,
            'status'                   => 'active',
            'last_interaction_at'      => now(),
            'liquidated_at'            => null,
            'auto_liquidation_enabled' => $this->faker->boolean(70),
            'stop_loss_ratio'          => $this->faker->optional(0.3)->randomFloat(4, 1.1, 1.5),
        ];
    }

    /**
     * Indicate that the position is liquidated.
     */
    public function liquidated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'        => 'liquidated',
            'liquidated_at' => now(),
        ]);
    }

    /**
     * Indicate that the position is at risk.
     */
    public function atRisk(): static
    {
        return $this->state(fn (array $attributes) => [
            'collateral_ratio' => $this->faker->randomFloat(4, 1.0, 1.2),
        ]);
    }
}
