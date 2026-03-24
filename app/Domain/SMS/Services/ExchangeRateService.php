<?php

declare(strict_types=1);

namespace App\Domain\SMS\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetches and caches EUR/USD exchange rates for SMS pricing.
 *
 * Falls back to config value if API is unavailable.
 */
class ExchangeRateService
{
    private const CACHE_KEY = 'sms:exchange_rate:eur_usd';

    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Get the current EUR/USD rate.
     */
    public function getEurUsdRate(): float
    {
        /** @var float $rate */
        $rate = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): float {
            return $this->fetchRate();
        });

        return $rate;
    }

    /**
     * Force refresh the cached rate.
     */
    public function refreshRate(): float
    {
        $rate = $this->fetchRate();
        Cache::put(self::CACHE_KEY, $rate, self::CACHE_TTL);

        return $rate;
    }

    private function fetchRate(): float
    {
        $fallback = (float) config('sms.pricing.eur_usd_rate', 1.08);

        try {
            // Use a free, unauthenticated exchange rate API
            $response = Http::timeout(10)
                ->get('https://open.er-api.com/v6/latest/EUR');

            if ($response->successful()) {
                $usdRate = $response->json('rates.USD');

                if (is_numeric($usdRate) && (float) $usdRate > 0) {
                    $rate = (float) $usdRate;

                    Log::debug('ExchangeRate: EUR/USD fetched', ['rate' => $rate]);

                    return $rate;
                }
            }
        } catch (Throwable $e) {
            Log::warning('ExchangeRate: Failed to fetch EUR/USD rate', [
                'error' => $e->getMessage(),
            ]);
        }

        Log::debug('ExchangeRate: Using fallback EUR/USD rate', ['rate' => $fallback]);

        return $fallback;
    }
}
