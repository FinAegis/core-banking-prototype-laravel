<?php

declare(strict_types=1);

namespace App\Domain\MachinePay\Models;

use App\Domain\MachinePay\Enums\MppSettlementStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * MPP payment record.
 *
 * Tracks the lifecycle of a payment from challenge issuance
 * through verification and settlement.
 *
 * @property string $uuid
 * @property string $challenge_id
 * @property string $rail
 * @property int    $amount_cents
 * @property string $currency
 * @property string $status
 * @property string|null $payer_identifier
 * @property string|null $settlement_reference
 * @property string|null $endpoint_method
 * @property string|null $endpoint_path
 * @property array<string, mixed>|null $payment_payload
 * @property string|null $payload_hash
 * @property string|null $error_message
 * @property int|null $team_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class MppPayment extends Model
{
    use HasUuids;

    protected $table = 'mpp_payments';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'uuid',
        'challenge_id',
        'rail',
        'amount_cents',
        'currency',
        'status',
        'payer_identifier',
        'settlement_reference',
        'endpoint_method',
        'endpoint_path',
        'payment_payload',
        'payload_hash',
        'error_message',
        'team_id',
    ];

    /**
     * The stored credential (with its proof of payment) and its hash are
     * never serialized.
     *
     * @var list<string>
     */
    protected $hidden = [
        'payment_payload',
        'payload_hash',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_cents'    => 'integer',
            'payment_payload' => 'array',
            'team_id'         => 'integer',
        ];
    }

    public function isSettled(): bool
    {
        return $this->status === MppSettlementStatus::SETTLED->value;
    }

    public function isFailed(): bool
    {
        return $this->status === MppSettlementStatus::FAILED->value;
    }
}
