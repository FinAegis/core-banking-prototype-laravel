<?php

namespace App\Domain\Compliance\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @method static \Illuminate\Database\Eloquent\Builder where(string $column, mixed $operator = null, mixed $value = null, string $boolean = 'and')
 * @method static \Illuminate\Database\Eloquent\Builder whereYear(string $column, mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder whereMonth(string $column, mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder whereDate(string $column, mixed $value)
 * @method static \Illuminate\Database\Eloquent\Builder whereIn(string $column, mixed $values)
 * @method static static updateOrCreate(array $attributes, array $values = [])
 * @method static \Illuminate\Support\Collection pluck(string $column, string|null $key = null)
 * @method static \Illuminate\Database\Eloquent\Builder selectRaw(string $expression, array $bindings = [])
 * @method static \Illuminate\Database\Eloquent\Builder orderBy(string $column, string $direction = 'asc')
 * @method static \Illuminate\Database\Eloquent\Builder latest(string $column = null)
 * @method static \Illuminate\Database\Eloquent\Builder oldest(string $column = null)
 * @method static mixed sum(string $column)
 * @method static int count(string $columns = '*')
 * @method static static|null first()
 * @method static \Illuminate\Database\Eloquent\Collection get(array|string $columns = ['*'])
 */
class SuspiciousActivityReport extends Model
{
    use UsesTenantConnection;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'sar_number',
        'status',
        'priority',
        'subject_user_id',
        'subject_type',
        'subject_details',
        'activity_start_date',
        'activity_end_date',
        'total_amount',
        'primary_currency',
        'transaction_count',
        'involved_accounts',
        'involved_parties',
        'activity_types',
        'activity_description',
        'red_flags',
        'triggering_rules',
        'related_transactions',
        'investigator_id',
        'investigation_started_at',
        'investigation_completed_at',
        'investigation_findings',
        'supporting_documents',
        'decision',
        'decision_rationale',
        'decision_maker_id',
        'decision_date',
        'actions_taken',
        'filed_with_regulator',
        'filing_reference',
        'filing_date',
        'filing_jurisdiction',
        'filing_details',
        'requires_follow_up',
        'follow_up_date',
        'follow_up_notes',
        'related_sars',
        'reviewed_by',
        'reviewed_at',
        'review_comments',
        'qa_approved',
        'is_confidential',
        'access_log',
        'retention_until',
    ];

    protected $casts = [
        'subject_details'            => 'array',
        'involved_accounts'          => 'array',
        'involved_parties'           => 'array',
        'activity_types'             => 'array',
        'red_flags'                  => 'array',
        'triggering_rules'           => 'array',
        'related_transactions'       => 'array',
        'supporting_documents'       => 'array',
        'actions_taken'              => 'array',
        'filing_details'             => 'array',
        'related_sars'               => 'array',
        'access_log'                 => 'array',
        'total_amount'               => 'decimal:2',
        'filed_with_regulator'       => 'boolean',
        'requires_follow_up'         => 'boolean',
        'qa_approved'                => 'boolean',
        'is_confidential'            => 'boolean',
        'activity_start_date'        => 'datetime',
        'activity_end_date'          => 'datetime',
        'investigation_started_at'   => 'datetime',
        'investigation_completed_at' => 'datetime',
        'decision_date'              => 'datetime',
        'filing_date'                => 'datetime',
        'follow_up_date'             => 'date',
        'reviewed_at'                => 'datetime',
        'retention_until'            => 'datetime',
    ];

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_CLOSED = 'closed';

    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_CRITICAL = 'critical';

    public const SUBJECT_TYPE_CUSTOMER = 'customer';

    public const SUBJECT_TYPE_TRANSACTION = 'transaction';

    public const SUBJECT_TYPE_PATTERN = 'pattern';

    public const DECISION_FILE_SAR = 'file_sar';

    public const DECISION_NO_ACTION = 'no_action';

    public const DECISION_CONTINUE_MONITORING = 'continue_monitoring';

    public const ACTIVITY_TYPES = [
        'structuring'         => 'Structuring',
        'layering'            => 'Layering',
        'integration'         => 'Integration',
        'tax_evasion'         => 'Tax Evasion',
        'terrorist_financing' => 'Terrorist Financing',
        'corruption'          => 'Corruption/Bribery',
        'fraud'               => 'Fraud',
        'identity_theft'      => 'Identity Theft',
        'human_trafficking'   => 'Human Trafficking',
        'drug_trafficking'    => 'Drug Trafficking',
        'other'               => 'Other Suspicious Activity',
    ];

    public const RED_FLAGS = [
        'unusual_transaction_pattern' => 'Unusual transaction patterns',
        'rapid_movement'              => 'Rapid movement of funds',
        'multiple_accounts'           => 'Use of multiple accounts',
        'high_risk_geography'         => 'High-risk geographic exposure',
        'inconsistent_activity'       => 'Activity inconsistent with profile',
        'reluctant_information'       => 'Reluctance to provide information',
        'complex_structure'           => 'Unnecessarily complex transaction structure',
        'round_amounts'               => 'Frequent round-dollar transactions',
        'no_business_purpose'         => 'No apparent business purpose',
        'third_party_involvement'     => 'Unusual third-party involvement',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(
            function ($sar) {
                if (! $sar->sar_number) {
                    $sar->sar_number = static::generateSARNumber();
                }
                if (! $sar->retention_until) {
                    $sar->retention_until = now()->addYears(5); // Standard retention period
                }
            }
        );

        // Log access when retrieved
        static::retrieved(
            function ($sar) {
                if ($sar->is_confidential && auth()->check()) {
                    $sar->logAccess();
                }
            }
        );
    }

    public static function generateSARNumber(): string
    {
        $year = date('Y');
        $lastSAR = static::whereYear('created_at', $year)
            ->orderBy('sar_number', 'desc')
            ->first();

        if ($lastSAR) {
            $lastNumber = intval(substr($lastSAR->sar_number, -5));
            $newNumber = str_pad((string) ($lastNumber + 1), 5, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '00001';
        }

        return "SAR-{$year}-{$newNumber}";
    }

    // Relationships
    public function subjectUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    public function investigator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'investigator_id');
    }

    public function decisionMaker(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decision_maker_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // Helper methods
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isPendingReview(): bool
    {
        return $this->status === self::STATUS_PENDING_REVIEW;
    }

    public function isSubmitted(): bool
    {
        return $this->status === self::STATUS_SUBMITTED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isHighPriority(): bool
    {
        return in_array($this->priority, [self::PRIORITY_HIGH, self::PRIORITY_CRITICAL]);
    }

    public function isFiledWithRegulator(): bool
    {
        return $this->filed_with_regulator;
    }

    public function requiresFollowUp(): bool
    {
        return $this->requires_follow_up && (! $this->follow_up_date || $this->follow_up_date->isFuture());
    }

    public function isOverdue(): bool
    {
        return $this->requires_follow_up && $this->follow_up_date && $this->follow_up_date->isPast();
    }

    public function canEdit(): bool
    {
        return in_array($this->status, [self::STATUS_DRAFT, self::STATUS_PENDING_REVIEW]);
    }

    public function startInvestigation(User $investigator): void
    {
        $this->update(
            [
            'investigator_id'          => $investigator->id,
            'investigation_started_at' => now(),
            'status'                   => self::STATUS_PENDING_REVIEW,
            ]
        );
    }

    public function completeInvestigation(string $findings, array $supportingDocs = []): void
    {
        $this->update(
            [
            'investigation_completed_at' => now(),
            'investigation_findings'     => $findings,
            'supporting_documents'       => array_merge($this->supporting_documents ?? [], $supportingDocs),
            ]
        );
    }

    public function makeDecision(string $decision, string $rationale, User $decisionMaker): void
    {
        $this->update(
            [
            'decision'           => $decision,
            'decision_rationale' => $rationale,
            'decision_maker_id'  => $decisionMaker->id,
            'decision_date'      => now(),
            ]
        );

        if ($decision === self::DECISION_FILE_SAR) {
            $this->prepareForFiling();
        } elseif ($decision === self::DECISION_NO_ACTION) {
            $this->status = self::STATUS_CLOSED;
            $this->save();
        }
    }

    protected function prepareForFiling(): void
    {
        // Generate filing details
        $this->filing_details = [
            'prepared_at'      => now()->toIso8601String(),
            'preparer_id'      => auth()->id(),
            'institution_name' => config('app.name'),
            'filing_deadline'  => now()->addDays(30)->toDateString(), // Standard 30-day deadline
        ];
        $this->save();
    }

    public function fileWithRegulator(string $reference, string $jurisdiction): void
    {
        $this->update(
            [
            'filed_with_regulator' => true,
            'filing_reference'     => $reference,
            'filing_date'          => now(),
            'filing_jurisdiction'  => $jurisdiction,
            'status'               => self::STATUS_SUBMITTED,
            ]
        );
    }

    public function addReview(string $comments, bool $approved, User $reviewer): void
    {
        $this->update(
            [
            'reviewed_by'     => $reviewer->id,
            'reviewed_at'     => now(),
            'review_comments' => $comments,
            'qa_approved'     => $approved,
            ]
        );
    }

    public function linkTransaction(string $transactionId): void
    {
        $transactions = $this->related_transactions ?? [];
        if (! in_array($transactionId, $transactions)) {
            $transactions[] = $transactionId;
            $this->related_transactions = $transactions;
            $this->save();
        }
    }

    public function linkSAR(string $sarId): void
    {
        $sars = $this->related_sars ?? [];
        if (! in_array($sarId, $sars)) {
            $sars[] = $sarId;
            $this->related_sars = $sars;
            $this->save();
        }
    }

    public function logAccess(): void
    {
        $log = $this->access_log ?? [];
        $log[] = [
            'user_id'     => auth()->id(),
            'user_name'   => auth()->user()->name,
            'accessed_at' => now()->toIso8601String(),
            'ip_address'  => request()->ip(),
        ];

        $this->access_log = $log;
        $this->saveQuietly(); // Save without triggering events
    }

    public function calculateTotalAmount(): float
    {
        // This would typically calculate from related transactions
        // For now, return the stored total_amount
        return (float) ($this->total_amount ?? 0);
    }

    public function getActivityDuration(): int
    {
        if (! $this->activity_start_date || ! $this->activity_end_date) {
            return 0;
        }

        return (int) $this->activity_start_date->diffInDays($this->activity_end_date);
    }

    public function getSummary(): array
    {
        return [
            'sar_number'        => $this->sar_number,
            'status'            => $this->status,
            'priority'          => $this->priority,
            'subject'           => $this->subject_details['name'] ?? 'Unknown',
            'activity_types'    => $this->activity_types,
            'total_amount'      => $this->total_amount,
            'transaction_count' => $this->transaction_count,
            'activity_duration' => $this->getActivityDuration() . ' days',
            'filed'             => $this->filed_with_regulator,
            'decision'          => $this->decision,
        ];
    }

    /**
     * Get the activity logs for this model.
     */
    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function logs()
    {
        return $this->morphMany(\App\Domain\Activity\Models\Activity::class, 'subject');
    }
}
