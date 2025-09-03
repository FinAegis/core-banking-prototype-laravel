<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $id
 * @property string $alert_id
 * @property string $type
 * @property string $severity
 * @property string $status
 * @property string $entity_type
 * @property string $entity_id
 * @property string $description
 * @property array|null $details
 * @property string|null $assigned_to
 * @property array|null $notes
 * @property string|null $resolution
 * @property string|null $created_by
 * @property string|null $case_id
 */
class ComplianceAlert extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    /**
     * Create a new factory instance for the model.
     */
    protected static function newFactory(): \Illuminate\Database\Eloquent\Factories\Factory
    {
        return \Database\Factories\Domain\Compliance\ComplianceAlertFactory::new();
    }

    protected $fillable = [
        'alert_id',
        'type',
        'severity',
        'status',
        'title',
        'description',
        'details',
        'source',
        'entity_type',
        'entity_id',
        'transaction_id',
        'account_id',
        'user_id',
        'rule_id',
        'case_id',
        'pattern_data',
        'evidence',
        'risk_score',
        'confidence_score',
        'metadata',
        'tags',
        'detected_at',
        'expires_at',
        'assigned_to',
        'assigned_at',
        'assigned_by',
        'resolved_at',
        'resolved_by',
        'resolution',
        'resolution_notes',
        'resolution_time_hours',
        'false_positive_notes',
        'escalated_at',
        'escalation_reason',
        'investigation_notes',
        'linked_alerts',
        'history',
        'status_changed_at',
        'status_changed_by',
        'created_by',
    ];

    protected $casts = [
        'pattern_data'          => 'array',
        'evidence'              => 'array',
        'metadata'              => 'array',
        'tags'                  => 'array',
        'details'               => 'array',
        'investigation_notes'   => 'array',
        'linked_alerts'         => 'array',
        'history'               => 'array',
        'risk_score'            => 'decimal:2',
        'confidence_score'      => 'decimal:2',
        'resolution_time_hours' => 'decimal:2',
        'detected_at'           => 'datetime',
        'expires_at'            => 'datetime',
        'assigned_at'           => 'datetime',
        'resolved_at'           => 'datetime',
        'escalated_at'          => 'datetime',
        'status_changed_at'     => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::creating(function ($alert) {
            // Set detected_at if not provided
            if (empty($alert->detected_at)) {
                $alert->detected_at = now();
            }

            if (empty($alert->alert_id)) {
                // Get the next sequence number for this year
                $year = now()->format('Y');
                $lastAlert = static::where('alert_id', 'like', "ALERT-{$year}-%")
                    ->orderBy('alert_id', 'desc')
                    ->first();

                if ($lastAlert) {
                    $lastNumber = (int) substr($lastAlert->alert_id, -6);
                    $newNumber = $lastNumber + 1;
                } else {
                    $newNumber = 1;
                }

                $alert->alert_id = sprintf('ALERT-%s-%06d', $year, $newNumber);
            }
        });
    }

    /**
     * Get the notes attribute.
     */
    public function getNotesAttribute()
    {
        return $this->investigation_notes ?? [];
    }

    /**
     * Set the notes attribute.
     */
    public function setNotesAttribute($value)
    {
        $this->investigation_notes = $value;
    }

    // Status constants
    public const STATUS_OPEN = 'open';

    public const STATUS_IN_REVIEW = 'in_review';

    public const STATUS_ESCALATED = 'escalated';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_FALSE_POSITIVE = 'false_positive';

    public const STATUS_EXPIRED = 'expired';

    // Severity constants
    public const SEVERITY_LOW = 'low';

    public const SEVERITY_MEDIUM = 'medium';

    public const SEVERITY_HIGH = 'high';

    public const SEVERITY_CRITICAL = 'critical';

    // Type constants
    public const TYPE_TRANSACTION = 'transaction';

    public const TYPE_PATTERN = 'pattern';

    public const TYPE_VELOCITY = 'velocity';

    public const TYPE_THRESHOLD = 'threshold';

    public const TYPE_BEHAVIOR = 'behavior';

    public const TYPE_GEOGRAPHY = 'geography';

    public const TYPE_MANUAL = 'manual';

    // Source constants
    public const SOURCE_SYSTEM = 'system';

    public const SOURCE_RULE = 'rule';

    public const SOURCE_ML = 'machine_learning';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_EXTERNAL = 'external';

    /**
     * Get the user associated with the alert.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get the assigned user.
     */
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * Get the user who resolved the alert.
     */
    public function resolvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    /**
     * Get the compliance case.
     */
    public function complianceCase(): BelongsTo
    {
        return $this->belongsTo(ComplianceCase::class, 'case_id');
    }

    /**
     * Get the monitoring rule.
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(TransactionMonitoringRule::class, 'rule_id');
    }

    /**
     * Check if alert is active.
     */
    public function isActive(): bool
    {
        return in_array($this->status, [
            self::STATUS_OPEN,
            self::STATUS_IN_REVIEW,
            self::STATUS_ESCALATED,
        ]);
    }

    /**
     * Check if alert is resolved.
     */
    public function isResolved(): bool
    {
        return in_array($this->status, [
            self::STATUS_RESOLVED,
            self::STATUS_FALSE_POSITIVE,
            self::STATUS_EXPIRED,
        ]);
    }

    /**
     * Check if alert is high priority.
     */
    public function isHighPriority(): bool
    {
        return in_array($this->severity, [
            self::SEVERITY_HIGH,
            self::SEVERITY_CRITICAL,
        ]) || $this->risk_score >= 75;
    }

    /**
     * Get severity color for UI.
     */
    public function getSeverityColor(): string
    {
        return match ($this->severity) {
            self::SEVERITY_CRITICAL => 'red',
            self::SEVERITY_HIGH     => 'orange',
            self::SEVERITY_MEDIUM   => 'yellow',
            self::SEVERITY_LOW      => 'green',
            default                 => 'gray',
        };
    }

    /**
     * Get status badge color for UI.
     */
    public function getStatusColor(): string
    {
        return match ($this->status) {
            self::STATUS_OPEN           => 'blue',
            self::STATUS_IN_REVIEW      => 'yellow',
            self::STATUS_ESCALATED      => 'orange',
            self::STATUS_RESOLVED       => 'green',
            self::STATUS_FALSE_POSITIVE => 'gray',
            self::STATUS_EXPIRED        => 'gray',
            default                     => 'gray',
        };
    }

    /**
     * Scope for active alerts.
     */
    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            self::STATUS_OPEN,
            self::STATUS_IN_REVIEW,
            self::STATUS_ESCALATED,
        ]);
    }

    /**
     * Scope for high priority alerts.
     */
    public function scopeHighPriority($query)
    {
        return $query->where(function ($q) {
            $q->whereIn('severity', [self::SEVERITY_HIGH, self::SEVERITY_CRITICAL])
              ->orWhere('risk_score', '>=', 75);
        });
    }

    /**
     * Scope for unassigned alerts.
     */
    public function scopeUnassigned($query)
    {
        return $query->whereNull('assigned_to');
    }

    /**
     * Scope for expired alerts.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now())
            ->where('status', '!=', self::STATUS_EXPIRED);
    }
}
