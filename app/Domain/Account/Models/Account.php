<?php

namespace App\Domain\Account\Models;

use App\Domain\Asset\Models\Asset;
use App\Domain\Custodian\Models\CustodianAccount;
use App\Domain\Shared\Traits\UsesTenantConnection;
use App\Traits\BelongsToTeam;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $uuid
 * @property string $name
 * @property string $user_uuid
 * @property int $balance
 * @property bool $frozen
 * @property string|null $team_uuid
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false)
 * @method static \Illuminate\Database\Eloquent\Builder orderBy(string $column, string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Collection get(array $columns = ['*'])
 * @method static static|null find(mixed $id, array $columns = ['*'])
 * @method static static|null first(array $columns = ['*'])
 * @method static static firstOrFail(array $columns = ['*'])
 * @method static int count(string $columns = '*')
 * @method static bool exists()
 * @method static static create(array $attributes = [])
 * @method static static updateOrCreate(array $attributes, array $values = [])
 */
class Account extends Model
{
    use UsesTenantConnection;
    use HasFactory;
    use HasUuids;
    use BelongsToTeam;

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Create a new factory instance for the model.
     *
     * @return \Database\Factories\AccountFactory
     */
    protected static function newFactory(): \Database\Factories\AccountFactory
    {
        return \Database\Factories\AccountFactory::new();
    }

    public $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'frozen' => 'boolean',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var list<string>
     */
    protected $appends = [];

    /**
     * Get the balance attribute (USD balance for backward compatibility).
     *
     * @return int
     */
    public function getBalanceAttribute(): int
    {
        return $this->getBalance('USD');
    }

    /**
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(
            related: \App\Models\User::class,
            foreignKey: 'user_uuid',
            ownerKey: 'uuid'
        );
    }

    /**
     * Get all balances for this account.
     *
     * @return HasMany<AccountBalance, $this>
     */
    public function balances(): HasMany
    {
        return $this->hasMany(AccountBalance::class, 'account_uuid', 'uuid');
    }

    /**
     * Get balance for a specific asset.
     *
     * @param  string $assetCode
     * @return AccountBalance|null
     */
    public function getBalanceForAsset(string $assetCode): ?AccountBalance
    {
        /** @var AccountBalance|null */
        return $this->balances()->where('asset_code', $assetCode)->first();
    }

    /**
     * Get balance amount for a specific asset.
     *
     * @param  string $assetCode
     * @return int
     */
    public function getBalance(string $assetCode = 'USD'): int
    {
        $balance = $this->getBalanceForAsset($assetCode);

        return $balance ? $balance->balance : 0;
    }

    // Balance manipulation methods removed - use event sourcing via services instead

    /**
     * Check if account has sufficient balance for asset.
     *
     * @param  string $assetCode
     * @param  int    $amount
     * @return bool
     */
    public function hasSufficientBalance(string $assetCode, int $amount): bool
    {
        $balance = $this->getBalanceForAsset($assetCode);

        return $balance && $balance->hasSufficientBalance($amount);
    }

    /**
     * Get all non-zero balances.
     *
     * @return \Illuminate\Database\Eloquent\Collection<int, AccountBalance>
     */
    public function getActiveBalances(): \Illuminate\Database\Eloquent\Collection
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, AccountBalance> */
        return $this->balances()->where('balance', '>', 0)->with('asset')->get();
    }

    /**
     * Get the custodian accounts for this account.
     *
     * @return HasMany<CustodianAccount, $this>
     */
    public function custodianAccounts(): HasMany
    {
        return $this->hasMany(CustodianAccount::class, 'account_uuid', 'uuid');
    }

    /**
     * Get the primary custodian account.
     */
    public function primaryCustodianAccount(): ?CustodianAccount
    {
        /** @var CustodianAccount|null */
        return $this->custodianAccounts()->where('is_primary', true)->first();
    }

    // Legacy balance manipulation methods removed - use event sourcing via WalletService instead

    /**
     * Get transactions from the transaction projection table.
     */
    /**
     * @return HasMany
     */
    public function transactions()
    {
        return $this->hasMany(TransactionProjection::class, 'account_uuid', 'uuid');
    }

    /**
     * Get turnovers for this account.
     */
    public function turnovers(): HasMany
    {
        return $this->hasMany(Turnover::class, 'account_uuid', 'uuid');
    }

    /**
     * Get the account UUID as an AccountUuid value object.
     */
    public function getAggregateUuid(): \App\Domain\Account\DataObjects\AccountUuid
    {
        return \App\Domain\Account\DataObjects\AccountUuid::fromString($this->uuid);
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
