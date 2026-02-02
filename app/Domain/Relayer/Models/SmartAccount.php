<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Smart Account model for ERC-4337 account abstraction.
 *
 * Tracks counterfactual smart account addresses and their deployment status.
 * Smart accounts are created deterministically from owner address + salt.
 *
 * @property string $id
 * @property int $user_id
 * @property string $owner_address
 * @property string $account_address
 * @property string $network
 * @property bool $deployed
 * @property string|null $deploy_tx_hash
 * @property int $nonce
 * @property int $pending_ops
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class SmartAccount extends Model
{
    use HasUuids;

    protected $table = 'smart_accounts';

    protected $fillable = [
        'user_id',
        'owner_address',
        'account_address',
        'network',
        'deployed',
        'deploy_tx_hash',
        'nonce',
        'pending_ops',
    ];

    protected $casts = [
        'deployed'    => 'boolean',
        'nonce'       => 'integer',
        'pending_ops' => 'integer',
    ];

    /**
     * Get the user that owns this smart account.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if this account needs deployment.
     */
    public function needsDeployment(): bool
    {
        return ! $this->deployed;
    }

    /**
     * Increment the nonce after a successful operation.
     */
    public function incrementNonce(): void
    {
        $this->increment('nonce');
    }

    /**
     * Increment pending operations count.
     */
    public function incrementPendingOps(): void
    {
        $this->increment('pending_ops');
    }

    /**
     * Decrement pending operations count.
     */
    public function decrementPendingOps(): void
    {
        $this->decrement('pending_ops');
        if ($this->pending_ops < 0) {
            $this->pending_ops = 0;
            $this->save();
        }
    }

    /**
     * Mark the account as deployed.
     */
    public function markAsDeployed(string $txHash): void
    {
        $this->update([
            'deployed'       => true,
            'deploy_tx_hash' => $txHash,
        ]);
    }

    /**
     * Scope for deployed accounts.
     *
     * @param \Illuminate\Database\Eloquent\Builder<SmartAccount> $query
     * @return \Illuminate\Database\Eloquent\Builder<SmartAccount>
     */
    public function scopeDeployed($query)
    {
        return $query->where('deployed', true);
    }

    /**
     * Scope for undeployed (counterfactual) accounts.
     *
     * @param \Illuminate\Database\Eloquent\Builder<SmartAccount> $query
     * @return \Illuminate\Database\Eloquent\Builder<SmartAccount>
     */
    public function scopeUndeployed($query)
    {
        return $query->where('deployed', false);
    }

    /**
     * Scope by network.
     *
     * @param \Illuminate\Database\Eloquent\Builder<SmartAccount> $query
     * @return \Illuminate\Database\Eloquent\Builder<SmartAccount>
     */
    public function scopeForNetwork($query, string $network)
    {
        return $query->where('network', $network);
    }

    /**
     * Format the model for API responses.
     *
     * @return array<string, mixed>
     */
    public function toApiResponse(): array
    {
        return [
            'id'              => $this->id,
            'owner_address'   => $this->owner_address,
            'account_address' => $this->account_address,
            'network'         => $this->network,
            'deployed'        => $this->deployed,
            'deploy_tx_hash'  => $this->deploy_tx_hash,
            'nonce'           => $this->nonce,
            'pending_ops'     => $this->pending_ops,
            'created_at'      => $this->created_at->toIso8601String(),
        ];
    }
}
