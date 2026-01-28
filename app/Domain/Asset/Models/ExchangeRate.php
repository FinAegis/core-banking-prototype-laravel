<?php

declare(strict_types=1);

namespace App\Domain\Asset\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use stdClass;

/**
 * @method static \Illuminate\Database\Eloquent\Builder where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false)
 * @method static \Illuminate\Database\Eloquent\Builder whereNull(string $column)
 * @method static \Illuminate\Database\Eloquent\Builder whereNotNull(string $column)
 * @method static \Illuminate\Database\Eloquent\Builder whereDate(string $column, mixed $operator, string|\DateTimeInterface|null $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder whereMonth(string $column, mixed $operator, string|\DateTimeInterface|null $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder whereYear(string $column, mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder orderBy(string $column, string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder latest(string $column = null)
 * @method static \Illuminate\Database\Eloquent\Builder oldest(string $column = null)
 * @method static \Illuminate\Database\Eloquent\Builder with(array|string $relations)
 * @method static \Illuminate\Database\Eloquent\Builder distinct(string $column = null)
 * @method static \Illuminate\Database\Eloquent\Builder groupBy(string ...$groups)
 * @method static \Illuminate\Database\Eloquent\Builder having(string $column, string $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder selectRaw(string $expression, array $bindings = [])
 * @method static \Illuminate\Database\Eloquent\Collection get(array $columns = ['*'])
 * @method static static|null find(mixed $id, array $columns = ['*'])
 * @method static static|null first(array $columns = ['*'])
 * @method static static firstOrFail(array $columns = ['*'])
 * @method static static firstOrCreate(array $attributes, array $values = [])
 * @method static static firstOrNew(array $attributes, array $values = [])
 * @method static static updateOrCreate(array $attributes, array $values = [])
 * @method static static create(array $attributes = [])
 * @method static int count(string $columns = '*')
 * @method static mixed sum(string $column)
 * @method static mixed avg(string $column)
 * @method static mixed max(string $column)
 * @method static mixed min(string $column)
 * @method static bool exists()
 * @method static bool doesntExist()
 * @method static \Illuminate\Support\Collection pluck(string $column, string|null $key = null)
 * @method static bool delete()
 * @method static bool update(array $values)
 * @method static \Illuminate\Database\Eloquent\Builder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder query()
 */
class ExchangeRate extends Model
{
    use UsesTenantConnection;
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
        'rate'       => 'decimal:10',
        'valid_at'   => 'datetime',
        'expires_at' => 'datetime',
        'is_active'  => 'boolean',
        'metadata'   => 'array',
    ];

    /**
     * Exchange rate sources.
     */
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_API = 'api';

    public const SOURCE_ORACLE = 'oracle';

    public const SOURCE_MARKET = 'market';

    /**
     * Get all valid sources.
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
     * Get the from asset.
     */
    public function fromAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'from_asset_code', 'code');
    }

    /**
     * Get the to asset.
     */
    public function toAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'to_asset_code', 'code');
    }

    /**
     * Convert an amount from the base asset to the target asset.
     *
     * @param  int  $amount  Amount in smallest unit
     * @return int Converted amount in smallest unit
     */
    public function convert(int $amount): int
    {
        return (int) round($amount * (float) $this->rate);
    }

    /**
     * Get the inverse rate.
     */
    public function getInverseRate(): float
    {
        return 1 / (float) $this->rate;
    }

    /**
     * Check if the rate is currently valid.
     */
    public function isValid(): bool
    {
        $now = now();

        return $this->is_active
            && $this->valid_at <= $now
            && ($this->expires_at === null || $this->expires_at > $now);
    }

    /**
     * Check if the rate has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at <= now();
    }

    /**
     * Get the age of the rate in minutes.
     */
    public function getAgeInMinutes(): int
    {
        return (int) $this->valid_at->diffInMinutes(now(), false);
    }

    /**
     * Scope for active rates.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for valid rates (active and within time range).
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeValid($query)
    {
        $now = now();

        return $query->where('is_active', true)
            ->where('valid_at', '<=', $now)
            ->where(
                function ($q) use ($now) {
                    $q->whereNull('expires_at')
                        ->orWhere('expires_at', '>', $now);
                }
            );
    }

    /**
     * Scope for rates between specific assets.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBetween($query, string $fromAsset, string $toAsset)
    {
        return $query->where('from_asset_code', $fromAsset)
            ->where('to_asset_code', $toAsset);
    }

    /**
     * Scope for rates by source.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBySource($query, string $source)
    {
        return $query->where('source', $source);
    }

    /**
     * Scope for latest rates first.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeLatest($query)
    {
        return $query->orderBy('valid_at', 'desc');
    }

    /**
     * Get latest exchange rates grouped by asset pairs.
     */
    public static function getLatestRates(): object
    {
        $rates = self::valid()->latest()->get()->groupBy(['from_asset_code', 'to_asset_code']);

        $result = new stdClass();
        foreach ($rates as $fromAsset => $toAssets) {
            $result->$fromAsset = new stdClass();
            foreach ($toAssets as $toAsset => $rateRecords) {
                $result->$fromAsset->$toAsset = $rateRecords->first()->rate;
            }
        }

        return $result;
    }

    /**
     * Get exchange rate between two assets.
     */
    public static function getRate(string $fromAsset, string $toAsset): ?float
    {
        if ($fromAsset === $toAsset) {
            return 1.0;
        }

        /** @var static|null $rate */
        $rate = self::valid()->between($fromAsset, $toAsset)->latest()->first();

        return $rate ? (float) $rate->rate : null;
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
