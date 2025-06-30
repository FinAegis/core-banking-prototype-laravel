<?php

namespace App\Models;

use App\Traits\BelongsToTeam;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FraudCase extends Model
{
    use HasUuids, BelongsToTeam, HasFactory;
    
    /**
     * The primary key type.
     *
     * @var string
     */
    protected $keyType = 'int';
    
    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;
    
    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected $fillable = [
        'uuid',
        'team_id',
        'case_number',
        'status',
        'severity',
        'type',
        'subject_account_uuid',
        'related_transactions',
        'related_accounts',
        'amount',
        'currency',
        'risk_score',
        'description',
        'detection_rules',
        'evidence',
        'detected_at',
        'assigned_to',
        'resolved_at',
        'actions_taken',
        'resolution_notes',
    ];

    protected $casts = [
        'related_transactions' => 'array',
        'related_accounts' => 'array',
        'detection_rules' => 'array',
        'evidence' => 'array',
        'actions_taken' => 'array',
        'amount' => 'decimal:8',
        'risk_score' => 'decimal:2',
        'detected_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    const STATUS_PENDING = 'pending';
    const STATUS_INVESTIGATING = 'investigating';
    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_FALSE_POSITIVE = 'false_positive';
    const STATUS_RESOLVED = 'resolved';

    const SEVERITY_LOW = 'low';
    const SEVERITY_MEDIUM = 'medium';
    const SEVERITY_HIGH = 'high';
    const SEVERITY_CRITICAL = 'critical';

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
    public function subjectAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'subject_account_uuid', 'uuid');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to', 'uuid');
    }


    // Helper methods
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isInvestigating(): bool
    {
        return $this->status === self::STATUS_INVESTIGATING;
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    public function isHighPriority(): bool
    {
        return in_array($this->severity, [self::SEVERITY_HIGH, self::SEVERITY_CRITICAL]);
    }

    public function wasFalsePositive(): bool
    {
        return $this->status === self::STATUS_FALSE_POSITIVE;
    }

    public function getDurationInDays(): int
    {
        // Since we don't have fraud_start_date and fraud_end_date columns,
        // we'll calculate based on detected_at and resolved_at
        if (!$this->detected_at || !$this->resolved_at) {
            return 0;
        }
        
        return $this->detected_at->diffInDays($this->resolved_at);
    }


    public function assign(User $investigator): void
    {
        $this->update([
            'assigned_to' => $investigator->uuid,
            'status' => self::STATUS_INVESTIGATING,
        ]);
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

    public function resolve(string $notes): void
    {
        $this->update([
            'resolution_notes' => $notes,
            'resolved_at' => now(),
            'status' => self::STATUS_RESOLVED,
        ]);
    }

    public function getCaseSummary(): array
    {
        return [
            'case_number' => $this->case_number,
            'type' => $this->type,
            'status' => $this->status,
            'severity' => $this->severity,
            'total_loss' => $this->amount,
            'duration_days' => $this->getDurationInDays(),
            'accounts_affected' => count($this->related_accounts ?? []),
        ];
    }
}