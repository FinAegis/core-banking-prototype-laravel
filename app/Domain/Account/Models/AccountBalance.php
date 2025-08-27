<?php

declare(strict_types=1);

namespace App\Domain\Account\Models;

use App\Domain\Asset\Models\Asset;
use Database\Factories\AccountBalanceFactory;
use Exception;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $account_uuid
 * @property string $asset_code
 * @property int $balance
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
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
class AccountBalance extends Model
{
    use HasFactory;

    /**
     * Create a new factory instance for the model.
     *
     * @return AccountBalanceFactory
     */
    protected static function newFactory(): AccountBalanceFactory
    {
        return AccountBalanceFactory::new();
    }

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'account_balances';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'account_uuid',
        'asset_code',
        'balance',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'balance' => 'integer',
    ];

    /**
     * Get the account that owns this balance.
     *
     * @return BelongsTo<Account, $this>
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_uuid', 'uuid');
    }

    /**
     * Get the asset for this balance.
     *
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_code', 'code');
    }

    /**
     * Increment the balance
     * NOTE: This method should only be used by projectors for event sourcing.
     */
    public function credit(int $amount): bool
    {
        $this->balance += $amount;

        return $this->save();
    }

    /**
     * Decrement the balance
     * NOTE: This method should only be used by projectors for event sourcing.
     *
     * @throws Exception if insufficient balance
     */
    public function debit(int $amount): bool
    {
        if ($this->balance < $amount) {
            throw new Exception('Insufficient balance');
        }

        $this->balance -= $amount;

        return $this->save();
    }

    /**
     * Check if balance is sufficient for amount.
     */
    public function hasSufficientBalance(int $amount): bool
    {
        return $this->balance >= $amount;
    }

    /**
     * Get formatted balance with asset symbol.
     */
    public function getFormattedBalance(): string
    {
        if ($this->asset) {
            return $this->asset->formatAmount($this->balance);
        }

        return number_format($this->balance / 100, 2) . ' ' . $this->asset_code;
    }

    /**
     * Scope for balances with positive amounts.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePositive($query)
    {
        return $query->where('balance', '>', 0);
    }

    /**
     * Scope for balances by asset.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForAsset($query, string $assetCode)
    {
        return $query->where('asset_code', $assetCode);
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
