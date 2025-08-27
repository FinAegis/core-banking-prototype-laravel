<?php

declare(strict_types=1);

namespace App\Domain\Basket\Services;

use App\Domain\Asset\Services\ExchangeRateService;
use App\Domain\Basket\Models\BasketAsset;
use App\Domain\Basket\Models\BasketValue;
use DateTimeInterface;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class BasketValueCalculationService
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRateService
    ) {
    }

    /**
     * Calculate the current value of a basket based on its components.
     */
    public function calculateValue(BasketAsset $basket, bool $useCache = true): BasketValue
    {
        $cacheKey = "basket_value:{$basket->code}";

        if ($useCache && $cachedValue = Cache::get($cacheKey)) {
            return $cachedValue;
        }

        $value = $this->performCalculation($basket);

        // Cache for 5 minutes
        Cache::put($cacheKey, $value, 300);

        return $value;
    }

    /**
     * Perform the actual value calculation.
     */
    private function performCalculation(BasketAsset $basket): BasketValue
    {
        $components = $basket->activeComponents()->with('asset')->get();

        if ($components->isEmpty()) {
            Log::warning("Basket {$basket->code} has no active components");

            return $this->createEmptyValue($basket);
        }

        $totalValue = 0.0;
        $componentValues = [];
        $errors = [];

        foreach ($components as $component) {
            try {
                $componentData = $this->calculateComponentValue($component);
                $totalValue += $componentData['weighted_value'];
                $componentValues[$component->asset_code] = $componentData;
            } catch (Exception $e) {
                Log::error(
                    'Error calculating component value',
                    [
                        'basket'    => $basket->code,
                        'component' => $component->asset_code,
                        'error'     => $e->getMessage(),
                    ]
                );

                $errors[] = [
                    'asset' => $component->asset_code,
                    'error' => $e->getMessage(),
                ];
            }
        }

        // Store the calculated value
        return DB::transaction(
            function () use ($basket, $totalValue, $componentValues, $errors) {
                // Ensure the basket exists as an asset
                $basket->toAsset();

                $basketValue = BasketValue::create(
                    [
                        'basket_asset_code' => $basket->code,
                        'value'             => $totalValue,
                        'calculated_at'     => now(),
                        'component_values'  => array_merge(
                            $componentValues,
                            [
                                '_metadata' => [
                                    'calculation_errors' => $errors,
                                    'total_components'   => count($componentValues),
                                    'base_currency'      => 'USD',
                                ],
                            ]
                        ),
                    ]
                );

                return $basketValue;
            }
        );
    }

    /**
     * Calculate the value contribution of a single component.
     */
    private function calculateComponentValue($component): array
    {
        $asset = $component->asset;

        if (! $asset) {
            throw new Exception("Asset {$component->asset_code} not found");
        }

        // Get the value in USD
        $assetValueInUsd = $this->getAssetValueInUsd($component->asset_code);

        // Calculate weighted value
        $weightedValue = $assetValueInUsd * ($component->weight / 100);

        return [
            'asset_code'     => $component->asset_code,
            'asset_name'     => $asset->name,
            'value'          => $assetValueInUsd,
            'weight'         => $component->weight,
            'weighted_value' => $weightedValue,
            'currency'       => 'USD',
        ];
    }

    /**
     * Get the value of an asset in USD.
     */
    private function getAssetValueInUsd(string $assetCode): float
    {
        if ($assetCode === 'USD') {
            return 1.0;
        }

        $rate = $this->exchangeRateService->getRate($assetCode, 'USD');

        if (! $rate) {
            throw new Exception("No exchange rate available for {$assetCode} to USD");
        }

        return (float) $rate->rate;
    }

    /**
     * Create an empty value record for a basket with no components.
     */
    private function createEmptyValue(BasketAsset $basket): BasketValue
    {
        return BasketValue::create(
            [
                'basket_asset_code' => $basket->code,
                'value'             => 0.0,
                'calculated_at'     => now(),
                'component_values'  => [
                    '_metadata' => [
                        'calculation_errors' => ['No active components'],
                        'total_components'   => 0,
                        'base_currency'      => 'USD',
                    ],
                ],
            ]
        );
    }

    /**
     * Calculate values for all active baskets.
     */
    public function calculateAllBasketValues(): array
    {
        $baskets = BasketAsset::active()->get();
        $results = [
            'successful' => [],
            'failed'     => [],
        ];

        foreach ($baskets as $basket) {
            try {
                $value = $this->calculateValue($basket, false); // Don't use cache
                $results['successful'][] = [
                    'basket'        => $basket->code,
                    'value'         => $value->value,
                    'calculated_at' => $value->calculated_at,
                ];
            } catch (Exception $e) {
                $results['failed'][] = [
                    'basket' => $basket->code,
                    'error'  => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    /**
     * Get historical values for a basket.
     */
    public function getHistoricalValues(
        BasketAsset $basket,
        DateTimeInterface $startDate,
        DateTimeInterface $endDate
    ): array {
        return $basket->values()
            ->betweenDates($startDate, $endDate)
            ->orderBy('calculated_at', 'asc')
            ->get()
            ->map(
                function ($value) {
                    return [
                        'value'         => $value->value,
                        'calculated_at' => $value->calculated_at->toISOString(),
                        'components'    => $value->component_values,
                    ];
                }
            )
            ->toArray();
    }

    /**
     * Calculate the performance of a basket over a time period.
     */
    public function calculatePerformance(
        BasketAsset $basket,
        DateTimeInterface $startDate,
        DateTimeInterface $endDate
    ): array {
        $startValue = $basket->values()
            ->where('calculated_at', '>=', $startDate)
            ->orderBy('calculated_at', 'asc')
            ->first();

        $endValue = $basket->values()
            ->where('calculated_at', '<=', $endDate)
            ->orderBy('calculated_at', 'desc')
            ->first();

        if (! $startValue || ! $endValue) {
            return [
                'start_value'       => null,
                'end_value'         => null,
                'absolute_change'   => 0,
                'percentage_change' => 0,
                'error'             => 'Insufficient data for performance calculation',
            ];
        }

        $change = $endValue->value - $startValue->value;
        $percentageChange = $startValue->value > 0
            ? ($change / $startValue->value) * 100
            : 0;

        return [
            'start_date'        => $startValue->calculated_at->toISOString(),
            'end_date'          => $endValue->calculated_at->toISOString(),
            'start_value'       => $startValue->value,
            'end_value'         => $endValue->value,
            'absolute_change'   => $change,
            'percentage_change' => round($percentageChange, 2),
            'days'              => $startValue->calculated_at->diffInDays($endValue->calculated_at),
        ];
    }

    /**
     * Invalidate cached value for a basket.
     */
    public function invalidateCache(BasketAsset $basket): void
    {
        Cache::forget("basket_value:{$basket->code}");
    }
}
