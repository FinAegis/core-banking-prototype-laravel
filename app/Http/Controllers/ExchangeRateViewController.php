<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Domain\Exchange\Models\ExchangeRate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ExchangeRateViewController extends Controller
{
    /**
     * Display the exchange rate viewer
     */
    public function index(Request $request)
    {
        // Get all active assets
        $assets = Asset::where('is_active', true)
            ->orderBy('type')
            ->orderBy('code')
            ->get();
        
        // Get filter parameters
        $baseCurrency = $request->get('base', 'USD');
        $selectedAssets = $request->get('assets', ['EUR', 'GBP', 'GCU', 'BTC', 'ETH']);
        
        // Get latest exchange rates
        $rates = $this->getLatestRates($baseCurrency, $selectedAssets);
        
        // Get historical data for charts
        $historicalData = $this->getHistoricalData($baseCurrency, $selectedAssets);
        
        // Get rate statistics
        $statistics = $this->getRateStatistics($baseCurrency);
        
        return view('exchange-rates.index', compact(
            'assets',
            'baseCurrency',
            'selectedAssets',
            'rates',
            'historicalData',
            'statistics'
        ));
    }
    
    /**
     * Get real-time rate updates via AJAX
     */
    public function rates(Request $request)
    {
        $baseCurrency = $request->get('base', 'USD');
        $assets = $request->get('assets', ['EUR', 'GBP', 'GCU']);
        
        $rates = $this->getLatestRates($baseCurrency, $assets);
        
        return response()->json([
            'base' => $baseCurrency,
            'timestamp' => now()->toIso8601String(),
            'rates' => $rates,
        ]);
    }
    
    /**
     * Get historical data for a specific pair
     */
    public function historical(Request $request)
    {
        $base = $request->get('base', 'USD');
        $target = $request->get('target', 'EUR');
        $period = $request->get('period', '24h');
        
        $data = $this->getHistoricalDataForPair($base, $target, $period);
        
        return response()->json([
            'base' => $base,
            'target' => $target,
            'period' => $period,
            'data' => $data,
        ]);
    }
    
    /**
     * Get latest rates for given currencies
     */
    private function getLatestRates($baseCurrency, $assets)
    {
        $rates = [];
        
        foreach ($assets as $asset) {
            if ($asset === $baseCurrency) {
                $rates[$asset] = [
                    'rate' => 1.0000,
                    'change_24h' => 0,
                    'change_percent' => 0,
                    'last_updated' => now(),
                ];
                continue;
            }
            
            // Try to get from cache first
            $cacheKey = "rate:{$baseCurrency}:{$asset}";
            $cachedRate = Cache::get($cacheKey);
            
            if ($cachedRate) {
                $rates[$asset] = $cachedRate;
            } else {
                // Get from database
                $latestRate = ExchangeRate::where('base_asset_code', $baseCurrency)
                    ->where('target_asset_code', $asset)
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                if (!$latestRate) {
                    // Try reverse pair
                    $reverseRate = ExchangeRate::where('base_asset_code', $asset)
                        ->where('target_asset_code', $baseCurrency)
                        ->orderBy('created_at', 'desc')
                        ->first();
                    
                    if ($reverseRate) {
                        $rate = 1 / $reverseRate->rate;
                    } else {
                        // Default rate
                        $rate = $this->getDefaultRate($baseCurrency, $asset);
                    }
                } else {
                    $rate = $latestRate->rate;
                }
                
                // Calculate 24h change
                $dayAgoRate = $this->get24hAgoRate($baseCurrency, $asset);
                $change = $rate - $dayAgoRate;
                $changePercent = $dayAgoRate > 0 ? ($change / $dayAgoRate) * 100 : 0;
                
                $rateData = [
                    'rate' => round($rate, 4),
                    'change_24h' => round($change, 4),
                    'change_percent' => round($changePercent, 2),
                    'last_updated' => $latestRate ? $latestRate->created_at : now(),
                ];
                
                $rates[$asset] = $rateData;
                
                // Cache for 1 minute
                Cache::put($cacheKey, $rateData, 60);
            }
        }
        
        return $rates;
    }
    
    /**
     * Get historical data for charts
     */
    private function getHistoricalData($baseCurrency, $assets)
    {
        $data = [];
        
        foreach ($assets as $asset) {
            if ($asset === $baseCurrency) continue;
            
            $data[$asset] = $this->getHistoricalDataForPair($baseCurrency, $asset, '7d');
        }
        
        return $data;
    }
    
    /**
     * Get historical data for a specific currency pair
     */
    private function getHistoricalDataForPair($base, $target, $period)
    {
        $startDate = match($period) {
            '24h' => now()->subDay(),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            default => now()->subDays(7),
        };
        
        $rates = ExchangeRate::where('base_asset_code', $base)
            ->where('target_asset_code', $target)
            ->where('created_at', '>=', $startDate)
            ->orderBy('created_at')
            ->get()
            ->map(function ($rate) {
                return [
                    'timestamp' => $rate->created_at->toIso8601String(),
                    'rate' => $rate->rate,
                ];
            });
        
        // If no direct rates, try reverse
        if ($rates->isEmpty()) {
            $rates = ExchangeRate::where('base_asset_code', $target)
                ->where('target_asset_code', $base)
                ->where('created_at', '>=', $startDate)
                ->orderBy('created_at')
                ->get()
                ->map(function ($rate) {
                    return [
                        'timestamp' => $rate->created_at->toIso8601String(),
                        'rate' => 1 / $rate->rate,
                    ];
                });
        }
        
        return $rates;
    }
    
    /**
     * Get rate statistics
     */
    private function getRateStatistics($baseCurrency)
    {
        return Cache::remember("rate_stats:{$baseCurrency}", 300, function () use ($baseCurrency) {
            $stats = DB::table('exchange_rates')
                ->where('base_asset_code', $baseCurrency)
                ->where('created_at', '>=', now()->subDay())
                ->select(
                    DB::raw('COUNT(*) as total_updates'),
                    DB::raw('COUNT(DISTINCT target_asset_code) as pairs_tracked'),
                    DB::raw('AVG(rate) as avg_rate'),
                    DB::raw('MAX(created_at) as last_update')
                )
                ->first();
            
            $providers = DB::table('exchange_rates')
                ->where('base_asset_code', $baseCurrency)
                ->where('created_at', '>=', now()->subDay())
                ->select('provider', DB::raw('COUNT(*) as count'))
                ->groupBy('provider')
                ->get();
            
            return [
                'total_updates' => $stats->total_updates ?? 0,
                'pairs_tracked' => $stats->pairs_tracked ?? 0,
                'last_update' => $stats->last_update ?? now(),
                'providers' => $providers,
            ];
        });
    }
    
    /**
     * Get rate from 24 hours ago
     */
    private function get24hAgoRate($base, $target)
    {
        $rate = ExchangeRate::where('base_asset_code', $base)
            ->where('target_asset_code', $target)
            ->where('created_at', '<=', now()->subDay())
            ->orderBy('created_at', 'desc')
            ->first();
        
        if (!$rate) {
            // Try reverse
            $reverseRate = ExchangeRate::where('base_asset_code', $target)
                ->where('target_asset_code', $base)
                ->where('created_at', '<=', now()->subDay())
                ->orderBy('created_at', 'desc')
                ->first();
            
            if ($reverseRate) {
                return 1 / $reverseRate->rate;
            }
        }
        
        return $rate ? $rate->rate : $this->getDefaultRate($base, $target);
    }
    
    /**
     * Get default exchange rate
     */
    private function getDefaultRate($base, $target)
    {
        // Default rates for demo purposes
        $defaults = [
            'USD' => [
                'EUR' => 0.92,
                'GBP' => 0.79,
                'GCU' => 0.91,
                'BTC' => 0.000016,
                'ETH' => 0.00028,
                'JPY' => 156.75,
                'CHF' => 0.89,
                'XAU' => 0.00042,
            ],
        ];
        
        if (isset($defaults[$base][$target])) {
            return $defaults[$base][$target];
        }
        
        if (isset($defaults[$target][$base])) {
            return 1 / $defaults[$target][$base];
        }
        
        return 1.0;
    }
}