<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $case_id
 * @property string $case_number
 * @property string $title
 * @property string $description
 * @property string $status
 * @property string $priority
 * @property string|null $assigned_to
 * @property array|null $related_alerts
 * @property array|null $entities
 * @property array|null $evidence
 * @property array|null $notes
 * @property string|null $resolution
 * @property string|null $created_by
 */
class ComplianceCase extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): \Illuminate\Database\Eloquent\Factories\Factory
    {
        return \Database\Factories\Domain\Compliance\ComplianceCaseFactory::new();
    }

    protected $fillable = [
        'case_id',
        'case_number',
        'title',
        'description',
        'type',
        'priority',
        'status',
        'alert_count',
        'total_risk_score',
        'entities',
        'evidence',
        'investigation_summary',
        'findings',
        'recommendations',
        'actions_taken',
        'regulatory_filings',
        'assigned_to',
        'assigned_at',
        'assigned_by',
        'created_by',
        'reviewed_by',
        'reviewed_at',
        'closed_by',
        'closed_at',
        'closure_reason',
        'closure_notes',
        'reopened_count',
        'last_activity_at',
        'due_date',
        'sla_status',
        'escalation_level',
        'tags',
        'metadata',
        'history',
        'documents',
        'communications',
        'notes',
        'user_id',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'entities'           => 'array',
        'evidence'           => 'array',
        'findings'           => 'array',
        'recommendations'    => 'array',
        'actions_taken'      => 'array',
        'regulatory_filings' => 'array',
        'tags'               => 'array',
        'metadata'           => 'array',
        'history'            => 'array',
        'documents'          => 'array',
        'communications'     => 'array',
        'notes'              => 'array',
        'total_risk_score'   => 'decimal:2',
        'assigned_at'        => 'datetime',
        'reviewed_at'        => 'datetime',
        'closed_at'          => 'datetime',
        'resolved_at'        => 'datetime',
        'last_activity_at'   => 'datetime',
        'due_date'           => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($case) {
            if (empty($case->case_id)) {
                // Generate case number
                $year = now()->format('Y');
                $lastCase = static::where('case_id', 'like', "CASE-{$year}-%")
                    ->orderBy('case_id', 'desc')
                    ->first();

                if ($lastCase) {
                    $lastNumber = (int) substr($lastCase->case_id, -6);
                    $newNumber = $lastNumber + 1;
                } else {
                    $newNumber = 1;
                }

                $case->case_id = sprintf('CASE-%s-%06d', $year, $newNumber);
            }
        });
    }

    // Status constants
    public const STATUS_OPEN = 'open';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_PENDING_REVIEW = 'pending_review';

    public const STATUS_ESCALATED = 'escalated';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_REOPENED = 'reopened';

    // Priority constants
    public const PRIORITY_LOW = 'low';

    public const PRIORITY_MEDIUM = 'medium';

    public const PRIORITY_HIGH = 'high';

    public const PRIORITY_CRITICAL = 'critical';

    // Type constants
    public const TYPE_INVESTIGATION = 'investigation';

    public const TYPE_SAR = 'sar';

    public const TYPE_CTR = 'ctr';

    public const TYPE_REGULATORY = 'regulatory';

    public const TYPE_FRAUD = 'fraud';

    public const TYPE_AML = 'aml';

    public const TYPE_KYC = 'kyc';

    // SLA Status
    public const SLA_ON_TRACK = 'on_track';

    public const SLA_AT_RISK = 'at_risk';

    public const SLA_BREACHED = 'breached';

    /**
     * Get the alerts associated with this case.
     */
    public function alerts(): HasMany
    {
        return $this->hasMany(ComplianceAlert::class, 'case_id');
    }

    /**
     * Get the assigned user.
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Alias for assignedUser relationship.
     */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the creator.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the reviewer.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the user who closed the case.
     */
    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /**
     * Check if case is active.
     */
    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_OPEN,
            self::STATUS_IN_PROGRESS,
            self::STATUS_PENDING_REVIEW,
            self::STATUS_ESCALATED,
            self::STATUS_REOPENED,
        ]);
    }

    /**
     * Check if case is closed.
     */
    public function isClosed(): bool
    {
        return in_array($this->status, [
            self::STATUS_RESOLVED,
            self::STATUS_CLOSED,
        ]);
    }

    /**
     * Check if case is high priority.
     */
    public function isHighPriority(): bool
    {
        return in_array($this->priority, [
            self::PRIORITY_HIGH,
            self::PRIORITY_CRITICAL,
        ]);
    }

    /**
     * Get priority color for UI.
     */
    public function getPriorityColor(): string
    {
        return match ($this->priority) {
            self::PRIORITY_CRITICAL => 'red',
            self::PRIORITY_HIGH     => 'orange',
            self::PRIORITY_MEDIUM   => 'yellow',
            self::PRIORITY_LOW      => 'green',
            default                 => 'gray',
        };
    }

    /**
     * Get status color for UI.
     */
    public function getStatusColor(): string
    {
        return match ($this->status) {
            self::STATUS_OPEN           => 'blue',
            self::STATUS_IN_PROGRESS    => 'yellow',
            self::STATUS_PENDING_REVIEW => 'orange',
            self::STATUS_ESCALATED      => 'red',
            self::STATUS_REOPENED       => 'purple',
            self::STATUS_RESOLVED       => 'green',
            self::STATUS_CLOSED         => 'gray',
            default                     => 'gray',
        };
    }

    /**
     * Get SLA status color.
     */
    public function getSlaColor(): string
    {
        return match ($this->sla_status) {
            self::SLA_ON_TRACK => 'green',
            self::SLA_AT_RISK  => 'yellow',
            self::SLA_BREACHED => 'red',
            default            => 'gray',
        };
    }

    /**
     * Calculate SLA status.
     */
    public function calculateSlaStatus(): string
    {
        if (! $this->due_date) {
            return self::SLA_ON_TRACK;
        }

        $now = now();
        $hoursUntilDue = $now->diffInHours($this->due_date, false);

        if ($hoursUntilDue < 0) {
            return self::SLA_BREACHED;
        } elseif ($hoursUntilDue < 24) {
            return self::SLA_AT_RISK;
        } else {
            return self::SLA_ON_TRACK;
        }
    }

    /**
     * Add investigation note.
     */
    public function addInvestigationNote(string $note, User $user): void
    {
        $history = $this->history ?? [];
        $history[] = [
            'type'      => 'note',
            'timestamp' => now()->toIso8601String(),
            'user_id'   => $user->id,
            'user_name' => $user->name,
            'content'   => $note,
        ];

        $this->update([
            'history'          => $history,
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Add document to case.
     */
    public function addDocument(array $documentData): void
    {
        $documents = $this->documents ?? [];
        $documents[] = array_merge($documentData, [
            'id'          => uniqid('doc_'),
            'uploaded_at' => now()->toIso8601String(),
        ]);

        $this->update([
            'documents'        => $documents,
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Record communication.
     */
    public function recordCommunication(array $communicationData): void
    {
        $communications = $this->communications ?? [];
        $communications[] = array_merge($communicationData, [
            'id'        => uniqid('comm_'),
            'timestamp' => now()->toIso8601String(),
        ]);

        $this->update([
            'communications'   => $communications,
            'last_activity_at' => now(),
        ]);
    }

    /**
     * Scope for active cases.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            self::STATUS_OPEN,
            self::STATUS_IN_PROGRESS,
            self::STATUS_PENDING_REVIEW,
            self::STATUS_ESCALATED,
            self::STATUS_REOPENED,
        ]);
    }

    /**
     * Scope for high priority cases.
     */
    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', [
            self::PRIORITY_HIGH,
            self::PRIORITY_CRITICAL,
        ]);
    }

    /**
     * Scope for overdue cases.
     */
    public function scopeOverdue($query)
    {
        return $query->where('due_date', '<', now())
            ->whereNotIn('status', [
                self::STATUS_RESOLVED,
                self::STATUS_CLOSED,
            ]);
    }

    /**
     * Scope for cases at risk of SLA breach.
     */
    public function scopeSlaAtRisk($query)
    {
        return $query->where('sla_status', self::SLA_AT_RISK);
    }
}
