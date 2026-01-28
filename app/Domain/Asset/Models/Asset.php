<?php

declare(strict_types=1);

namespace App\Domain\Asset\Models;

use App\Domain\Account\Models\AccountBalance;
use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @method static \Illuminate\Database\Eloquent\Builder where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder whereDate(string $column, string|\DateTimeInterface $value)
 * @method static \Illuminate\Database\Eloquent\Builder whereMonth(string $column, string|\DateTimeInterface $value)
 * @method static \Illuminate\Database\Eloquent\Builder whereYear(string $column, mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder whereIn(string $column, mixed $values)
 * @method static \Illuminate\Database\Eloquent\Builder whereBetween(string $column, array $values)
 * @method static \Illuminate\Database\Eloquent\Builder whereNull(string $column)
 * @method static \Illuminate\Database\Eloquent\Builder whereNotNull(string $column)
 * @method static \Illuminate\Database\Eloquent\Builder orderBy(string $column, string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder latest(string $column = null)
 * @method static \Illuminate\Database\Eloquent\Builder oldest(string $column = null)
 * @method static \Illuminate\Database\Eloquent\Builder with(array|string $relations)
 * @method static \Illuminate\Database\Eloquent\Builder withCount(array|string $relations)
 * @method static \Illuminate\Database\Eloquent\Builder has(string $relation, string $operator = '>=', int $count = 1, string $boolean = 'and', \Closure $callback = null)
 * @method static \Illuminate\Database\Eloquent\Builder distinct(string $column = null)
 * @method static \Illuminate\Database\Eloquent\Builder groupBy(string ...$groups)
 * @method static \Illuminate\Database\Eloquent\Builder limit(int $value)
 * @method static \Illuminate\Database\Eloquent\Builder take(int $value)
 * @method static \Illuminate\Database\Eloquent\Builder skip(int $value)
 * @method static \Illuminate\Database\Eloquent\Builder offset(int $value)
 * @method static \Illuminate\Database\Eloquent\Builder selectRaw(string $expression, array $bindings = [])
 * @method static \Illuminate\Database\Eloquent\Builder lockForUpdate()
 * @method static static updateOrCreate(array $attributes, array $values = [])
 * @method static static firstOrCreate(array $attributes, array $values = [])
 * @method static static firstOrNew(array $attributes, array $values = [])
 * @method static static create(array $attributes = [])
 * @method static static forceCreate(array $attributes)
 * @method static static|null find(mixed $id, array $columns = ['*'])
 * @method static static|null first(array $columns = ['*'])
 * @method static static firstOrFail(array $columns = ['*'])
 * @method static static findOrFail(mixed $id, array $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection get(array $columns = ['*'])
 * @method static \Illuminate\Support\Collection pluck(string $column, string|null $key = null)
 * @method static int count(string $columns = '*')
 * @method static mixed sum(string $column)
 * @method static mixed avg(string $column)
 * @method static mixed max(string $column)
 * @method static mixed min(string $column)
 * @method static bool exists()
 * @method static bool doesntExist()
 * @method static bool delete()
 * @method static bool forceDelete()
 * @method static bool restore()
 * @method static bool update(array $attributes = [])
 * @method static int increment(string $column, float|int $amount = 1, array $extra = [])
 * @method static int decrement(string $column, float|int $amount = 1, array $extra = [])
 * @method static \Illuminate\Database\Eloquent\Builder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder query()
 */
class Asset extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     *
     * @return \Database\Factories\AssetFactory
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
        'is_tradeable',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'precision'    => 'integer',
        'is_active'    => 'boolean',
        'is_basket'    => 'boolean',
        'is_tradeable' => 'boolean',
        'metadata'     => 'array',
    ];

    /**
     * Asset types.
     */
    public const TYPE_FIAT = 'fiat';

    public const TYPE_CRYPTO = 'crypto';

    public const TYPE_COMMODITY = 'commodity';

    public const TYPE_CUSTOM = 'custom';

    public const TYPE_BASKET = 'basket';

    /**
     * Get all valid asset types.
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
     * Get all account balances for this asset.
     */
    public function accountBalances(): HasMany
    {
        return $this->hasMany(AccountBalance::class, 'asset_code', 'code');
    }

    /**
     * Get exchange rates where this asset is the source.
     */
    public function exchangeRatesFrom(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'from_asset_code', 'code');
    }

    /**
     * Get exchange rates where this asset is the target.
     */
    public function exchangeRatesTo(): HasMany
    {
        return $this->hasMany(ExchangeRate::class, 'to_asset_code', 'code');
    }

    /**
     * Get the symbol from metadata.
     */
    public function getSymbol(): ?string
    {
        return $this->metadata['symbol'] ?? null;
    }

    /**
     * Get the symbol attribute (accessor).
     */
    public function getSymbolAttribute(): ?string
    {
        return $this->getSymbol();
    }

    /**
     * Format an amount according to the asset's precision.
     *
     * @param  int  $amount  Amount in smallest unit
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
     * Convert a decimal amount to the smallest unit.
     */
    public function toSmallestUnit(float $amount): int
    {
        return (int) round($amount * (10 ** $this->precision));
    }

    /**
     * Convert from smallest unit to decimal.
     */
    public function fromSmallestUnit(int $amount): float
    {
        return $amount / (10 ** $this->precision);
    }

    /**
     * Check if the asset is a fiat currency.
     */
    public function isFiat(): bool
    {
        return $this->type === self::TYPE_FIAT;
    }

    /**
     * Check if the asset is a cryptocurrency.
     */
    public function isCrypto(): bool
    {
        return $this->type === self::TYPE_CRYPTO;
    }

    /**
     * Check if the asset is a commodity.
     */
    public function isCommodity(): bool
    {
        return $this->type === self::TYPE_COMMODITY;
    }

    /**
     * Scope for active assets.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for assets by type.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /**
     * Get the activity logs for this model.
     */
    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function logs()
    {
        return $this->morphMany(\App\Domain\Activity\Models\Activity::class, 'subject');
    }
}
