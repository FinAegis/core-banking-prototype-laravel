<?php

declare(strict_types=1);

namespace App\Domain\SMS\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string      $id
 * @property string      $provider
 * @property string      $provider_id
 * @property string      $to
 * @property string      $from
 * @property string      $message
 * @property int         $parts
 * @property string      $status
 * @property string      $price_usdc
 * @property string      $country_code
 * @property string|null $payment_rail
 * @property string|null $payment_id
 * @property string|null $payment_receipt
 * @property bool        $test_mode
 * @property \Illuminate\Support\Carbon|null $delivered_at
 * @property \Illuminate\Support\Carbon      $created_at
 * @property \Illuminate\Support\Carbon      $updated_at
 */
class SmsMessage extends Model
{
    use HasUuids;

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    protected $table = 'sms_messages';

    protected $fillable = [
        'provider',
        'provider_id',
        'to',
        'from',
        'message',
        'parts',
        'status',
        'price_usdc',
        'country_code',
        'payment_rail',
        'payment_id',
        'payment_receipt',
        'test_mode',
        'delivered_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'parts'        => 'integer',
            'test_mode'    => 'boolean',
            'delivered_at' => 'datetime',
        ];
    }
}
