<?php

namespace Database\Factories;

use App\Models\CgoInvestment;
use App\Models\CgoPricingRound;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class CgoInvestmentFactory extends Factory
{
    protected $model = CgoInvestment::class;

    public function definition(): array
    {
        $tiers = ['bronze', 'silver', 'gold'];
        $tier = $this->faker->randomElement($tiers);
        $amounts = [
            'bronze' => 1000,
            'silver' => 5000,
            'gold' => 10000,
        ];

        return [
            'uuid' => Str::uuid(),
            'user_id' => User::factory(),
            'round_id' => CgoPricingRound::factory(),
            'amount' => $amounts[$tier],
            'currency' => 'USD',
            'share_price' => 10.00,
            'shares_purchased' => $amounts[$tier] / 10,
            'ownership_percentage' => ($amounts[$tier] / 10) / 1000000 * 100, // Assuming 1M total shares
            'tier' => $tier,
            'status' => 'pending',
            'payment_method' => $this->faker->randomElement(['card', 'crypto', 'bank_transfer']),
            'payment_status' => 'pending',
            'email' => $this->faker->safeEmail(),
            'metadata' => [],
        ];
    }

    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
            'payment_status' => 'completed',
            'payment_completed_at' => now(),
        ]);
    }

    public function withStripePayment(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'card',
            'stripe_session_id' => 'cs_test_' . Str::random(24),
            'stripe_payment_intent_id' => 'pi_test_' . Str::random(24),
        ]);
    }

    public function withCoinbasePayment(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'crypto',
            'coinbase_charge_id' => 'charge_' . Str::random(8),
            'coinbase_charge_code' => strtoupper(Str::random(8)),
            'crypto_payment_url' => 'https://commerce.coinbase.com/charges/' . strtoupper(Str::random(8)),
        ]);
    }

    public function withCertificate(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'certificate_number' => 'CGO-' . strtoupper($attributes['tier'][0]) . '-' . date('Y') . '-' . str_pad($this->faker->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
                'certificate_issued_at' => now(),
            ];
        });
    }
}