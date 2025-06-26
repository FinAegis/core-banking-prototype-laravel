<?php

declare(strict_types=1);

namespace App\Domain\Asset\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\AccountBalance;

class Asset extends Model
{
    use HasFactory;
    
    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory()
    {
        return \Database\Factories\AssetFactory::new();
    }
    
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'assets';
    
    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'code';
    
    /**
     * Indicates if the primary key is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;
    
    /**
     * The data type of the primary key.
     *
     * @var string
     */
    protected $keyType = 'string';
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'code',
        'name',
        'type',
        'precision',
        'is_active',
        'is_basket',
        'metadata',
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'precision' => 'integer',
        'is_active' => 'boolean',
        'is_basket' => 'boolean',
        'metadata' => 'array',
    ];
    
    /**
     * Asset types
     */
    public const TYPE_FIAT = 'fiat';
    public const TYPE_CRYPTO = 'crypto';
    public const TYPE_COMMODITY = 'commodity';
    public const TYPE_CUSTOM = 'custom';
    public const TYPE_BASKET = 'basket';
    
    /**
     * Get all valid asset types
     *
     * @return array<string>
     */
    public static function getTypes(): array
    {
        return [
            self::TYPE_FIAT,
            self::TYPE_CRYPTO,
            self::TYPE_COMMODITY,
            self::TYPE_CUSTOM,
        ];
    }
    
    /**
     * Get all account balances for this asset
     */
    public function accountBalances(): HasMany
    {
        return $this->hasMany(AccountBalance::class, 'asset_code', 'code');
    }
    
    /**
     * Get exchange rates where this asset is the source
     */
    public function exchangeRatesFrom(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'from_asset_code', 'code');
    }
    
    /**
     * Get exchange rates where this asset is the target
     */
    public function exchangeRatesTo(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'to_asset_code', 'code');
    }
    
    /**
     * Get the symbol from metadata
     *
     * @return string|null
     */
    public function getSymbol(): ?string
    {
        return $this->metadata['symbol'] ?? null;
    }
    
    /**
     * Get the symbol attribute (accessor)
     *
     * @return string|null
     */
    public function getSymbolAttribute(): ?string
    {
        return $this->getSymbol();
    }
    
    /**
     * Format an amount according to the asset's precision
     *
     * @param int $amount Amount in smallest unit
     * @return string Formatted amount
     */
    public function formatAmount(int $amount): string
    {
        $divisor = 10 ** $this->precision;
        $formatted = number_format($amount / $divisor, $this->precision);
        
        if ($symbol = $this->getSymbol()) {
            return $symbol . $formatted;
        }
        
        return $formatted . ' ' . $this->code;
    }
    
    /**
     * Convert a decimal amount to the smallest unit
     *
     * @param float $amount
     * @return int
     */
    public function toSmallestUnit(float $amount): int
    {
        return (int) round($amount * (10 ** $this->precision));
    }
    
    /**
     * Convert from smallest unit to decimal
     *
     * @param int $amount
     * @return float
     */
    public function fromSmallestUnit(int $amount): float
    {
        return $amount / (10 ** $this->precision);
    }
    
    /**
     * Check if the asset is a fiat currency
     *
     * @return bool
     */
    public function isFiat(): bool
    {
        return $this->type === self::TYPE_FIAT;
    }
    
    /**
     * Check if the asset is a cryptocurrency
     *
     * @return bool
     */
    public function isCrypto(): bool
    {
        return $this->type === self::TYPE_CRYPTO;
    }
    
    /**
     * Check if the asset is a commodity
     *
     * @return bool
     */
    public function isCommodity(): bool
    {
        return $this->type === self::TYPE_COMMODITY;
    }
    
    /**
     * Scope for active assets
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    
    /**
     * Scope for assets by type
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }
    
}