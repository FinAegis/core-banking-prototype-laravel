<?php

declare(strict_types=1);

namespace App\Domain\X402\Models;

use App\Domain\X402\Enums\SettlementStatus;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * x402 payment record — tracks the full lifecycle of a single payment.
 *
 * @property string $id
 * @property string $payer_address
 * @property string $pay_to_address
 * @property string $amount
 * @property string $network
 * @property string $asset
 * @property string $scheme
 * @property SettlementStatus $status
 * @property string|null $transaction_hash
 * @property string $endpoint_method
 * @property string $endpoint_path
 * @property string|null $error_reason
 * @property string|null $error_message
 * @property array<string, mixed>|null $payment_payload
 * @property array<string, mixed>|null $extensions
 * @property int|null $team_id
 * @property \Carbon\Carbon|null $verified_at
 * @property \Carbon\Carbon|null $settled_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class X402Payment extends Model
{
    use HasUuids;

    protected $table = 'x402_payments';

    protected $fillable = [
        'payer_address',
        'pay_to_address',
        'amount',
        'network',
        'asset',
        'scheme',
        'status',
        'transaction_hash',
        'endpoint_method',
        'endpoint_path',
        'error_reason',
        'error_message',
        'payment_payload',
        'extensions',
        'team_id',
        'verified_at',
        'settled_at',
    ];

    protected $hidden = [
        'payment_payload',
    ];

    protected $casts = [
        'status'          => SettlementStatus::class,
        'payment_payload' => 'array',
        'extensions'      => 'array',
        'verified_at'     => 'datetime',
        'settled_at'      => 'datetime',
    ];

    // ----------------------------------------------------------------
    // Relationships
    // ----------------------------------------------------------------

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    // ----------------------------------------------------------------
    // Scopes
    // ----------------------------------------------------------------

    /**
     * @param Builder<X402Payment> $query
     * @return Builder<X402Payment>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', SettlementStatus::PENDING);
    }

    /**
     * @param Builder<X402Payment> $query
     * @return Builder<X402Payment>
     */
    public function scopeSettled(Builder $query): Builder
    {
        return $query->where('status', SettlementStatus::SETTLED);
    }

    /**
     * @param Builder<X402Payment> $query
     * @return Builder<X402Payment>
     */
    public function scopeForEndpoint(Builder $query, string $path): Builder
    {
        return $query->where('endpoint_path', $path);
    }

    /**
     * @param Builder<X402Payment> $query
     * @return Builder<X402Payment>
     */
    public function scopeForPayer(Builder $query, string $address): Builder
    {
        return $query->where('payer_address', $address);
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    /**
     * Whether the payment is still pending verification/settlement.
     */
    public function isPending(): bool
    {
        return $this->status === SettlementStatus::PENDING;
    }

    /**
     * Whether the payment has been successfully settled on-chain.
     */
    public function isSettled(): bool
    {
        return $this->status === SettlementStatus::SETTLED;
    }

    /**
     * Mark the payment as cryptographically verified.
     */
    public function markVerified(): void
    {
        $this->status = SettlementStatus::VERIFIED;
        $this->verified_at = now();
        $this->save();
    }

    /**
     * Mark the payment as settled on-chain with the given transaction hash.
     */
    public function markSettled(string $txHash): void
    {
        $this->status = SettlementStatus::SETTLED;
        $this->transaction_hash = $txHash;
        $this->settled_at = now();
        $this->save();
    }

    /**
     * Mark the payment as failed with reason and message.
     */
    public function markFailed(string $reason, string $message): void
    {
        $this->status = SettlementStatus::FAILED;
        $this->error_reason = $reason;
        $this->error_message = $message;
        $this->save();
    }

    /**
     * Convert the atomic-unit amount to a USD-equivalent float.
     *
     * Assumes USDC with 6 decimal places.
     */
    public function amountInUsd(): float
    {
        return (float) bcdiv($this->amount, '1000000', 6);
    }

    // ----------------------------------------------------------------
    // API Serialization
    // ----------------------------------------------------------------

    /**
     * Format for API response.
     *
     * @return array<string, mixed>
     */
    public function toApiResponse(): array
    {
        return [
            'id'              => $this->id,
            'payerAddress'    => $this->payer_address,
            'payToAddress'    => $this->pay_to_address,
            'amount'          => $this->amount,
            'amountUsd'       => $this->amountInUsd(),
            'network'         => $this->network,
            'asset'           => $this->asset,
            'scheme'          => $this->scheme,
            'status'          => $this->status->value,
            'transactionHash' => $this->transaction_hash,
            'endpoint'        => $this->endpoint_method . ' ' . $this->endpoint_path,
            'error'           => $this->error_reason ? [
                'reason'  => $this->error_reason,
                'message' => $this->error_message,
            ] : null,
            'verifiedAt' => $this->verified_at?->toIso8601String(),
            'settledAt'  => $this->settled_at?->toIso8601String(),
            'createdAt'  => $this->created_at->toIso8601String(),
        ];
    }
}
