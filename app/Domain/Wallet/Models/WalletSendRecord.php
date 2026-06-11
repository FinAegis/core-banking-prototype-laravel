<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $public_id
 * @property int $user_id
 * @property string $network
 * @property string $asset
 * @property string $amount
 * @property string $sender_address
 * @property string $recipient_address
 * @property string $status
 * @property string|null $tx_hash
 * @property string|null $user_op_hash
 * @property string|null $idempotency_key
 * @property string|null $quote_id
 * @property string|null $sponsored_fee_raw   Sponsored network fee in native units (lamports/wei) as a decimal string — bcmath only, never float
 * @property string|null $sponsored_fee_asset Asset the sponsored fee was paid in (SOL, MATIC, ETH-BASE, ETH-ARB, ETH)
 * @property string|null $error_code
 * @property string|null $error_message
 * @property array<string, mixed>|null $metadata
 * @property \Illuminate\Support\Carbon|null $submitted_at
 * @property \Illuminate\Support\Carbon|null $confirmed_at
 * @property \Illuminate\Support\Carbon|null $failed_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class WalletSendRecord extends Model
{
    use HasUuids;

    protected $table = 'wallet_send_records';

    protected $fillable = [
        'public_id',
        'user_id',
        'network',
        'asset',
        'amount',
        'sender_address',
        'recipient_address',
        'status',
        'tx_hash',
        'user_op_hash',
        'idempotency_key',
        'quote_id',
        'sponsored_fee_raw',
        'sponsored_fee_asset',
        'error_code',
        'error_message',
        'metadata',
        'submitted_at',
        'confirmed_at',
        'failed_at',
    ];

    protected $casts = [
        'metadata'     => 'array',
        'submitted_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'failed_at'    => 'datetime',
    ];

    public const STATUS_PENDING = 'pending';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_FAILED = 'failed';

    /**
     * Map record to the response shape mobile expects (matches PaymentIntent::toApiResponse()).
     *
     * @return array<string, mixed>
     */
    public function toApiResponse(): array
    {
        return [
            'intentId'      => $this->public_id,
            'merchantId'    => null,
            'merchant'      => null,
            'asset'         => $this->asset,
            'network'       => $this->network,
            'amount'        => $this->amount,
            'status'        => strtoupper($this->status === self::STATUS_PENDING ? 'pending' : $this->status),
            'shieldEnabled' => false,
            'feesEstimate'  => null,
            'createdAt'     => $this->created_at->toIso8601String(),
            'expiresAt'     => $this->created_at->copy()->addMinutes(15)->toIso8601String(),
            'tx'            => $this->tx_hash ? [
                'hash'        => $this->tx_hash,
                'explorerUrl' => $this->buildExplorerUrl(),
            ] : null,
            'confirmations'         => $this->status === self::STATUS_CONFIRMED ? 1 : 0,
            'requiredConfirmations' => 1,
            'error'                 => $this->error_code ? [
                'code'    => $this->error_code,
                'message' => $this->error_message,
            ] : null,
            'quoteId' => $this->quote_id,
        ];
    }

    /**
     * Flat, snake_case status payload for the per-intent polling endpoint
     * (GET /api/v1/wallet/transactions/by-intent/{intentId}).
     *
     * `status` is the lowercase lifecycle value (pending/submitted/confirmed/
     * failed). `error_message` is best-effort human-readable text; mobile
     * should branch on the stable `error_code`.
     *
     * @return array<string, mixed>
     */
    public function toIntentStatusResponse(): array
    {
        return [
            'intent_id'     => $this->public_id,
            'status'        => $this->status,
            'network'       => $this->network,
            'asset'         => $this->asset,
            'amount'        => $this->amount,
            'tx_hash'       => $this->tx_hash,
            'explorer_url'  => $this->buildExplorerUrl(),
            'error_code'    => $this->error_code,
            'error_message' => $this->error_message,
            'created_at'    => $this->created_at->toIso8601String(),
            'submitted_at'  => $this->submitted_at?->toIso8601String(),
            'confirmed_at'  => $this->confirmed_at?->toIso8601String(),
            'failed_at'     => $this->failed_at?->toIso8601String(),
        ];
    }

    private function buildExplorerUrl(): ?string
    {
        if ($this->tx_hash === null) {
            return null;
        }

        return match (strtolower($this->network)) {
            'solana'   => "https://solscan.io/tx/{$this->tx_hash}",
            'polygon'  => "https://polygonscan.com/tx/{$this->tx_hash}",
            'base'     => "https://basescan.org/tx/{$this->tx_hash}",
            'arbitrum' => "https://arbiscan.io/tx/{$this->tx_hash}",
            'ethereum' => "https://etherscan.io/tx/{$this->tx_hash}",
            default    => null,
        };
    }
}
