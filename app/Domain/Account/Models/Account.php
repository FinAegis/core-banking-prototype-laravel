<?php

namespace App\Domain\Account\Models;

use App\Domain\Asset\Models\Asset;
use App\Domain\Custodian\Models\CustodianAccount;
use App\Domain\Shared\Traits\UsesTenantConnection;
use App\Models\User;
use App\Support\StaffRoles;
use App\Traits\BelongsToTeam;
use Illuminate\Contracts\Auth\Authenticatable;
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
 * @property bool $frozen_by_owner true when the owner placed the current freeze
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
        'frozen'          => 'boolean',
        'frozen_by_owner' => 'boolean',
    ];

    protected static function booted(): void
    {
        // Any change of `frozen` that does not say who made it (the event
        // projector, the admin panel, compliance monitoring, an unfreeze)
        // clears the owner marker, so an unknown freeze is a staff freeze.
        static::updating(function (self $account): void {
            if ($account->isDirty('frozen') && ! $account->isDirty('frozen_by_owner')) {
                $account->frozen_by_owner = false;
            }
        });
    }

    /**
     * Freeze the account and record who placed the freeze. A staff freeze
     * always takes over, so the owner cannot lift it. An owner freeze never
     * replaces an existing freeze.
     */
    public function recordFreeze(bool $byStaff): void
    {
        if ($byStaff) {
            $this->forceFill(['frozen' => true, 'frozen_by_owner' => false])->save();

            return;
        }

        if ($this->frozen) {
            return;
        }

        // Conditional update: a staff or compliance freeze that landed after
        // this model was loaded keeps its staff marker.
        static::query()
            ->withoutGlobalScopes()
            ->whereKey($this->getKey())
            ->where('frozen', false)
            ->update(['frozen' => true, 'frozen_by_owner' => true]);

        $this->refresh();
    }

    /**
     * Whether the user may lift the current freeze: staff
     * (StaffRoles::OPERATORS) always, the owner only a freeze they placed.
     */
    public function mayBeUnfrozenBy(?Authenticatable $user): bool
    {
        if (StaffRoles::userHasAny($user, StaffRoles::OPERATORS)) {
            return true;
        }

        if (! $this->isOwnedBy($user)) {
            return false;
        }

        return ! $this->frozen || $this->frozen_by_owner;
    }

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
            related: User::class,
            foreignKey: 'user_uuid',
            ownerKey: 'uuid'
        );
    }

    /**
     * Whether the given (authenticated) user owns this account.
     *
     * Same rule as the REST account endpoints: accounts.user_uuid must equal
     * the user's uuid. A missing user or an account without an owner never
     * matches.
     */
    public function isOwnedBy(?Authenticatable $user): bool
    {
        if (! $user instanceof User) {
            return false;
        }

        $ownerUuid = (string) ($this->user_uuid ?? '');

        return $ownerUuid !== '' && hash_equals($ownerUuid, (string) $user->uuid);
    }

    /**
     * Find an account by uuid only when the given user owns it.
     *
     * Returns null both for unknown accounts and for accounts owned by
     * someone else, so callers can answer without revealing which one.
     * The lookup ignores the BelongsToTeam scope: user_uuid is the ownership
     * check, and an owner's account may have no team or another team.
     */
    public static function findOwnedBy(string $uuid, ?Authenticatable $user): ?self
    {
        if (! $user instanceof User || $uuid === '') {
            return null;
        }

        /** @var self|null $account */
        $account = static::query()->withoutGlobalScope('team')->where('uuid', $uuid)->first();

        return $account !== null && $account->isOwnedBy($user) ? $account : null;
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
     * @return HasMany<TransactionProjection, $this>
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
