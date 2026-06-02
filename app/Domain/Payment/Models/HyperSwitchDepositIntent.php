<?php

declare(strict_types=1);

namespace App\Domain\Payment\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Maps a HyperSwitch payment to the deposit aggregate it funds, so the webhook
 * can complete + credit the right account. Central/default connection (NOT
 * tenant) — read inside the webhook's idempotency transaction.
 *
 * @property int    $id
 * @property string $hyperswitch_payment_id
 * @property string $deposit_uuid
 * @property string $account_uuid
 * @property string|null $user_uuid
 * @property int    $amount_cents
 * @property string $currency
 * @property string $status
 */
class HyperSwitchDepositIntent extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $table = 'hyperswitch_deposit_intents';

    protected $fillable = [
        'hyperswitch_payment_id',
        'deposit_uuid',
        'account_uuid',
        'user_uuid',
        'amount_cents',
        'currency',
        'status',
    ];

    protected $casts = [
        'amount_cents' => 'integer',
    ];
}
