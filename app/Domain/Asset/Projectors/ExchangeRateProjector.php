<?php

declare(strict_types=1);

namespace App\Domain\Asset\Projectors;

use App\Domain\Asset\Events\ExchangeRateUpdated;
use App\Domain\Asset\Models\ExchangeRate;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Spatie\EventSourcing\EventHandlers\Projectors\Projector;

class ExchangeRateProjector extends Projector
{
    /**
     * Handle exchange rate updated event.
     */
    public function onExchangeRateUpdated(ExchangeRateUpdated $event): void
    {
        try {
            // Find existing rate to update or create new one
            $existingRate = ExchangeRate::between($event->fromAssetCode, $event->toAssetCode)
                ->where('source', $event->source)
                ->latest()
                ->first();

            // Create new rate record
            $newRate = ExchangeRate::create(
                [
                    'from_asset_code' => $event->fromAssetCode,
                    'to_asset_code'   => $event->toAssetCode,
                    'rate'            => $event->newRate,
                    'source'          => $event->source,
                    'valid_at'        => now(),
                    'expires_at'      => now()->addHours(1), // Default 1 hour expiry
                    'is_active'       => true,
                    'metadata'        => array_merge(
                        $event->metadata ?? [],
                        [
                            'previous_rate'         => $event->oldRate,
                            'change_percentage'     => $event->getChangePercentage(),
                            'is_increase'           => $event->isIncrease(),
                            'is_significant_change' => $event->isSignificantChange(),
                        ]
                    ),
                ]
            );

            // Deactivate old rate if it exists
            if ($existingRate) {
                $existingRate->update(['is_active' => false]);
            }

            // Clear relevant caches
            $this->clearRateCache($event->fromAssetCode, $event->toAssetCode);

            // Log significant changes
            if ($event->isSignificantChange()) {
                Log::warning(
                    'Significant exchange rate change detected',
                    [
                        'from_asset'        => $event->fromAssetCode,
                        'to_asset'          => $event->toAssetCode,
                        'old_rate'          => $event->oldRate,
                        'new_rate'          => $event->newRate,
                        'change_percentage' => $event->getChangePercentage(),
                        'source'            => $event->source,
                    ]
                );
            } else {
                Log::info(
                    'Exchange rate updated',
                    [
                        'from_asset'        => $event->fromAssetCode,
                        'to_asset'          => $event->toAssetCode,
                        'old_rate'          => $event->oldRate,
                        'new_rate'          => $event->newRate,
                        'change_percentage' => $event->getChangePercentage(),
                        'source'            => $event->source,
                    ]
                );
            }
        } catch (Exception $e) {
            Log::error(
                'Error processing exchange rate update',
                [
                    'from_asset' => $event->fromAssetCode,
                    'to_asset'   => $event->toAssetCode,
                    'old_rate'   => $event->oldRate,
                    'new_rate'   => $event->newRate,
                    'source'     => $event->source,
                    'error'      => $e->getMessage(),
                ]
            );

            throw $e;
        }
    }

    /**
     * Clear exchange rate cache for a specific pair.
     */
    private function clearRateCache(string $fromAsset, string $toAsset): void
    {
        $cacheKeys = [
            "exchange_rate:{$fromAsset}:{$toAsset}",
            "exchange_rate:{$toAsset}:{$fromAsset}", // Also clear inverse
        ];

        foreach ($cacheKeys as $key) {
            Cache::forget($key);
        }
    }
}
