<?php

namespace Database\Factories;

use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Models\ExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Asset\Models\ExchangeRate>
 */
class ExchangeRateFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = ExchangeRate::class;
    
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Get two different assets
        $assets = Asset::pluck('code')->toArray();
        if (count($assets) < 2) {
            // Ensure we have at least USD and EUR for testing
            $assets = ['USD', 'EUR', 'GBP', 'BTC', 'ETH'];
        }
        
        $fromAsset = fake()->randomElement($assets);
        $toAsset = fake()->randomElement(array_diff($assets, [$fromAsset]));
        
        $validAt = fake()->dateTimeBetween('-30 days', 'now');
        
        return [
            'from_asset_code' => $fromAsset,
            'to_asset_code' => $toAsset,
            'rate' => $this->generateRealisticRate($fromAsset, $toAsset),
            'source' => fake()->randomElement(ExchangeRate::getSources()),
            'valid_at' => $validAt,
            'expires_at' => fake()->optional(0.3)->dateTimeBetween($validAt, '+7 days'),
            'is_active' => fake()->boolean(95),
            'metadata' => $this->generateMetadata(),
        ];
    }
    
    /**
     * Generate realistic exchange rates based on asset types
     */
    private function generateRealisticRate(string $fromAsset, string $toAsset): string
    {
        // Predefined realistic rates for common pairs
        $knownRates = [
            'USD-EUR' => 0.85,
            'EUR-USD' => 1.18,
            'USD-GBP' => 0.73,
            'GBP-USD' => 1.37,
            'EUR-GBP' => 0.86,
            'GBP-EUR' => 1.16,
            'USD-BTC' => 0.000025,
            'BTC-USD' => 40000,
            'USD-ETH' => 0.0004,
            'ETH-USD' => 2500,
            'USD-XAU' => 0.0005,
            'XAU-USD' => 2000,
        ];
        
        $pair = "$fromAsset-$toAsset";
        if (isset($knownRates[$pair])) {
            // Add some variance to the known rate
            $baseRate = $knownRates[$pair];
            $variance = fake()->numberBetween(-5, 5) / 100; // ±5%
            return number_format($baseRate * (1 + $variance), 10, '.', '');
        }
        
        // Generate random rate based on asset types
        if ($this->isCrypto($fromAsset) && $this->isFiat($toAsset)) {
            return number_format(fake()->randomFloat(10, 0.00001, 0.1), 10, '.', '');
        } elseif ($this->isFiat($fromAsset) && $this->isCrypto($toAsset)) {
            return number_format(fake()->randomFloat(2, 1000, 50000), 10, '.', '');
        } elseif ($this->isFiat($fromAsset) && $this->isFiat($toAsset)) {
            return number_format(fake()->randomFloat(6, 0.5, 2.0), 10, '.', '');
        } else {
            return number_format(fake()->randomFloat(8, 0.001, 100), 10, '.', '');
        }
    }
    
    /**
     * Generate metadata based on source
     */
    private function generateMetadata(): array
    {
        return [
            'confidence' => fake()->randomFloat(2, 0.8, 1.0),
            'volume' => fake()->numberBetween(1000, 1000000),
            'provider' => fake()->randomElement(['coinbase', 'binance', 'xe.com', 'fixer.io']),
        ];
    }
    
    /**
     * Check if asset is crypto
     */
    private function isCrypto(string $assetCode): bool
    {
        return in_array($assetCode, ['BTC', 'ETH']);
    }
    
    /**
     * Check if asset is fiat
     */
    private function isFiat(string $assetCode): bool
    {
        return in_array($assetCode, ['USD', 'EUR', 'GBP']);
    }
    
    /**
     * Indicate that the rate is for a specific asset pair
     */
    public function between(string $fromAsset, string $toAsset): static
    {
        return $this->state(fn (array $attributes) => [
            'from_asset_code' => $fromAsset,
            'to_asset_code' => $toAsset,
            'rate' => $this->generateRealisticRate($fromAsset, $toAsset),
        ]);
    }
    
    /**
     * Indicate that the rate is from an API source
     */
    public function fromApi(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => ExchangeRate::SOURCE_API,
            'metadata' => [
                'api_endpoint' => 'https://api.example.com/rates',
                'response_time' => fake()->numberBetween(50, 500),
                'timestamp' => now()->toISOString(),
            ],
        ]);
    }
    
    /**
     * Indicate that the rate is manual
     */
    public function manual(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => ExchangeRate::SOURCE_MANUAL,
            'metadata' => [
                'entered_by' => fake()->name(),
                'reason' => 'Manual override for trading session',
            ],
        ]);
    }
    
    /**
     * Indicate that the rate is valid (active and not expired)
     */
    public function valid(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
            'valid_at' => now()->subMinutes(fake()->numberBetween(1, 60)),
            'expires_at' => now()->addHours(fake()->numberBetween(1, 24)),
        ]);
    }
    
    /**
     * Indicate that the rate is expired
     */
    public function expired(): static
    {
        $expiredAt = fake()->dateTimeBetween('-7 days', '-1 hour');
        
        return $this->state(fn (array $attributes) => [
            'valid_at' => fake()->dateTimeBetween('-30 days', $expiredAt),
            'expires_at' => $expiredAt,
        ]);
    }
    
    /**
     * Create a USD to EUR rate
     */
    public function usdToEur(): static
    {
        return $this->between('USD', 'EUR');
    }
    
    /**
     * Create a USD to BTC rate
     */
    public function usdToBtc(): static
    {
        return $this->between('USD', 'BTC');
    }
}