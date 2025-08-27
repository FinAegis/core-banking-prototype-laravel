<?php

declare(strict_types=1);

namespace App\Domain\Basket\Events;

use DateTimeInterface;
use Spatie\EventSourcing\StoredEvents\ShouldBeStored;

class BasketRebalanced extends ShouldBeStored
{
    public readonly DateTimeInterface $timestamp;

    public function __construct(
        public readonly string $basketCode,
        public readonly array $adjustments,
        public readonly DateTimeInterface $rebalancedAt
    ) {
        // Add timestamp property for test compatibility
        $this->timestamp = $this->rebalancedAt;
    }

    /**
     * Get the total number of adjustments made.
     */
    public function getAdjustmentCount(): int
    {
        return count($this->adjustments);
    }

    /**
     * Get the assets that were adjusted.
     */
    public function getAdjustedAssets(): array
    {
        return array_column($this->adjustments, 'asset');
    }

    /**
     * Check if a specific asset was adjusted.
     */
    public function wasAssetAdjusted(string $assetCode): bool
    {
        return in_array($assetCode, $this->getAdjustedAssets());
    }

    /**
     * Get the adjustment for a specific asset.
     */
    public function getAssetAdjustment(string $assetCode): ?array
    {
        foreach ($this->adjustments as $adjustment) {
            if ($adjustment['asset'] === $assetCode) {
                return $adjustment;
            }
        }

        return null;
    }
}
