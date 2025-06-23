<?php

declare(strict_types=1);

namespace App\Domain\Asset\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class ExchangeRate extends Model
{
    use HasFactory;
    
    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\ExchangeRateFactory::new();
    }
    
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'exchange_rates';
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'from_asset_code',
        'to_asset_code',
        'rate',
        'source',
        'valid_at',
        'expires_at',
        'is_active',
        'metadata',
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'rate' => 'decimal:10',
        'valid_at' => 'datetime',
        'expires_at' => 'datetime',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];
    
    /**
     * Exchange rate sources
     */
    public const SOURCE_MANUAL = 'manual';
    public const SOURCE_API = 'api';
    public const SOURCE_ORACLE = 'oracle';
    public const SOURCE_MARKET = 'market';
    
    /**
     * Get all valid sources
     *
     * @return array<string>
     */
    public static function getSources(): array
    {
        return [
            self::SOURCE_MANUAL,
            self::SOURCE_API,
            self::SOURCE_ORACLE,
            self::SOURCE_MARKET,
        ];
    }
    
    /**
     * Get the from asset
     */
    public function fromAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'from_asset_code', 'code');
    }
    
    /**
     * Get the to asset
     */
    public function toAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'to_asset_code', 'code');
    }
    
    /**
     * Convert an amount from the base asset to the target asset
     *
     * @param int $amount Amount in smallest unit
     * @return int Converted amount in smallest unit
     */
    public function convert(int $amount): int
    {
        return (int) round($amount * (float) $this->rate);
    }
    
    /**
     * Get the inverse rate
     *
     * @return float
     */
    public function getInverseRate(): float
    {
        return 1 / (float) $this->rate;
    }
    
    /**
     * Check if the rate is currently valid
     *
     * @return bool
     */
    public function isValid(): bool
    {
        $now = now();
        
        return $this->is_active 
            && $this->valid_at <= $now 
            && ($this->expires_at === null || $this->expires_at > $now);
    }
    
    /**
     * Check if the rate has expired
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at <= now();
    }
    
    /**
     * Get the age of the rate in minutes
     *
     * @return int
     */
    public function getAgeInMinutes(): int
    {
        return (int) $this->valid_at->diffInMinutes(now());
    }
    
    /**
     * Scope for active rates
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    /**
     * Scope for valid rates (active and within time range)
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeValid($query)
    {
        $now = now();
        
        return $query->where('is_active', true)
            ->where('valid_at', '<=', $now)
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', $now);
            });
    }
    
    /**
     * Scope for rates between specific assets
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $fromAsset
     * @param string $toAsset
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBetween($query, string $fromAsset, string $toAsset)
    {
        return $query->where('from_asset_code', $fromAsset)
                     ->where('to_asset_code', $toAsset);
    }
    
    /**
     * Scope for rates by source
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $source
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBySource($query, string $source)
    {
        return $query->where('source', $source);
    }
    
    /**
     * Scope for latest rates first
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('valid_at', 'desc');
    }
    
    /**
     * Get latest exchange rates grouped by asset pairs
     *
     * @return object
     */
    public static function getLatestRates(): object
    {
        $rates = self::valid()->latest()->get()->groupBy(['from_asset_code', 'to_asset_code']);
        
        $result = new \stdClass();
        foreach ($rates as $fromAsset => $toAssets) {
            $result->$fromAsset = new \stdClass();
            foreach ($toAssets as $toAsset => $rateRecords) {
                $result->$fromAsset->$toAsset = $rateRecords->first()->rate;
            }
        }
        
        return $result;
    }
    
    /**
     * Get exchange rate between two assets
     *
     * @param string $fromAsset
     * @param string $toAsset
     * @return float|null
     */
    public static function getRate(string $fromAsset, string $toAsset): ?float
    {
        if ($fromAsset === $toAsset) {
            return 1.0;
        }
        
        $rate = self::valid()->between($fromAsset, $toAsset)->latest()->first();
        
        return $rate ? (float) $rate->rate : null;
    }
}