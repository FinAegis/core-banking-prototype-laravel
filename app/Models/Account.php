<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domain\Asset\Models\Asset;
use App\Models\AccountBalance;
use App\Traits\BelongsToTeam;

class Account extends Model
{
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
     * @var array
     */
    protected $appends = ['balance'];
    
    /**
     * Get the balance attribute (USD balance for backward compatibility)
     *
     * @return int
     */
    public function getBalanceAttribute(): int
    {
        return $this->getBalance('USD');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
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
     * Get all balances for this account
     */
    public function balances(): HasMany
    {
        return $this->hasMany(AccountBalance::class, 'account_uuid', 'uuid');
    }
    
    /**
     * Get balance for a specific asset
     *
     * @param string $assetCode
     * @return AccountBalance|null
     */
    public function getBalanceForAsset(string $assetCode): ?AccountBalance
    {
        return $this->balances()->where('asset_code', $assetCode)->first();
    }
    
    /**
     * Get balance amount for a specific asset
     *
     * @param string $assetCode
     * @return int
     */
    public function getBalance(string $assetCode = 'USD'): int
    {
        $balance = $this->getBalanceForAsset($assetCode);
        return $balance ? $balance->balance : 0;
    }
    
    // Balance manipulation methods removed - use event sourcing via services instead
    
    /**
     * Check if account has sufficient balance for asset
     *
     * @param string $assetCode
     * @param int $amount
     * @return bool
     */
    public function hasSufficientBalance(string $assetCode, int $amount): bool
    {
        $balance = $this->getBalanceForAsset($assetCode);
        return $balance && $balance->hasSufficientBalance($amount);
    }
    
    /**
     * Get all non-zero balances
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getActiveBalances()
    {
        return $this->balances()->positive()->with('asset')->get();
    }

    /**
     * Get the custodian accounts for this account
     */
    public function custodianAccounts(): HasMany
    {
        return $this->hasMany(CustodianAccount::class, 'account_uuid', 'uuid');
    }

    /**
     * Get the primary custodian account
     */
    public function primaryCustodianAccount(): ?CustodianAccount
    {
        return $this->custodianAccounts()->where('is_primary', true)->first();
    }

    // Legacy balance manipulation methods removed - use event sourcing via WalletService instead
    
    /**
     * Get transactions from event sourcing ledger
     * This is a temporary method until we have a proper transaction projection
     */
    public function transactions()
    {
        // For now, return an empty relationship to prevent errors
        // In a proper implementation, this would query the event store or a transaction projection
        return $this->hasMany(Transaction::class, 'account_uuid', 'uuid');
    }
    
    /**
     * Get the account UUID as an AccountUuid value object.
     */
    public function getAggregateUuid(): \App\Domain\Account\DataObjects\AccountUuid
    {
        return \App\Domain\Account\DataObjects\AccountUuid::fromString($this->uuid);
    }
}
