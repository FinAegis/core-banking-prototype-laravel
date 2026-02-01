<?php

declare(strict_types=1);

namespace App\Domain\FinancialInstitution\Models;

use App\Domain\FinancialInstitution\Enums\PartnerTier;
use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $uuid
 * @property string $partner_id
 * @property string $invoice_number
 * @property \Carbon\Carbon $period_start
 * @property \Carbon\Carbon $period_end
 * @property string $billing_cycle
 * @property string $status
 * @property string $tier
 * @property float $base_amount_usd
 * @property float $discount_amount_usd
 * @property string|null $discount_reason
 * @property int $total_api_calls
 * @property int $included_api_calls
 * @property int $overage_api_calls
 * @property float $overage_amount_usd
 * @property array|null $line_items
 * @property float $additional_charges_usd
 * @property float $subtotal_usd
 * @property float $tax_amount_usd
 * @property float $tax_rate
 * @property float $total_amount_usd
 * @property string $display_currency
 * @property float $exchange_rate
 * @property float $total_amount_display
 * @property string|null $payment_method
 * @property string|null $payment_reference
 * @property \Carbon\Carbon|null $paid_at
 * @property \Carbon\Carbon $due_date
 * @property string|null $pdf_path
 * @property \Carbon\Carbon|null $pdf_generated_at
 * @property string|null $notes
 * @property array|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property FinancialInstitutionPartner $partner
 *
 * @method static \Illuminate\Database\Eloquent\Builder where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder pending()
 * @method static \Illuminate\Database\Eloquent\Builder paid()
 * @method static \Illuminate\Database\Eloquent\Builder overdue()
 * @method static \Illuminate\Database\Eloquent\Collection get(array $columns = ['*'])
 * @method static static|null find(mixed $id, array $columns = ['*'])
 * @method static static|null first(array $columns = ['*'])
 * @method static static create(array $attributes = [])
 */
class PartnerInvoice extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;
    use UsesTenantConnection;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'uuid',
        'partner_id',
        'invoice_number',
        'period_start',
        'period_end',
        'billing_cycle',
        'status',
        'tier',
        'base_amount_usd',
        'discount_amount_usd',
        'discount_reason',
        'total_api_calls',
        'included_api_calls',
        'overage_api_calls',
        'overage_amount_usd',
        'line_items',
        'additional_charges_usd',
        'subtotal_usd',
        'tax_amount_usd',
        'tax_rate',
        'total_amount_usd',
        'display_currency',
        'exchange_rate',
        'total_amount_display',
        'payment_method',
        'payment_reference',
        'paid_at',
        'due_date',
        'pdf_path',
        'pdf_generated_at',
        'notes',
        'metadata',
    ];

    protected $casts = [
        'period_start'           => 'date',
        'period_end'             => 'date',
        'due_date'               => 'date',
        'paid_at'                => 'datetime',
        'pdf_generated_at'       => 'datetime',
        'line_items'             => 'array',
        'metadata'               => 'array',
        'base_amount_usd'        => 'decimal:2',
        'discount_amount_usd'    => 'decimal:2',
        'overage_amount_usd'     => 'decimal:2',
        'additional_charges_usd' => 'decimal:2',
        'subtotal_usd'           => 'decimal:2',
        'tax_amount_usd'         => 'decimal:2',
        'tax_rate'               => 'decimal:2',
        'total_amount_usd'       => 'decimal:2',
        'exchange_rate'          => 'decimal:6',
        'total_amount_display'   => 'decimal:2',
        'total_api_calls'        => 'integer',
        'included_api_calls'     => 'integer',
        'overage_api_calls'      => 'integer',
    ];

    protected $attributes = [
        'status'                 => self::STATUS_DRAFT,
        'discount_amount_usd'    => 0,
        'overage_api_calls'      => 0,
        'overage_amount_usd'     => 0,
        'additional_charges_usd' => 0,
        'tax_amount_usd'         => 0,
        'tax_rate'               => 0,
        'display_currency'       => 'USD',
        'exchange_rate'          => 1.0,
    ];

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * Boot method to generate invoice numbers.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($invoice) {
            if (empty($invoice->invoice_number)) {
                $invoice->invoice_number = static::generateInvoiceNumber();
            }
        });
    }

    /**
     * Generate a unique invoice number.
     */
    public static function generateInvoiceNumber(): string
    {
        $prefix = 'INV-' . now()->format('Ym');
        $latestInvoice = static::where('invoice_number', 'like', $prefix . '%')
            ->orderBy('invoice_number', 'desc')
            ->first();

        if ($latestInvoice) {
            $lastNumber = (int) substr($latestInvoice->invoice_number, -5);
            $newNumber = str_pad((string) ($lastNumber + 1), 5, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '00001';
        }

        return $prefix . '-' . $newNumber;
    }

    /**
     * Get the partner that owns this invoice.
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(FinancialInstitutionPartner::class, 'partner_id');
    }

    /**
     * Scope to filter pending invoices.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to filter paid invoices.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopePaid($query)
    {
        return $query->where('status', self::STATUS_PAID);
    }

    /**
     * Scope to filter overdue invoices.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeOverdue($query)
    {
        return $query->where('status', self::STATUS_OVERDUE);
    }

    /**
     * Get the tier enum.
     */
    public function getTierEnum(): ?PartnerTier
    {
        return PartnerTier::tryFrom($this->tier);
    }

    /**
     * Check if invoice is paid.
     */
    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /**
     * Check if invoice is overdue.
     */
    public function isOverdue(): bool
    {
        return $this->status === self::STATUS_OVERDUE ||
            ($this->status === self::STATUS_PENDING && $this->due_date->isPast());
    }

    /**
     * Check if invoice can be paid.
     */
    public function canBePaid(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_OVERDUE]);
    }

    /**
     * Mark invoice as paid.
     *
     * @param string|null $paymentMethod
     * @param string|null $paymentReference
     */
    public function markAsPaid(?string $paymentMethod = null, ?string $paymentReference = null): void
    {
        $this->update([
            'status'            => self::STATUS_PAID,
            'paid_at'           => now(),
            'payment_method'    => $paymentMethod,
            'payment_reference' => $paymentReference,
        ]);
    }

    /**
     * Mark invoice as overdue.
     */
    public function markAsOverdue(): void
    {
        if ($this->status === self::STATUS_PENDING) {
            $this->update(['status' => self::STATUS_OVERDUE]);
        }
    }

    /**
     * Calculate totals.
     */
    public function calculateTotals(): void
    {
        $subtotal = $this->base_amount_usd
            - $this->discount_amount_usd
            + $this->overage_amount_usd
            + $this->additional_charges_usd;

        $taxAmount = $subtotal * ($this->tax_rate / 100);
        $totalUsd = $subtotal + $taxAmount;
        $totalDisplay = $totalUsd * $this->exchange_rate;

        $this->update([
            'subtotal_usd'         => $subtotal,
            'tax_amount_usd'       => $taxAmount,
            'total_amount_usd'     => $totalUsd,
            'total_amount_display' => $totalDisplay,
        ]);
    }

    /**
     * Get status badge color.
     */
    public function getStatusBadgeColor(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT     => 'gray',
            self::STATUS_PENDING   => 'yellow',
            self::STATUS_PAID      => 'green',
            self::STATUS_OVERDUE   => 'red',
            self::STATUS_CANCELLED => 'gray',
            self::STATUS_REFUNDED  => 'blue',
            default                => 'gray',
        };
    }

    /**
     * Get days until due or days overdue.
     */
    public function getDaysUntilDue(): int
    {
        return (int) now()->diffInDays($this->due_date, false);
    }
}
