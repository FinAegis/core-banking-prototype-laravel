<?php

namespace Database\Factories;

use App\Domain\Asset\Models\Asset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Asset\Models\Asset>
 */
class AssetFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Asset::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(Asset::getTypes());

        // Generate a unique code that avoids common asset codes
        // Prefix with 'T' for test to avoid conflicts with seeded data
        $code = 'T' . strtoupper(fake()->unique()->lexify('??'));

        return [
            'code'      => $code,
            'name'      => fake()->company() . ' ' . $this->getTypeLabel($type),
            'type'      => $type,
            'precision' => $this->getPrecisionForType($type),
            'is_active' => fake()->boolean(90), // 90% chance of being active
            'metadata'  => $this->getMetadataForType($type),
        ];
    }

    /**
     * Get type label.
     */
    private function getTypeLabel(string $type): string
    {
        return match ($type) {
            Asset::TYPE_FIAT      => 'Currency',
            Asset::TYPE_CRYPTO    => 'Cryptocurrency',
            Asset::TYPE_COMMODITY => 'Commodity',
            Asset::TYPE_CUSTOM    => 'Asset',
        };
    }

    /**
     * Get appropriate precision for asset type.
     */
    private function getPrecisionForType(string $type): int
    {
        return match ($type) {
            Asset::TYPE_FIAT      => fake()->numberBetween(0, 2),
            Asset::TYPE_CRYPTO    => fake()->numberBetween(6, 18),
            Asset::TYPE_COMMODITY => fake()->numberBetween(2, 4),
            Asset::TYPE_CUSTOM    => fake()->numberBetween(0, 8),
        };
    }

    /**
     * Get metadata for asset type.
     */
    private function getMetadataForType(string $type): array
    {
        $metadata = ['symbol' => fake()->currencyCode()];

        return match ($type) {
            Asset::TYPE_FIAT => array_merge($metadata, [
                'iso_code' => fake()->currencyCode(),
                'country'  => fake()->country(),
            ]),
            Asset::TYPE_CRYPTO => array_merge($metadata, [
                'network'          => fake()->randomElement(['ethereum', 'bitcoin', 'solana', 'polygon']),
                'contract_address' => fake()->optional()->sha256(),
            ]),
            Asset::TYPE_COMMODITY => array_merge($metadata, [
                'unit'     => fake()->randomElement(['troy_ounce', 'kilogram', 'barrel', 'bushel']),
                'exchange' => fake()->randomElement(['COMEX', 'LME', 'NYMEX', 'ICE']),
            ]),
            Asset::TYPE_CUSTOM => $metadata,
        };
    }

    /**
     * Indicate that the asset is a fiat currency.
     */
    public function fiat(): static
    {
        return $this->state(fn (array $attributes) => [
            'type'      => Asset::TYPE_FIAT,
            'precision' => 2,
            'metadata'  => [
                'symbol'   => fake()->currencyCode(),
                'iso_code' => fake()->currencyCode(),
            ],
        ]);
    }

    /**
     * Indicate that the asset is a cryptocurrency.
     */
    public function crypto(): static
    {
        return $this->state(fn (array $attributes) => [
            'type'      => Asset::TYPE_CRYPTO,
            'precision' => fake()->numberBetween(8, 18),
            'metadata'  => [
                'symbol'  => '₿',
                'network' => fake()->randomElement(['ethereum', 'bitcoin', 'solana']),
            ],
        ]);
    }

    /**
     * Indicate that the asset is a commodity.
     */
    public function commodity(): static
    {
        return $this->state(fn (array $attributes) => [
            'type'      => Asset::TYPE_COMMODITY,
            'precision' => 3,
            'metadata'  => [
                'symbol' => fake()->randomElement(['Au', 'Ag', 'Pt', 'Cu']),
                'unit'   => 'troy_ounce',
            ],
        ]);
    }

    /**
     * Indicate that the asset is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the asset is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
