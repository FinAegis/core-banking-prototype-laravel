<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Models;

use App\Domain\Wallet\ValueObjects\PendingSigningRequest as PendingSigningRequestVO;
use App\Domain\Wallet\ValueObjects\TransactionData;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Pending signing request model.
 *
 * @property string $id
 * @property int $user_id
 * @property string $association_id
 * @property string $status
 * @property array<string, mixed> $transaction_data
 * @property string $raw_data_to_sign
 * @property string $chain
 * @property string|null $signature
 * @property string|null $public_key
 * @property string|null $signed_transaction_hash
 * @property string|null $error_message
 * @property array<string, mixed>|null $metadata
 * @property Carbon $expires_at
 * @property Carbon|null $completed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read User $user
 * @property-read HardwareWalletAssociation $association
 */
class PendingSigningRequest extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'pending_signing_requests';

    protected $fillable = [
        'user_id',
        'association_id',
        'status',
        'transaction_data',
        'raw_data_to_sign',
        'chain',
        'signature',
        'public_key',
        'signed_transaction_hash',
        'error_message',
        'metadata',
        'expires_at',
        'completed_at',
    ];

    protected $casts = [
        'transaction_data' => 'array',
        'metadata'         => 'array',
        'expires_at'       => 'datetime',
        'completed_at'     => 'datetime',
    ];

    /**
     * Get the user that owns this signing request.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the hardware wallet association.
     *
     * @return BelongsTo<HardwareWalletAssociation, $this>
     */
    public function association(): BelongsTo
    {
        return $this->belongsTo(HardwareWalletAssociation::class, 'association_id');
    }

    /**
     * Scope to get pending requests.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', PendingSigningRequestVO::STATUS_PENDING);
    }

    /**
     * Scope to get non-expired requests.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeNotExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope to get expired requests.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeExpired(Builder $query): Builder
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope to get processable requests.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeProcessable(Builder $query): Builder
    {
        return $query->whereIn('status', [
            PendingSigningRequestVO::STATUS_PENDING,
            PendingSigningRequestVO::STATUS_AWAITING_DEVICE,
        ])->where('expires_at', '>', now());
    }

    /**
     * Check if this request is expired.
     */
    public function isExpired(): bool
    {
        return now()->isAfter($this->expires_at);
    }

    /**
     * Check if this request is pending.
     */
    public function isPending(): bool
    {
        return $this->status === PendingSigningRequestVO::STATUS_PENDING;
    }

    /**
     * Check if this request is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === PendingSigningRequestVO::STATUS_COMPLETED;
    }

    /**
     * Check if this request has failed.
     */
    public function isFailed(): bool
    {
        return $this->status === PendingSigningRequestVO::STATUS_FAILED;
    }

    /**
     * Check if this request can be processed.
     */
    public function canBeProcessed(): bool
    {
        return ! $this->isExpired()
            && in_array($this->status, [
                PendingSigningRequestVO::STATUS_PENDING,
                PendingSigningRequestVO::STATUS_AWAITING_DEVICE,
            ], true);
    }

    /**
     * Mark as awaiting device connection.
     */
    public function markAsAwaitingDevice(): void
    {
        $this->update(['status' => PendingSigningRequestVO::STATUS_AWAITING_DEVICE]);
    }

    /**
     * Mark as signing in progress.
     */
    public function markAsSigning(): void
    {
        $this->update(['status' => PendingSigningRequestVO::STATUS_SIGNING]);
    }

    /**
     * Mark as completed with signature.
     */
    public function markAsCompleted(string $signature, string $publicKey, string $transactionHash): void
    {
        $this->update([
            'status'                  => PendingSigningRequestVO::STATUS_COMPLETED,
            'signature'               => $signature,
            'public_key'              => $publicKey,
            'signed_transaction_hash' => $transactionHash,
            'completed_at'            => now(),
        ]);
    }

    /**
     * Mark as failed with error.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status'        => PendingSigningRequestVO::STATUS_FAILED,
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Mark as expired.
     */
    public function markAsExpired(): void
    {
        $this->update(['status' => PendingSigningRequestVO::STATUS_EXPIRED]);
    }

    /**
     * Mark as cancelled.
     */
    public function markAsCancelled(): void
    {
        $this->update(['status' => PendingSigningRequestVO::STATUS_CANCELLED]);
    }

    /**
     * Get the transaction data as a value object.
     */
    public function getTransactionDataVO(): TransactionData
    {
        $data = $this->transaction_data;

        return new TransactionData(
            from: $data['from'] ?? '',
            to: $data['to'] ?? '',
            value: $data['value'] ?? '0',
            chain: $data['chain'] ?? $this->chain,
            data: $data['data'] ?? null,
            gasLimit: $data['gas_limit'] ?? null,
            gasPrice: $data['gas_price'] ?? null,
            maxFeePerGas: $data['max_fee_per_gas'] ?? null,
            maxPriorityFeePerGas: $data['max_priority_fee_per_gas'] ?? null,
            nonce: $data['nonce'] ?? null,
            metadata: $data['metadata'] ?? []
        );
    }

    /**
     * Convert to value object.
     */
    public function toValueObject(): PendingSigningRequestVO
    {
        return PendingSigningRequestVO::fromArray([
            'request_id'              => $this->id,
            'user_id'                 => (string) $this->user_id,
            'association_id'          => $this->association_id,
            'status'                  => $this->status,
            'transaction_data'        => $this->transaction_data,
            'raw_data_to_sign'        => $this->raw_data_to_sign,
            'expires_at'              => $this->expires_at,
            'signature'               => $this->signature,
            'signed_transaction_hash' => $this->signed_transaction_hash,
            'error_message'           => $this->error_message,
            'metadata'                => $this->metadata ?? [],
        ]);
    }
}
