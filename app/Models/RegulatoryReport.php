<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $report_type
 * @property string|null $agent_id
 * @property array $report_data
 * @property string $status
 * @property Carbon $generated_at
 * @property Carbon|null $submitted_at
 * @property string|null $submission_reference
 * @property string|null $regulatory_authority
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class RegulatoryReport extends Model
{
    use HasFactory;

    protected $table = 'regulatory_reports';

    protected $fillable = [
        'report_type',
        'agent_id',
        'report_data',
        'status',
        'generated_at',
        'submitted_at',
        'submission_reference',
        'regulatory_authority',
    ];

    protected $casts = [
        'report_data'  => 'array',
        'generated_at' => 'datetime',
        'submitted_at' => 'datetime',
    ];

    /**
     * Report type constants.
     */
    public const TYPE_CTR = 'CTR';

    public const TYPE_SAR = 'SAR';

    public const TYPE_AML_COMPLIANCE = 'AML_COMPLIANCE';

    public const TYPE_KYC_AUDIT = 'KYC_AUDIT';

    public const TYPE_TRANSACTION_MONITORING = 'TRANSACTION_MONITORING';

    /**
     * Status constants.
     */
    public const STATUS_DRAFT = 'draft';

    public const STATUS_GENERATED = 'generated';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_REJECTED = 'rejected';

    /**
     * Get the agent associated with the report.
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class, 'agent_id', 'agent_id');
    }

    /**
     * Check if report is CTR.
     */
    public function isCTR(): bool
    {
        return $this->report_type === self::TYPE_CTR;
    }

    /**
     * Check if report is SAR.
     */
    public function isSAR(): bool
    {
        return $this->report_type === self::TYPE_SAR;
    }

    /**
     * Check if report is submitted.
     */
    public function isSubmitted(): bool
    {
        return in_array($this->status, [self::STATUS_SUBMITTED, self::STATUS_ACKNOWLEDGED], true);
    }

    /**
     * Get report summary.
     */
    public function getSummary(): array
    {
        return [
            'type'         => $this->report_type,
            'status'       => $this->status,
            'generated_at' => $this->generated_at->toDateTimeString(),
            'submitted_at' => $this->submitted_at?->toDateTimeString(),
            'agent_id'     => $this->agent_id,
            'reference'    => $this->submission_reference,
        ];
    }

    /**
     * Get transaction count from report data.
     */
    public function getTransactionCount(): int
    {
        $transactions = $this->report_data['transactions'] ?? [];

        return count($transactions);
    }

    /**
     * Get total amount from report data.
     */
    public function getTotalAmount(): float
    {
        if ($this->isCTR()) {
            return (float) ($this->report_data['summary']['total_amount'] ?? 0);
        }

        if ($this->isSAR()) {
            return (float) ($this->report_data['total_suspicious_amount'] ?? 0);
        }

        return 0.0;
    }

    /**
     * Mark report as submitted.
     */
    public function markAsSubmitted(?string $reference = null, ?string $authority = null): void
    {
        $this->update([
            'status'               => self::STATUS_SUBMITTED,
            'submitted_at'         => now(),
            'submission_reference' => $reference,
            'regulatory_authority' => $authority,
        ]);
    }

    /**
     * Mark report as acknowledged.
     */
    public function markAsAcknowledged(?string $reference = null): void
    {
        $this->update([
            'status'               => self::STATUS_ACKNOWLEDGED,
            'submission_reference' => $reference ?? $this->submission_reference,
        ]);
    }

    /**
     * Mark report as rejected.
     */
    public function markAsRejected(?string $reason = null): void
    {
        $this->update([
            'status'      => self::STATUS_REJECTED,
            'report_data' => array_merge($this->report_data, [
                'rejection_reason' => $reason,
                'rejected_at'      => now()->toIso8601String(),
            ]),
        ]);
    }

    /**
     * Scope for pending submission.
     */
    public function scopePendingSubmission($query)
    {
        return $query->where('status', self::STATUS_GENERATED);
    }

    /**
     * Scope for specific report type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('report_type', $type);
    }

    /**
     * Scope for date range.
     */
    public function scopeGeneratedBetween($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('generated_at', [$startDate, $endDate]);
    }
}
