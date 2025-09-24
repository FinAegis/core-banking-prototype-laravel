<?php

namespace Database\Factories;

use App\Domain\AgentProtocol\Models\AgentWallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\AgentProtocol\Models\AgentWallet>
 */
class AgentWalletFactory extends Factory
{
    protected $model = AgentWallet::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wallet_id'           => 'wallet_' . $this->faker->unique()->uuid(),
            'agent_id'            => 'agent_' . $this->faker->uuid(),
            'balance'             => $this->faker->randomFloat(2, 0, 10000),
            'currency'            => $this->faker->randomElement(['USD', 'EUR', 'GBP']),
            'status'              => $this->faker->randomElement(['active', 'suspended', 'frozen']),
            'blockchain_address'  => null,
            'linked_account_uuid' => null,
            'linked_at'           => null,
            'link_metadata'       => json_encode([]),
            'created_at'          => now(),
            'updated_at'          => now(),
        ];
    }

    /**
     * Indicate that the wallet is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Indicate that the wallet has blockchain integration.
     */
    public function withBlockchain(): static
    {
        return $this->state(fn (array $attributes) => [
            'blockchain_address' => '0x' . $this->faker->sha1(),
        ]);
    }

    /**
     * Indicate that the wallet is linked to an account.
     */
    public function linked(): static
    {
        return $this->state(fn (array $attributes) => [
            'linked_account_uuid' => $this->faker->uuid(),
            'linked_at'           => now(),
            'link_metadata'       => json_encode([
                'link_type'   => 'standard',
                'permissions' => ['read', 'transfer'],
            ]),
        ]);
    }
}
