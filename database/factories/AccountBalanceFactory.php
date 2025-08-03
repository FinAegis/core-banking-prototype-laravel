<?php

namespace Database\Factories;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Asset\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Account\Models\AccountBalance>
 */
class AccountBalanceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = AccountBalance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Get or create a random asset
        $asset = Asset::inRandomOrder()->first() ?? Asset::factory()->create();

        return [
            'account_uuid' => Account::factory(),
            'asset_code'   => $asset->code,
            'balance'      => fake()->numberBetween(0, 1000000), // 0 to 10,000 in decimal
        ];
    }

    /**
     * Indicate that the balance is zero.
     */
    public function zero(): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => 0,
        ]);
    }

    /**
     * Indicate that the balance is for a specific asset.
     */
    public function forAsset(string|Asset $asset): static
    {
        $assetCode = $asset instanceof Asset ? $asset->code : $asset;

        return $this->state(fn (array $attributes) => [
            'asset_code' => $assetCode,
        ]);
    }

    /**
     * Indicate that the balance is for a specific account.
     */
    public function forAccount(string|Account $account): static
    {
        $accountUuid = $account instanceof Account ? $account->uuid : $account;

        return $this->state(fn (array $attributes) => [
            'account_uuid' => $accountUuid,
        ]);
    }

    /**
     * Set a specific balance amount.
     */
    public function withBalance(int $balance): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => $balance,
        ]);
    }

    /**
     * Create a USD balance.
     */
    public function usd(): static
    {
        return $this->forAsset('USD');
    }

    /**
     * Create a EUR balance.
     */
    public function eur(): static
    {
        return $this->forAsset('EUR');
    }

    /**
     * Create a BTC balance.
     */
    public function btc(): static
    {
        return $this->forAsset('BTC');
    }
}
