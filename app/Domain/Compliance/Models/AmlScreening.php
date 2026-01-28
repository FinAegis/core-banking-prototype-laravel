<?php

namespace App\Domain\Compliance\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
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
class AmlScreening extends Model
{
    use UsesTenantConnection;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'entity_id',
        'entity_type',
        'screening_number',
        'type',
        'status',
        'provider',
        'provider_reference',
        'search_parameters',
        'screening_config',
        'fuzzy_matching',
        'match_threshold',
        'total_matches',
        'confirmed_matches',
        'false_positives',
        'overall_risk',
        'sanctions_results',
        'pep_results',
        'adverse_media_results',
        'other_results',
        'confirmed_matches_detail',
        'potential_matches_detail',
        'dismissed_matches_detail',
        'lists_checked',
        'lists_updated_at',
        'reviewed_by',
        'reviewed_at',
        'review_decision',
        'review_notes',
        'started_at',
        'completed_at',
        'processing_time',
        'api_response',
    ];

    protected $casts = [
        'search_parameters'        => 'array',
        'screening_config'         => 'array',
        'fuzzy_matching'           => 'boolean',
        'sanctions_results'        => 'array',
        'pep_results'              => 'array',
        'adverse_media_results'    => 'array',
        'other_results'            => 'array',
        'confirmed_matches_detail' => 'array',
        'potential_matches_detail' => 'array',
        'dismissed_matches_detail' => 'array',
        'lists_checked'            => 'array',
        'api_response'             => 'array',
        'lists_updated_at'         => 'datetime',
        'reviewed_at'              => 'datetime',
        'started_at'               => 'datetime',
        'completed_at'             => 'datetime',
        'processing_time'          => 'decimal:2',
    ];

    public const TYPE_SANCTIONS = 'sanctions';

    public const TYPE_PEP = 'pep';

    public const TYPE_ADVERSE_MEDIA = 'adverse_media';

    public const TYPE_COMPREHENSIVE = 'comprehensive';

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const RISK_LOW = 'low';

    public const RISK_MEDIUM = 'medium';

    public const RISK_HIGH = 'high';

    public const RISK_CRITICAL = 'critical';

    public const DECISION_CLEAR = 'clear';

    public const DECISION_ESCALATE = 'escalate';

    public const DECISION_BLOCK = 'block';

    public const SCREENING_TYPES = [
        self::TYPE_SANCTIONS     => 'Sanctions Screening',
        self::TYPE_PEP           => 'PEP Screening',
        self::TYPE_ADVERSE_MEDIA => 'Adverse Media Screening',
        self::TYPE_COMPREHENSIVE => 'Comprehensive Screening',
    ];

    public const SANCTIONS_LISTS = [
        'OFAC' => 'Office of Foreign Assets Control (US)',
        'EU'   => 'European Union Sanctions',
        'UN'   => 'United Nations Sanctions',
        'HMT'  => 'HM Treasury (UK)',
        'DFAT' => 'Department of Foreign Affairs and Trade (AU)',
        'SECO' => 'State Secretariat for Economic Affairs (CH)',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(
            function ($screening) {
                if (! $screening->screening_number) {
                    $screening->screening_number = static::generateScreeningNumber();
                }
            }
        );
    }

    public static function generateScreeningNumber(): string
    {
        $year = date('Y');
        $lastScreening = static::whereYear('created_at', $year)
            ->orderBy('screening_number', 'desc')
            ->first();

        if ($lastScreening) {
            $lastNumber = intval(substr($lastScreening->screening_number, -5));
            $newNumber = str_pad((string) ($lastNumber + 1), 5, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '00001';
        }

        return "AML-{$year}-{$newNumber}";
    }

    // Relationships
    public function entity(): MorphTo
    {
        return $this->morphTo();
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // Helper methods
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function hasMatches(): bool
    {
        return $this->total_matches > 0;
    }

    public function hasConfirmedMatches(): bool
    {
        return $this->confirmed_matches > 0;
    }

    public function isHighRisk(): bool
    {
        return in_array($this->overall_risk, [self::RISK_HIGH, self::RISK_CRITICAL]);
    }

    public function requiresReview(): bool
    {
        return $this->hasMatches() && ! $this->reviewed_at;
    }

    public function getMatchRate(): float
    {
        if ($this->total_matches === 0) {
            return 0;
        }

        return round(($this->confirmed_matches / $this->total_matches) * 100, 2);
    }

    public function getFalsePositiveRate(): float
    {
        if ($this->total_matches === 0) {
            return 0;
        }

        return round(($this->false_positives / $this->total_matches) * 100, 2);
    }

    public function markAsCompleted(array $results = []): void
    {
        $this->update(
            array_merge(
                $results,
                [
                'status'          => self::STATUS_COMPLETED,
                'completed_at'    => now(),
                'processing_time' => $this->started_at ? now()->diffInSeconds($this->started_at) : null,
                ]
            )
        );
    }

    public function markAsFailed(?string $reason = null): void
    {
        $this->update(
            [
            'status'          => self::STATUS_FAILED,
            'completed_at'    => now(),
            'processing_time' => $this->started_at ? now()->diffInSeconds($this->started_at) : null,
            'review_notes'    => $reason,
            ]
        );
    }

    public function addReview(string $decision, string $notes, User $reviewer): void
    {
        $this->update(
            [
            'reviewed_by'     => $reviewer->id,
            'reviewed_at'     => now(),
            'review_decision' => $decision,
            'review_notes'    => $notes,
            ]
        );
    }

    public function dismissMatch(string $matchId, string $reason): void
    {
        $dismissed = $this->dismissed_matches_detail ?? [];
        $dismissed[$matchId] = [
            'dismissed_at' => now()->toIso8601String(),
            'reason'       => $reason,
        ];

        $this->update(
            [
            'dismissed_matches_detail' => $dismissed,
            'false_positives'          => $this->false_positives + 1,
            ]
        );
    }

    public function confirmMatch(string $matchId, array $details = []): void
    {
        $confirmed = $this->confirmed_matches_detail ?? [];
        $confirmed[$matchId] = array_merge(
            $details,
            [
            'confirmed_at' => now()->toIso8601String(),
            ]
        );

        $this->update(
            [
            'confirmed_matches_detail' => $confirmed,
            'confirmed_matches'        => $this->confirmed_matches + 1,
            ]
        );
    }

    public function calculateOverallRisk(): string
    {
        // Critical risk if any confirmed sanctions match
        if ($this->confirmed_matches > 0 && isset($this->sanctions_results['matches'])) {
            return self::RISK_CRITICAL;
        }

        // High risk if PEP or multiple potential matches
        if (isset($this->pep_results['is_pep']) && $this->pep_results['is_pep']) {
            return self::RISK_HIGH;
        }

        // High risk if adverse media with serious allegations
        if (
            isset($this->adverse_media_results['serious_allegations'])
            && $this->adverse_media_results['serious_allegations'] > 0
        ) {
            return self::RISK_HIGH;
        }

        // Medium risk if any potential matches
        if ($this->total_matches > 0) {
            return self::RISK_MEDIUM;
        }

        // Low risk if clean
        return self::RISK_LOW;
    }

    public function getScreeningSummary(): array
    {
        return [
            'screening_number'  => $this->screening_number,
            'type'              => $this->type,
            'status'            => $this->status,
            'overall_risk'      => $this->overall_risk,
            'total_matches'     => $this->total_matches,
            'confirmed_matches' => $this->confirmed_matches,
            'false_positives'   => $this->false_positives,
            'requires_review'   => $this->requiresReview(),
            'review_decision'   => $this->review_decision,
            'completed_at'      => $this->completed_at?->toIso8601String(),
        ];
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
