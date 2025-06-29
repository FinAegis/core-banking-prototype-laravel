<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraudCase extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'case_number',
        'status',
        'priority',
        'type',
        'subject_user_id',
        'subject_account_id',
        'related_entities',
        'total_amount',
        'currency',
        'transaction_count',
        'fraud_start_date',
        'fraud_end_date',
        'description',
        'detection_method',
        'detection_details',
        'initial_fraud_score_id',
        'detected_at',
        'assigned_to',
        'assigned_at',
        'investigation_started_at',
        'investigation_completed_at',
        'investigation_notes',
        'evidence',
        'actions_taken',
        'funds_recovered',
        'amount_recovered',
        'law_enforcement_notified',
        'law_enforcement_reference',
        'resolution',
        'resolution_summary',
        'resolved_by',
        'resolved_at',
        'prevention_measures',
        'rules_updated',
        'updated_rules',
        'reported_to_regulator',
        'regulatory_reports',
        'customer_notified',
        'customer_notified_at',
    ];

    protected $casts = [
        'related_entities' => 'array',
        'detection_details' => 'array',
        'investigation_notes' => 'array',
        'evidence' => 'array',
        'actions_taken' => 'array',
        'prevention_measures' => 'array',
        'updated_rules' => 'array',
        'regulatory_reports' => 'array',
        'total_amount' => 'decimal:2',
        'amount_recovered' => 'decimal:2',
        'funds_recovered' => 'boolean',
        'law_enforcement_notified' => 'boolean',
        'rules_updated' => 'boolean',
        'reported_to_regulator' => 'boolean',
        'customer_notified' => 'boolean',
        'fraud_start_date' => 'datetime',
        'fraud_end_date' => 'datetime',
        'detected_at' => 'datetime',
        'assigned_at' => 'datetime',
        'investigation_started_at' => 'datetime',
        'investigation_completed_at' => 'datetime',
        'resolved_at' => 'datetime',
        'customer_notified_at' => 'datetime',
    ];

    const STATUS_OPEN = 'open';
    const STATUS_INVESTIGATING = 'investigating';
    const STATUS_RESOLVED = 'resolved';
    const STATUS_CLOSED = 'closed';

    const PRIORITY_LOW = 'low';
    const PRIORITY_MEDIUM = 'medium';
    const PRIORITY_HIGH = 'high';
    const PRIORITY_CRITICAL = 'critical';

    const TYPE_ACCOUNT_TAKEOVER = 'account_takeover';
    const TYPE_IDENTITY_THEFT = 'identity_theft';
    const TYPE_TRANSACTION_FRAUD = 'transaction_fraud';
    const TYPE_CARD_FRAUD = 'card_fraud';
    const TYPE_PHISHING = 'phishing';
    const TYPE_MONEY_LAUNDERING = 'money_laundering';
    const TYPE_OTHER = 'other';

    const DETECTION_METHOD_RULE_BASED = 'rule_based';
    const DETECTION_METHOD_ML_MODEL = 'ml_model';
    const DETECTION_METHOD_MANUAL_REPORT = 'manual_report';
    const DETECTION_METHOD_EXTERNAL_REPORT = 'external_report';

    const RESOLUTION_CONFIRMED_FRAUD = 'confirmed_fraud';
    const RESOLUTION_FALSE_POSITIVE = 'false_positive';
    const RESOLUTION_INSUFFICIENT_EVIDENCE = 'insufficient_evidence';

    const FRAUD_TYPES = [
        self::TYPE_ACCOUNT_TAKEOVER => 'Account Takeover',
        self::TYPE_IDENTITY_THEFT => 'Identity Theft',
        self::TYPE_TRANSACTION_FRAUD => 'Transaction Fraud',
        self::TYPE_CARD_FRAUD => 'Card Fraud',
        self::TYPE_PHISHING => 'Phishing',
        self::TYPE_MONEY_LAUNDERING => 'Money Laundering',
        self::TYPE_OTHER => 'Other',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($case) {
            if (!$case->case_number) {
                $case->case_number = static::generateCaseNumber();
            }
            if (!$case->detected_at) {
                $case->detected_at = now();
            }
        });
    }

    public static function generateCaseNumber(): string
    {
        $year = date('Y');
        $lastCase = static::whereYear('created_at', $year)
            ->orderBy('case_number', 'desc')
            ->first();
        
        if ($lastCase) {
            $lastNumber = intval(substr($lastCase->case_number, -5));
            $newNumber = str_pad($lastNumber + 1, 5, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '00001';
        }
        
        return "FC-{$year}-{$newNumber}";
    }

    // Relationships
    public function subjectUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'subject_user_id');
    }

    public function subjectAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'subject_account_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function initialFraudScore(): BelongsTo
    {
        return $this->belongsTo(FraudScore::class, 'initial_fraud_score_id');
    }

    // Helper methods
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isInvestigating(): bool
    {
        return $this->status === self::STATUS_INVESTIGATING;
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function isClosed(): bool
    {
        return $this->status === self::STATUS_CLOSED;
    }

    public function isHighPriority(): bool
    {
        return in_array($this->priority, [self::PRIORITY_HIGH, self::PRIORITY_CRITICAL]);
    }

    public function wasConfirmedFraud(): bool
    {
        return $this->resolution === self::RESOLUTION_CONFIRMED_FRAUD;
    }

    public function wasFalsePositive(): bool
    {
        return $this->resolution === self::RESOLUTION_FALSE_POSITIVE;
    }

    public function getDurationInDays(): int
    {
        if (!$this->fraud_start_date || !$this->fraud_end_date) {
            return 0;
        }
        
        return $this->fraud_start_date->diffInDays($this->fraud_end_date);
    }

    public function getInvestigationDurationInHours(): float
    {
        if (!$this->investigation_started_at || !$this->investigation_completed_at) {
            return 0;
        }
        
        return $this->investigation_started_at->diffInHours($this->investigation_completed_at);
    }

    public function getRecoveryRate(): float
    {
        if (!$this->total_amount || $this->total_amount == 0) {
            return 0;
        }
        
        return round(($this->amount_recovered / $this->total_amount) * 100, 2);
    }

    public function assign(User $investigator): void
    {
        $this->update([
            'assigned_to' => $investigator->id,
            'assigned_at' => now(),
            'status' => self::STATUS_INVESTIGATING,
        ]);
    }

    public function startInvestigation(): void
    {
        $this->update([
            'investigation_started_at' => now(),
            'status' => self::STATUS_INVESTIGATING,
        ]);
    }

    public function addInvestigationNote(string $note, User $user): void
    {
        $notes = $this->investigation_notes ?? [];
        $notes[] = [
            'timestamp' => now()->toIso8601String(),
            'user_id' => $user->id,
            'user_name' => $user->name,
            'note' => $note,
        ];
        
        $this->update(['investigation_notes' => $notes]);
    }

    public function addEvidence(array $evidence): void
    {
        $currentEvidence = $this->evidence ?? [];
        $currentEvidence[] = array_merge($evidence, [
            'added_at' => now()->toIso8601String(),
        ]);
        
        $this->update(['evidence' => $currentEvidence]);
    }

    public function recordAction(string $action, array $details = []): void
    {
        $actions = $this->actions_taken ?? [];
        $actions[] = array_merge([
            'action' => $action,
            'timestamp' => now()->toIso8601String(),
        ], $details);
        
        $this->update(['actions_taken' => $actions]);
    }

    public function resolve(string $resolution, string $summary, User $resolver): void
    {
        $this->update([
            'resolution' => $resolution,
            'resolution_summary' => $summary,
            'resolved_by' => $resolver->id,
            'resolved_at' => now(),
            'investigation_completed_at' => now(),
            'status' => self::STATUS_RESOLVED,
        ]);
    }

    public function close(): void
    {
        $this->update(['status' => self::STATUS_CLOSED]);
    }

    public function notifyCustomer(): void
    {
        $this->update([
            'customer_notified' => true,
            'customer_notified_at' => now(),
        ]);
    }

    public function notifyLawEnforcement(string $reference): void
    {
        $this->update([
            'law_enforcement_notified' => true,
            'law_enforcement_reference' => $reference,
        ]);
        
        $this->recordAction('law_enforcement_notified', [
            'reference' => $reference,
        ]);
    }

    public function reportToRegulator(array $reportDetails): void
    {
        $reports = $this->regulatory_reports ?? [];
        $reports[] = array_merge($reportDetails, [
            'reported_at' => now()->toIso8601String(),
        ]);
        
        $this->update([
            'reported_to_regulator' => true,
            'regulatory_reports' => $reports,
        ]);
    }

    public function implementPreventionMeasures(array $measures): void
    {
        $this->update([
            'prevention_measures' => $measures,
            'rules_updated' => !empty($measures['updated_rules']),
            'updated_rules' => $measures['updated_rules'] ?? [],
        ]);
    }

    public function getCaseSummary(): array
    {
        return [
            'case_number' => $this->case_number,
            'type' => $this->type,
            'status' => $this->status,
            'priority' => $this->priority,
            'total_loss' => $this->total_amount,
            'amount_recovered' => $this->amount_recovered,
            'recovery_rate' => $this->getRecoveryRate() . '%',
            'duration_days' => $this->getDurationInDays(),
            'resolution' => $this->resolution,
            'customer_impact' => [
                'notified' => $this->customer_notified,
                'accounts_affected' => count($this->related_entities['accounts'] ?? []),
            ],
        ];
    }
}