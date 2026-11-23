<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One "Add to card" (or a deposit that arrived without one) on its way from
 * the user's USDC deposit to the card balance.
 *
 * @property string $id
 * @property int $user_id
 * @property string|null $card_id null: no card could take the deposit (operator)
 * @property string|null $funding_account_id
 * @property string $provider
 * @property string $source app|external_deposit
 * @property string $asset
 * @property string $network
 * @property string $amount USDC, 6 decimals
 * @property string|null $received_amount USDC, 6 decimals
 * @property string $deposit_address
 * @property string|null $tx_hash
 * @property string $status
 * @property int|null $credited_cents
 * @property int|null $fee_cents
 * @property int|null $issuance_fee_cents
 * @property string|null $provider_deposit_id
 * @property string|null $provider_conversion_id
 * @property string|null $provider_load_id
 * @property Carbon|null $step_started_at
 * @property Carbon|null $conversion_requested_at
 * @property Carbon|null $converted_at
 * @property string|null $last_error
 * @property Carbon $expires_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $created_at
 */
class CardTopUp extends Model
{
    use HasUuids;
    use UsesTenantConnection;

    public const SOURCE_APP = 'app';

    public const SOURCE_EXTERNAL = 'external_deposit';

    public const STATUS_AWAITING_DEPOSIT = 'awaiting_deposit';

    public const STATUS_DEPOSIT_RECEIVED = 'deposit_received';

    public const STATUS_CONVERTING = 'converting';

    public const STATUS_LOADING = 'loading';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_FAILED = 'failed';

    public const STATUS_NEEDS_ATTENTION = 'needs_attention';

    /** Money has arrived and is moving through Lepo. */
    public const IN_FLIGHT = [self::STATUS_DEPOSIT_RECEIVED, self::STATUS_CONVERTING, self::STATUS_LOADING];

    /** Still waiting for a deposit, or moving money. */
    public const OPEN = [self::STATUS_AWAITING_DEPOSIT, ...self::IN_FLIGHT];

    protected $table = 'card_topups';

    protected $fillable = [
        'user_id', 'card_id', 'funding_account_id', 'provider', 'source', 'asset', 'network', 'amount', 'received_amount',
        'deposit_address', 'tx_hash', 'status', 'credited_cents', 'fee_cents', 'issuance_fee_cents', 'provider_deposit_id',
        'provider_conversion_id', 'provider_load_id', 'step_started_at', 'conversion_requested_at', 'converted_at', 'last_error', 'expires_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'amount'                  => 'decimal:6',
            'received_amount'         => 'decimal:6',
            'credited_cents'          => 'integer',
            'fee_cents'               => 'integer',
            'issuance_fee_cents'      => 'integer',
            'step_started_at'         => 'datetime',
            'conversion_requested_at' => 'datetime',
            'converted_at'            => 'datetime',
            'expires_at'              => 'datetime',
            'completed_at'            => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Card, $this>
     */
    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
