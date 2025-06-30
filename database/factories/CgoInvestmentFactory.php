<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\CgoPricingRound;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CgoInvestment>
 */
class CgoInvestmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = $this->faker->randomElement([100, 500, 1000, 5000, 10000, 25000]);
        $sharePrice = 10.00; // Default share price
        $shares = $amount / $sharePrice;
        $ownershipPercentage = ($shares / 1000000) * 100; // 1M total shares
        
        // Determine tier based on amount
        $tier = 'bronze';
        if ($amount >= 10000) {
            $tier = 'gold';
        } elseif ($amount >= 1000) {
            $tier = 'silver';
        }
        
        return [
            'uuid' => (string) Str::uuid(),
            'user_id' => User::factory(),
            'round_id' => CgoPricingRound::factory(),
            'amount' => $amount,
            'currency' => 'USD',
            'share_price' => $sharePrice,
            'shares_purchased' => $shares,
            'ownership_percentage' => $ownershipPercentage,
            'tier' => $tier,
            'status' => $this->faker->randomElement(['pending', 'confirmed', 'cancelled']),
            'payment_method' => $this->faker->randomElement(['crypto', 'bank_transfer', 'card']),
            'crypto_address' => null,
            'crypto_tx_hash' => null,
            'certificate_number' => null,
            'certificate_issued_at' => null,
            'metadata' => [],
        ];
    }
    
    /**
     * Indicate that the investment is pending.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }
    
    /**
     * Indicate that the investment is confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
            'certificate_number' => 'CGO-' . strtoupper($attributes['tier'][0]) . '-' . date('Y') . '-' . str_pad(rand(1, 999999), 6, '0', STR_PAD_LEFT),
            'certificate_issued_at' => now(),
        ]);
    }
    
    /**
     * Indicate that the investment was made with crypto.
     */
    public function crypto(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'crypto',
            'crypto_address' => '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'crypto_currency' => $this->faker->randomElement(['BTC', 'ETH', 'USDT', 'USDC'])
            ]),
        ]);
    }
}