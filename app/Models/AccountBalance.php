<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Domain\Asset\Models\Asset;

class AccountBalance extends Model
{
    use HasFactory;
    
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'account_balances';
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
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
     * Get the account that owns this balance
     */
    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_uuid', 'uuid');
    }
    
    /**
     * Get the asset for this balance
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_code', 'code');
    }
    
    /**
     * Increment the balance
     * NOTE: This method should only be used by projectors for event sourcing
     *
     * @param int $amount
     * @return bool
     */
    public function credit(int $amount): bool
    {
        $this->balance += $amount;
        return $this->save();
    }
    
    /**
     * Decrement the balance
     * NOTE: This method should only be used by projectors for event sourcing
     *
     * @param int $amount
     * @return bool
     * @throws \Exception if insufficient balance
     */
    public function debit(int $amount): bool
    {
        if ($this->balance < $amount) {
            throw new \Exception('Insufficient balance');
        }
        
        $this->balance -= $amount;
        return $this->save();
    }
    
    /**
     * Check if balance is sufficient for amount
     *
     * @param int $amount
     * @return bool
     */
    public function hasSufficientBalance(int $amount): bool
    {
        return $this->balance >= $amount;
    }
    
    /**
     * Get formatted balance with asset symbol
     *
     * @return string
     */
    public function getFormattedBalance(): string
    {
        if ($this->asset) {
            return $this->asset->formatAmount($this->balance);
        }
        
        return number_format($this->balance / 100, 2) . ' ' . $this->asset_code;
    }
    
    /**
     * Scope for balances with positive amounts
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePositive($query)
    {
        return $query->where('balance', '>', 0);
    }
    
    /**
     * Scope for balances by asset
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $assetCode
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForAsset($query, string $assetCode)
    {
        return $query->where('asset_code', $assetCode);
    }
}