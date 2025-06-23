<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Domain\Asset\Models\Asset;
use App\Models\AccountBalance;
use App\Domain\Account\DataObjects\Money;

class Account extends Model
{
    use HasFactory;
    use HasUuids;

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
    
    /**
     * Add balance to account (DEPRECATED - use event sourcing via services)
     * @deprecated Use WalletService->deposit() instead
     */
    public function addBalance(string $assetCode, mixed $amount): bool
    {
        $amount = (int) $amount; // Convert to integer
        
        if ($assetCode === 'USD') {
            // Legacy USD balance
            $this->balance += $amount;
            $this->save();
        }
        
        // Multi-asset balance
        $balance = AccountBalance::firstOrCreate(
            [
                'account_uuid' => $this->uuid,
                'asset_code' => $assetCode,
            ],
            ['balance' => 0]
        );
        
        return $balance->credit($amount);
    }
    
    /**
     * Subtract balance from account (DEPRECATED - use event sourcing via services)  
     * @deprecated Use WalletService->withdraw() instead
     */
    public function subtractBalance(string $assetCode, mixed $amount): bool
    {
        $amount = (int) $amount; // Convert to integer
        
        if ($assetCode === 'USD') {
            // Legacy USD balance
            if ($this->balance < $amount) {
                return false;
            }
            $this->balance -= $amount;
            $this->save();
        }
        
        // Multi-asset balance
        $balance = $this->getBalanceForAsset($assetCode);
        if (!$balance || $balance->balance < $amount) {
            return false;
        }
        
        return $balance->debit($amount);
    }
    
    /**
     * Add money to account (DEPRECATED - use event sourcing via services)
     * @deprecated Use WalletService->deposit() instead  
     */
    public function addMoney(Money $money, string $assetCode = 'USD'): bool
    {
        return $this->addBalance($assetCode, $money->getAmount());
    }
    
    /**
     * Subtract money from account (DEPRECATED - use event sourcing via services)
     * @deprecated Use WalletService->withdraw() instead
     */
    public function subtractMoney(Money $money, string $assetCode = 'USD'): bool
    {
        return $this->subtractBalance($assetCode, $money->getAmount());
    }
    
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
     * Get the account UUID as an AccountUuid value object.
     */
    public function getAggregateUuid(): \App\Domain\Account\DataObjects\AccountUuid
    {
        return \App\Domain\Account\DataObjects\AccountUuid::fromString($this->uuid);
    }
}
