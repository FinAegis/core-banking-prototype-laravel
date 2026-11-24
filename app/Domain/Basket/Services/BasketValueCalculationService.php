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
     *
     * Component weights are shares of the basket's value, not amounts of each
     * asset. The first valuation fixes a base (each component's USD price and
     * the weights) at a value of one US dollar. After that each component
     * adds base value x weight x (price now / base price), so a 2 % gold
     * weight moves the basket by 2 % of gold's price change instead of adding
     * 2 % of an ounce. When the components or weights change, the base is set
     * again at the current value, so a rebalance does not make the value jump.
     */
    private function performCalculation(BasketAsset $basket): BasketValue
    {
        $components = $basket->activeComponents()->with('asset')->get();

        if ($components->isEmpty()) {
            Log::warning("Basket {$basket->code} has no active components");

            return $this->createEmptyValue($basket);
        }

        $prices = [];
        $errors = [];
        foreach ($components as $component) {
            try {
                if (! $component->asset) {
                    throw new Exception("Asset {$component->asset_code} not found");
                }
                $prices[$component->asset_code] = $this->getAssetValueInUsd($component->asset_code);
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

        $weights = [];
        foreach ($components as $component) {
            $weights[$component->asset_code] = (float) $component->weight;
        }

        $base = $this->valuationBase($basket, $weights, $prices);

        $totalValue = 0.0;
        $componentValues = [];
        foreach ($components as $component) {
            $code = $component->asset_code;
            $basePrice = $base['prices'][$code] ?? null;
            if (! isset($prices[$code]) || $basePrice === null || $basePrice <= 0.0) {
                continue;
            }

            $weightedValue = $base['value'] * ($weights[$code] / 100) * ($prices[$code] / $basePrice);
            $totalValue += $weightedValue;
            $componentValues[$code] = [
                'asset_code'     => $code,
                'asset_name'     => $component->asset?->name,
                'value'          => $prices[$code],
                'base_price'     => $basePrice,
                'weight'         => $component->weight,
                'weighted_value' => $weightedValue,
                'currency'       => 'USD',
            ];
        }
        $totalValue = round($totalValue, 8);

        // Store the calculated value
        return DB::transaction(
            function () use ($basket, $base, $totalValue, $componentValues, $errors) {
                // Ensure the basket exists as an asset
                $basket->toAsset();

                $metadata = is_array($basket->metadata) ? $basket->metadata : [];
                if (($metadata['valuation_base'] ?? null) !== $base) {
                    $basket->forceFill(['metadata' => [...$metadata, 'valuation_base' => $base]])->save();
                }

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
                                    'base_value'         => $base['value'],
                                    'base_set_at'        => $base['set_at'],
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
     * The valuation base: kept while the weights stay the same (a component
     * priced for the first time gets its base price now); set again at the
     * current value when components or weights change; one US dollar for a
     * basket that has none yet.
     *
     * @param  array<string, float>  $weights  current weights by asset code
     * @param  array<string, float>  $prices  current USD prices by asset code
     * @return array{value: float, prices: array<string, float>, weights: array<string, float>, set_at: string}
     */
    private function valuationBase(BasketAsset $basket, array $weights, array $prices): array
    {
        $metadata = is_array($basket->metadata) ? $basket->metadata : [];
        $stored = is_array($metadata['valuation_base'] ?? null) ? $metadata['valuation_base'] : null;
        $storedValue = is_numeric($stored['value'] ?? null) ? (float) $stored['value'] : 1.0;
        $storedPrices = $this->floatMap($stored['prices'] ?? null);
        $storedWeights = $this->floatMap($stored['weights'] ?? null);

        if ($stored !== null && $this->sameWeights($storedWeights, $weights)) {
            foreach ($prices as $code => $price) {
                $storedPrices[$code] ??= $price;
            }

            return [
                'value'   => $storedValue,
                'prices'  => $storedPrices,
                'weights' => $weights,
                'set_at'  => is_string($stored['set_at'] ?? null) ? $stored['set_at'] : now()->toIso8601String(),
            ];
        }

        // A first base starts at one dollar. A new one carries the current
        // value over, worked out with the old base and the old weights.
        $value = 1.0;
        if ($stored !== null && $storedWeights !== []) {
            $carried = 0.0;
            $complete = true;
            foreach ($storedWeights as $code => $weight) {
                $basePrice = $storedPrices[$code] ?? null;
                if (! isset($prices[$code]) || $basePrice === null || $basePrice <= 0.0) {
                    $complete = false;
                    break;
                }
                $carried += $storedValue * ($weight / 100) * ($prices[$code] / $basePrice);
            }
            if ($complete && $carried > 0.0) {
                $value = round($carried, 8);
            }
        }

        return ['value' => $value, 'prices' => $prices, 'weights' => $weights, 'set_at' => now()->toIso8601String()];
    }

    /**
     * @return array<string, float>
     */
    private function floatMap(mixed $values): array
    {
        $map = [];
        foreach (is_array($values) ? $values : [] as $code => $value) {
            if (is_string($code) && is_numeric($value)) {
                $map[$code] = (float) $value;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, float>  $stored
     * @param  array<string, float>  $current
     */
    private function sameWeights(array $stored, array $current): bool
    {
        if (count($stored) !== count($current)) {
            return false;
        }
        foreach ($current as $code => $weight) {
            if (! isset($stored[$code]) || abs($stored[$code] - $weight) > 0.0001) {
                return false;
            }
        }

        return true;
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
