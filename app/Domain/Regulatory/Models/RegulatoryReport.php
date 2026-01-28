<?php

namespace App\Domain\Regulatory\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @method static \Illuminate\Database\Eloquent\Builder where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder whereIn(string $column, mixed $values, string $boolean = 'and', bool $not = false)
 * @method static \Illuminate\Database\Eloquent\Builder whereNull(string $column)
 * @method static \Illuminate\Database\Eloquent\Builder whereNotNull(string $column)
 * @method static \Illuminate\Database\Eloquent\Builder whereDate(string $column, mixed $operator, string|\DateTimeInterface|null $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder whereMonth(string $column, mixed $operator, string|\DateTimeInterface|null $value = null)
 * @method static \Illuminate\Database\Eloquent\Builder whereYear(string $column, mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder orderBy(string $column, string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder latest(string $column = null)
 * @method static \Illuminate\Database\Eloquent\Builder oldest(string $column = null)
 * @method static \Illuminate\Database\Eloquent\Builder with(array|string $relations)
 * @method static \Illuminate\Database\Eloquent\Builder distinct(string $column = null)
 * @method static \Illuminate\Database\Eloquent\Builder groupBy(string ...$groups)
 * @method static \Illuminate\Database\Eloquent\Builder having(string $column, string $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder selectRaw(string $expression, array $bindings = [])
 * @method static \Illuminate\Database\Eloquent\Collection get(array $columns = ['*'])
 * @method static static|null find(mixed $id, array $columns = ['*'])
 * @method static static|null first(array $columns = ['*'])
 * @method static static firstOrFail(array $columns = ['*'])
 * @method static static firstOrCreate(array $attributes, array $values = [])
 * @method static static firstOrNew(array $attributes, array $values = [])
 * @method static static updateOrCreate(array $attributes, array $values = [])
 * @method static static create(array $attributes = [])
 * @method static int count(string $columns = '*')
 * @method static mixed sum(string $column)
 * @method static mixed avg(string $column)
 * @method static mixed max(string $column)
 * @method static mixed min(string $column)
 * @method static bool exists()
 * @method static bool doesntExist()
 * @method static \Illuminate\Support\Collection pluck(string $column, string|null $key = null)
 * @method static bool delete()
 * @method static bool update(array $values)
 * @method static \Illuminate\Database\Eloquent\Builder newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder query()
 */
class RegulatoryReport extends Model
{
    use UsesTenantConnection;
    use HasUuids;

    protected $fillable = [
        'report_id',
        'report_type',
        'jurisdiction',
        'reporting_period_start',
        'reporting_period_end',
        'status',
        'priority',
        'file_path',
        'file_format',
        'file_size',
        'file_hash',
        'generated_at',
        'submitted_at',
        'submitted_by',
        'submission_reference',
        'submission_response',
        'reviewed_at',
        'reviewed_by',
        'review_notes',
        'requires_correction',
        'corrections_required',
        'regulation_reference',
        'is_mandatory',
        'due_date',
        'is_overdue',
        'days_overdue',
        'report_data',
        'record_count',
        'total_amount',
        'entities_included',
        'risk_indicators',
        'audit_trail',
        'tags',
    ];

    protected $casts = [
        'reporting_period_start' => 'date',
        'reporting_period_end'   => 'date',
        'generated_at'           => 'datetime',
        'submitted_at'           => 'datetime',
        'reviewed_at'            => 'datetime',
        'due_date'               => 'datetime',
        'submission_response'    => 'array',
        'corrections_required'   => 'array',
        'report_data'            => 'array',
        'entities_included'      => 'array',
        'risk_indicators'        => 'array',
        'audit_trail'            => 'array',
        'tags'                   => 'array',
        'is_mandatory'           => 'boolean',
        'is_overdue'             => 'boolean',
        'requires_correction'    => 'boolean',
        'total_amount'           => 'decimal:2',
    ];

    // Report types
    public const TYPE_CTR = 'CTR'; // Currency Transaction Report

    public const TYPE_SAR = 'SAR'; // Suspicious Activity Report

    public const TYPE_OFAC = 'OFAC'; // Office of Foreign Assets Control

    public const TYPE_BSA = 'BSA'; // Bank Secrecy Act

    public const TYPE_CDD = 'CDD'; // Customer Due Diligence

    public const TYPE_EDD = 'EDD'; // Enhanced Due Diligence

    public const TYPE_KYC = 'KYC'; // Know Your Customer

    public const TYPE_AML = 'AML'; // Anti-Money Laundering

    public const TYPE_FATCA = 'FATCA'; // Foreign Account Tax Compliance Act

    public const TYPE_CRS = 'CRS'; // Common Reporting Standard

    public const TYPE_GDPR = 'GDPR'; // General Data Protection Regulation

    public const TYPE_PSD2 = 'PSD2'; // Payment Services Directive 2

    public const TYPE_MIFID = 'MIFID'; // Markets in Financial Instruments Directive

    // Statuses
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    // Jurisdictions
    public const JURISDICTION_US = 'US';

    public const JURISDICTION_EU = 'EU';

    public const JURISDICTION_UK = 'UK';

    public const JURISDICTION_CA = 'CA';

    public const JURISDICTION_AU = 'AU';

    public const JURISDICTION_SG = 'SG';

    public const JURISDICTION_HK = 'HK';

    // File formats
    public const FORMAT_JSON = 'json';

    public const FORMAT_XML = 'xml';

    public const FORMAT_CSV = 'csv';

    public const FORMAT_PDF = 'pdf';

    public const FORMAT_XLSX = 'xlsx';

    // Boot method to auto-generate report ID
    protected static function boot()
    {
        parent::boot();

        static::creating(
            function ($report) {
                if (empty($report->report_id)) {
                    $report->report_id = self::generateReportId($report->report_type);
                }

                // Check if overdue
                if ($report->due_date) {
                    $report->checkOverdueStatus();
                }
            }
        );

        static::updating(
            function ($report) {
                // Update overdue status
                if ($report->due_date) {
                    $report->checkOverdueStatus();
                }
            }
        );
    }

    // Relationships
    public function filingRecords(): HasMany
    {
        return $this->hasMany(RegulatoryFilingRecord::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function latestFiling()
    {
        return $this->hasOne(RegulatoryFilingRecord::class)->latestOfMany();
    }

    // Helper methods
    public static function generateReportId(string $type): string
    {
        $year = now()->format('Y');
        $lastReport = self::where('report_type', $type)
            ->whereYear('created_at', $year)
            ->orderBy('report_id', 'desc')
            ->first();

        if ($lastReport) {
            $lastNumber = intval(substr($lastReport->report_id, -4));
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return "{$type}-{$year}-{$newNumber}";
    }

    public function checkOverdueStatus(): void
    {
        if ($this->due_date && $this->status !== self::STATUS_SUBMITTED && $this->status !== self::STATUS_ACCEPTED) {
            $now = Carbon::now();
            $dueDate = Carbon::parse($this->due_date);

            $this->is_overdue = $now->isAfter($dueDate);
            $this->days_overdue = $this->is_overdue ? $now->diffInDays($dueDate) : 0;
        } else {
            $this->is_overdue = false;
            $this->days_overdue = 0;
        }
    }

    public function markAsSubmitted(string $submittedBy, ?string $reference = null): void
    {
        $this->update(
            [
            'status'               => self::STATUS_SUBMITTED,
            'submitted_at'         => now(),
            'submitted_by'         => $submittedBy,
            'submission_reference' => $reference,
            ]
        );

        $this->addAuditEntry(
            'submitted',
            [
            'submitted_by' => $submittedBy,
            'reference'    => $reference,
            ]
        );
    }

    public function markAsReviewed(string $reviewedBy, string $notes, bool $requiresCorrection = false): void
    {
        $this->update(
            [
            'reviewed_at'         => now(),
            'reviewed_by'         => $reviewedBy,
            'review_notes'        => $notes,
            'requires_correction' => $requiresCorrection,
            'status'              => $requiresCorrection ? self::STATUS_DRAFT : self::STATUS_PENDING_REVIEW,
            ]
        );

        $this->addAuditEntry(
            'reviewed',
            [
            'reviewed_by'         => $reviewedBy,
            'requires_correction' => $requiresCorrection,
            ]
        );
    }

    public function addAuditEntry(string $action, array $data = []): void
    {
        $auditTrail = $this->audit_trail ?? [];

        $auditTrail[] = [
            'action'    => $action,
            'timestamp' => now()->toIso8601String(),
            'user'      => auth()->user()?->name ?? 'System',
            'data'      => $data,
        ];

        $this->update(['audit_trail' => $auditTrail]);
    }

    public function addRiskIndicator(string $indicator, string $severity = 'medium', array $details = []): void
    {
        $riskIndicators = $this->risk_indicators ?? [];

        $riskIndicators[] = [
            'indicator'   => $indicator,
            'severity'    => $severity,
            'detected_at' => now()->toIso8601String(),
            'details'     => $details,
        ];

        $this->update(['risk_indicators' => $riskIndicators]);
    }

    public function addEntity(string $entityType, string $entityId, array $details = []): void
    {
        $entities = $this->entities_included ?? [];

        $entities[] = [
            'type'        => $entityType,
            'id'          => $entityId,
            'included_at' => now()->toIso8601String(),
            'details'     => $details,
        ];

        $this->update(
            [
            'entities_included' => $entities,
            'record_count'      => count($entities),
            ]
        );
    }

    public function canBeSubmitted(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PENDING_REVIEW]) &&
               ! $this->requires_correction &&
               $this->file_path &&
               $this->file_hash;
    }

    public function getTimeUntilDue(): ?string
    {
        if (! $this->due_date) {
            return null;
        }

        $now = Carbon::now();
        $dueDate = Carbon::parse($this->due_date);

        if ($now->isAfter($dueDate)) {
            return "Overdue by {$this->days_overdue} days";
        }

        return $now->diffForHumans(
            $dueDate,
            [
            'parts' => 2,
            'short' => true,
            ]
        );
    }

    public function getPriorityLabel(): string
    {
        return match ($this->priority) {
            5       => 'Critical',
            4       => 'High',
            3       => 'Medium',
            2       => 'Low',
            1       => 'Very Low',
            default => 'Unknown',
        };
    }

    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT          => 'Draft',
            self::STATUS_PENDING_REVIEW => 'Pending Review',
            self::STATUS_SUBMITTED      => 'Submitted',
            self::STATUS_ACCEPTED       => 'Accepted',
            self::STATUS_REJECTED       => 'Rejected',
            default                     => 'Unknown',
        };
    }

    // Scopes
    public function scopeOverdue($query)
    {
        return $query->where('is_overdue', true);
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', [self::STATUS_DRAFT, self::STATUS_PENDING_REVIEW]);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('report_type', $type);
    }

    public function scopeByJurisdiction($query, string $jurisdiction)
    {
        return $query->where('jurisdiction', $jurisdiction);
    }

    public function scopeDueSoon($query, int $days = 7)
    {
        return $query->whereNotNull('due_date')
            ->where('due_date', '<=', now()->addDays($days))
            ->where('status', '!=', self::STATUS_SUBMITTED)
            ->where('status', '!=', self::STATUS_ACCEPTED);
    }

    /**
     * Get the activity logs for this model.
     */
    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function logs()
    {
        return $this->morphMany(\App\Domain\Activity\Models\Activity::class, 'subject');
    }
}
