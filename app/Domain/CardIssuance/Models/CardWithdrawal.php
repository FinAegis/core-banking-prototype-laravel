<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One "Send to wallet": US dollars leave a card, become USDC and go to the
 * user's own wallet address, which was copied onto the row at the start.
 *
 * @property string $id
 * @property int $user_id
 * @property string $card_id
 * @property string $provider
 * @property string $network
 * @property string $asset
 * @property string $destination_address
 * @property string $destination_address_uuid the blockchain_addresses.uuid that was pinned
 * @property string $amount US dollars leaving the card, 2 decimals
 * @property bool $withdraw_all
 * @property bool $close_card
 * @property array<string, mixed>|null $estimate snapshot of the estimate at the start
 * @property string $status
 * @property int|null $card_balance_before_cents read just before the unload call
 * @property int|null $unloaded_cents
 * @property int|null $unload_fee_cents
 * @property int|null $conversion_fee_cents
 * @property string|null $usdc_balance_before read just before the exchange call
 * @property string|null $usdc_amount USDC the exchange produced, 6 decimals
 * @property string|null $withdrawal_fee USDC
 * @property string|null $withdrawal_amount USDC asked to be sent
 * @property string|null $provider_actual_amount USDC the provider reported as sent
 * @property string|null $received_amount USDC that reached the wallet
 * @property string|null $tx_hash
 * @property bool $card_closed
 * @property string|null $provider_unload_id
 * @property string|null $provider_trade_id
 * @property string|null $provider_withdrawal_id
 * @property string|null $provider_transfer_id
 * @property string|null $provider_status
 * @property string|null $provider_verification_token encrypted at rest
 * @property string|null $unload_key
 * @property string|null $exchange_key
 * @property string|null $withdrawal_key
 * @property string|null $confirm_key
 * @property int $withdrawal_attempts
 * @property Carbon|null $step_started_at
 * @property Carbon|null $unload_requested_at
 * @property Carbon|null $unloaded_at
 * @property Carbon|null $exchange_requested_at
 * @property Carbon|null $exchanged_at
 * @property Carbon|null $withdrawal_requested_at
 * @property Carbon|null $code_sent_at
 * @property Carbon|null $code_expires_at
 * @property string|null $code_sent_to masked e-mail
 * @property int $code_attempts wrong codes entered
 * @property int|null $code_attempts_left
 * @property int $code_resends
 * @property Carbon|null $last_resend_at
 * @property Carbon|null $confirm_requested_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $resumed_at
 * @property Carbon|null $card_close_attempted_at
 * @property Carbon|null $completed_at
 * @property string|null $last_error short reason code, never a provider payload
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class CardWithdrawal extends Model
{
    use HasUuids;
    use UsesTenantConnection;

    public const STATUS_UNLOADING = 'unloading';

    public const STATUS_CONVERTING = 'converting';

    public const STATUS_AWAITING_CODE = 'awaiting_code';

    public const STATUS_SENDING = 'sending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    public const STATUS_NEEDS_ATTENTION = 'needs_attention';

    /** Money is moving or an outcome is still unclear: the user's other money flows wait. */
    public const ACTIVE = [self::STATUS_UNLOADING, self::STATUS_CONVERTING, self::STATUS_AWAITING_CODE, self::STATUS_SENDING, self::STATUS_NEEDS_ATTENTION];

    /** The pipeline still has work to do on these. */
    public const IN_PROGRESS = [self::STATUS_UNLOADING, self::STATUS_CONVERTING, self::STATUS_AWAITING_CODE, self::STATUS_SENDING];

    protected $table = 'card_withdrawals';

    protected $fillable = [];

    /** @var array<string, mixed> */
    protected $attributes = [
        'withdraw_all'        => false,
        'close_card'          => false,
        'card_closed'         => false,
        'withdrawal_attempts' => 0,
        'code_attempts'       => 0,
        'code_resends'        => 0,
    ];

    protected $hidden = ['provider_verification_token', 'unload_key', 'exchange_key', 'withdrawal_key', 'confirm_key'];

    protected function casts(): array
    {
        return [
            'amount'                      => 'decimal:2',
            'withdraw_all'                => 'boolean',
            'close_card'                  => 'boolean',
            'card_closed'                 => 'boolean',
            'estimate'                    => 'array',
            'card_balance_before_cents'   => 'integer',
            'unloaded_cents'              => 'integer',
            'unload_fee_cents'            => 'integer',
            'conversion_fee_cents'        => 'integer',
            'usdc_balance_before'         => 'decimal:6',
            'usdc_amount'                 => 'decimal:6',
            'withdrawal_fee'              => 'decimal:6',
            'withdrawal_amount'           => 'decimal:6',
            'provider_actual_amount'      => 'decimal:6',
            'received_amount'             => 'decimal:6',
            'provider_verification_token' => 'encrypted',
            'withdrawal_attempts'         => 'integer',
            'code_attempts'               => 'integer',
            'code_attempts_left'          => 'integer',
            'code_resends'                => 'integer',
            'step_started_at'             => 'datetime',
            'unload_requested_at'         => 'datetime',
            'unloaded_at'                 => 'datetime',
            'exchange_requested_at'       => 'datetime',
            'exchanged_at'                => 'datetime',
            'withdrawal_requested_at'     => 'datetime',
            'code_sent_at'                => 'datetime',
            'code_expires_at'             => 'datetime',
            'last_resend_at'              => 'datetime',
            'confirm_requested_at'        => 'datetime',
            'confirmed_at'                => 'datetime',
            'resumed_at'                  => 'datetime',
            'card_close_attempted_at'     => 'datetime',
            'completed_at'                => 'datetime',
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

    /**
     * @return HasMany<CardWithdrawalEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(CardWithdrawalEvent::class, 'withdrawal_id');
    }
}
