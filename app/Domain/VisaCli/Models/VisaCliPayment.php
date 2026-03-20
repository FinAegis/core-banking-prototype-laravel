<?php

declare(strict_types=1);

namespace App\Domain\VisaCli\Models;

use App\Domain\VisaCli\Enums\VisaCliPaymentStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $agent_id
 * @property int|null $invoice_id
 * @property string $url
 * @property int $amount_cents
 * @property string $currency
 * @property string $status
 * @property string|null $card_identifier
 * @property string|null $payment_reference
 * @property array<string, mixed>|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class VisaCliPayment extends Model
{
    use HasUuids;
    use SoftDeletes;

    protected $table = 'visa_cli_payments';

    protected $fillable = [
        'agent_id',
        'invoice_id',
        'url',
        'amount_cents',
        'currency',
        'status',
        'card_identifier',
        'payment_reference',
        'metadata',
    ];

    protected $casts = [
        'metadata'     => 'array',
        'amount_cents' => 'integer',
        'status'       => VisaCliPaymentStatus::class,
    ];

    protected $attributes = [
        'currency' => 'USD',
        'status'   => VisaCliPaymentStatus::PENDING,
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo<\App\Domain\FinancialInstitution\Models\PartnerInvoice, $this>
     */
    public function invoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Domain\FinancialInstitution\Models\PartnerInvoice::class, 'invoice_id');
    }

    public function isCompleted(): bool
    {
        return $this->status === VisaCliPaymentStatus::COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === VisaCliPaymentStatus::FAILED;
    }

    public function isPending(): bool
    {
        return in_array($this->status, [VisaCliPaymentStatus::PENDING, VisaCliPaymentStatus::PROCESSING]);
    }

    public function markCompleted(string $paymentReference): void
    {
        $this->update([
            'status'            => VisaCliPaymentStatus::COMPLETED,
            'payment_reference' => $paymentReference,
        ]);
    }

    public function markFailed(string $reason): void
    {
        $this->update([
            'status'   => VisaCliPaymentStatus::FAILED,
            'metadata' => array_merge($this->metadata ?? [], ['failure_reason' => $reason]),
        ]);
    }
}
