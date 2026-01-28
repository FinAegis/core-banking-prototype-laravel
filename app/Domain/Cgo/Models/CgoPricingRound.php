<?php

namespace App\Domain\Cgo\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
class CgoPricingRound extends Model
{
    use UsesTenantConnection;
    use HasFactory;

    protected $fillable = [
        'round_number',
        'name',
        'share_price',
        'max_shares_available',
        'shares_sold',
        'total_raised',
        'pre_money_valuation',
        'post_money_valuation',
        'started_at',
        'ended_at',
        'is_active',
    ];

    protected $casts = [
        'share_price'          => 'decimal:4',
        'max_shares_available' => 'decimal:4',
        'shares_sold'          => 'decimal:4',
        'total_raised'         => 'decimal:2',
        'pre_money_valuation'  => 'decimal:2',
        'post_money_valuation' => 'decimal:2',
        'started_at'           => 'datetime',
        'ended_at'             => 'datetime',
        'is_active'            => 'boolean',
    ];

    public function investments(): HasMany
    {
        return $this->hasMany(CgoInvestment::class, 'round_id');
    }

    public function getRemainingSharesAttribute(): float
    {
        return $this->max_shares_available - $this->shares_sold;
    }

    public function getProgressPercentageAttribute(): float
    {
        if ($this->max_shares_available == 0) {
            return 0;
        }

        return ($this->shares_sold / $this->max_shares_available) * 100;
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public static function getCurrentRound()
    {
        return self::active()->first();
    }

    public static function getNextSharePrice(): float
    {
        $lastRound = self::orderBy('round_number', 'desc')->first();
        if (! $lastRound) {
            return 10.00; // Starting price $10 per share
        }

        // Increase price by 10% each round
        return round($lastRound->share_price * 1.10, 4);
    }

    /**
     * Get the activity logs for this model.
     */
    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function logs()
    {
        return $this->morphMany(\App\Domain\Activity\Models\Activity::class, 'subject');
    }
}
