<?php

namespace App\Domain\Exchange\Services;

use App\Domain\Exchange\Contracts\FeeCalculatorInterface;
use App\Domain\Exchange\Projections\Trade;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Support\Facades\Cache;

class FeeCalculator implements FeeCalculatorInterface
{
    private const DEFAULT_MAKER_FEE_PERCENT = '0.001'; // 0.1%
    private const DEFAULT_TAKER_FEE_PERCENT = '0.002'; // 0.2%
    
    public function calculateFees(
        BigDecimal $amount,
        BigDecimal $price,
        string $takerAccountId,
        string $makerAccountId
    ): object {
        $value = $amount->multipliedBy($price);
        
        // Get fee rates (could be customized per account based on volume)
        $makerFeeRate = $this->getFeeRate($makerAccountId, 'maker');
        $takerFeeRate = $this->getFeeRate($takerAccountId, 'taker');
        
        // Calculate fees
        $makerFee = $value->multipliedBy($makerFeeRate)->toScale(18, RoundingMode::DOWN);
        $takerFee = $value->multipliedBy($takerFeeRate)->toScale(18, RoundingMode::DOWN);
        
        return (object)[
            'makerFee' => $makerFee,
            'takerFee' => $takerFee,
            'makerFeeRate' => $makerFeeRate,
            'takerFeeRate' => $takerFeeRate,
        ];
    }
    
    private function getFeeRate(string $accountId, string $type): BigDecimal
    {
        // Check for cached custom fee rate
        $cacheKey = "fee_rate:{$accountId}:{$type}";
        $cachedRate = Cache::get($cacheKey);
        
        if ($cachedRate) {
            return BigDecimal::of($cachedRate);
        }
        
        // Calculate based on 30-day volume
        $volume30d = $this->get30DayVolume($accountId);
        $rate = $this->getVolumeBasedRate($volume30d, $type);
        
        // Cache for 1 hour
        Cache::put($cacheKey, $rate->__toString(), now()->addHour());
        
        return $rate;
    }
    
    private function get30DayVolume(string $accountId): BigDecimal
    {
        $cacheKey = "volume_30d:{$accountId}";
        $cached = Cache::get($cacheKey);
        
        if ($cached) {
            return BigDecimal::of($cached);
        }
        
        // Calculate 30-day trading volume
        $volume = Trade::forAccount($accountId)
            ->recent(24 * 30) // 30 days
            ->sum('value');
        
        $volumeDecimal = BigDecimal::of($volume ?? '0');
        
        // Cache for 1 hour
        Cache::put($cacheKey, $volumeDecimal->__toString(), now()->addHour());
        
        return $volumeDecimal;
    }
    
    private function getVolumeBasedRate(BigDecimal $volume, string $type): BigDecimal
    {
        // Volume-based fee tiers
        $tiers = [
            ['volume' => '1000000', 'maker' => '0.0009', 'taker' => '0.0018'], // > $1M
            ['volume' => '500000', 'maker' => '0.00095', 'taker' => '0.0019'],  // > $500K
            ['volume' => '100000', 'maker' => '0.001', 'taker' => '0.002'],     // > $100K
            ['volume' => '0', 'maker' => self::DEFAULT_MAKER_FEE_PERCENT, 'taker' => self::DEFAULT_TAKER_FEE_PERCENT],
        ];
        
        foreach ($tiers as $tier) {
            if ($volume->isGreaterThanOrEqualTo($tier['volume'])) {
                return BigDecimal::of($tier[$type]);
            }
        }
        
        return BigDecimal::of($type === 'maker' ? self::DEFAULT_MAKER_FEE_PERCENT : self::DEFAULT_TAKER_FEE_PERCENT);
    }
    
    public function calculateMinimumOrderValue(string $baseCurrency, string $quoteCurrency): BigDecimal
    {
        // Minimum order values to ensure fees don't exceed reasonable percentage
        $minimums = [
            'BTC' => '0.0001',  // 0.0001 BTC
            'ETH' => '0.001',   // 0.001 ETH
            'EUR' => '10',      // 10 EUR
            'USD' => '10',      // 10 USD
            'GCU' => '10',      // 10 GCU
        ];
        
        return BigDecimal::of($minimums[$baseCurrency] ?? '1');
    }
}