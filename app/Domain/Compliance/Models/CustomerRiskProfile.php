<?php

namespace App\Domain\Compliance\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
class CustomerRiskProfile extends Model
{
    use UsesTenantConnection;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'profile_number',
        'risk_rating',
        'risk_score',
        'last_assessment_at',
        'next_review_at',
        'geographic_risk',
        'product_risk',
        'channel_risk',
        'customer_risk',
        'behavioral_risk',
        'cdd_level',
        'cdd_measures',
        'cdd_completed_at',
        'cdd_expires_at',
        'is_pep',
        'pep_type',
        'pep_position',
        'pep_details',
        'pep_verified_at',
        'is_sanctioned',
        'sanctions_details',
        'sanctions_verified_at',
        'has_adverse_media',
        'adverse_media_details',
        'adverse_media_checked_at',
        'daily_transaction_limit',
        'monthly_transaction_limit',
        'single_transaction_limit',
        'restricted_countries',
        'restricted_currencies',
        'enhanced_monitoring',
        'monitoring_rules',
        'monitoring_frequency',
        'risk_history',
        'screening_history',
        'suspicious_activities_count',
        'last_suspicious_activity_at',
        'source_of_wealth',
        'source_of_funds',
        'sow_verified',
        'sof_verified',
        'business_type',
        'industry_code',
        'beneficial_owners',
        'complex_structure',
        'approved_by',
        'approved_at',
        'approval_notes',
        'override_reasons',
    ];

    protected $casts = [
        'geographic_risk'             => 'array',
        'product_risk'                => 'array',
        'channel_risk'                => 'array',
        'customer_risk'               => 'array',
        'behavioral_risk'             => 'array',
        'cdd_measures'                => 'array',
        'pep_details'                 => 'array',
        'sanctions_details'           => 'array',
        'adverse_media_details'       => 'array',
        'restricted_countries'        => 'array',
        'restricted_currencies'       => 'array',
        'monitoring_rules'            => 'array',
        'risk_history'                => 'array',
        'screening_history'           => 'array',
        'beneficial_owners'           => 'array',
        'override_reasons'            => 'array',
        'risk_score'                  => 'decimal:2',
        'daily_transaction_limit'     => 'decimal:2',
        'monthly_transaction_limit'   => 'decimal:2',
        'single_transaction_limit'    => 'decimal:2',
        'is_pep'                      => 'boolean',
        'is_sanctioned'               => 'boolean',
        'has_adverse_media'           => 'boolean',
        'enhanced_monitoring'         => 'boolean',
        'sow_verified'                => 'boolean',
        'sof_verified'                => 'boolean',
        'complex_structure'           => 'boolean',
        'last_assessment_at'          => 'datetime',
        'next_review_at'              => 'datetime',
        'cdd_completed_at'            => 'datetime',
        'cdd_expires_at'              => 'datetime',
        'pep_verified_at'             => 'datetime',
        'sanctions_verified_at'       => 'datetime',
        'adverse_media_checked_at'    => 'datetime',
        'last_suspicious_activity_at' => 'datetime',
        'approved_at'                 => 'datetime',
    ];

    public const RISK_RATING_LOW = 'low';

    public const RISK_RATING_MEDIUM = 'medium';

    public const RISK_RATING_HIGH = 'high';

    public const RISK_RATING_PROHIBITED = 'prohibited';

    public const CDD_LEVEL_SIMPLIFIED = 'simplified';

    public const CDD_LEVEL_STANDARD = 'standard';

    public const CDD_LEVEL_ENHANCED = 'enhanced';

    public const PEP_TYPE_DOMESTIC = 'domestic';

    public const PEP_TYPE_FOREIGN = 'foreign';

    public const PEP_TYPE_INTERNATIONAL_ORG = 'international_org';

    public const RISK_RATINGS = [
        self::RISK_RATING_LOW        => 'Low Risk',
        self::RISK_RATING_MEDIUM     => 'Medium Risk',
        self::RISK_RATING_HIGH       => 'High Risk',
        self::RISK_RATING_PROHIBITED => 'Prohibited',
    ];

    public const CDD_LEVELS = [
        self::CDD_LEVEL_SIMPLIFIED => 'Simplified Due Diligence',
        self::CDD_LEVEL_STANDARD   => 'Standard Due Diligence',
        self::CDD_LEVEL_ENHANCED   => 'Enhanced Due Diligence',
    ];

    // High-risk countries list (example - should be maintained separately)
    public const HIGH_RISK_COUNTRIES = [
        'AF', 'AL', 'BS', 'BB', 'BW', 'KH', 'GH', 'IS', 'JM', 'ML',
        'MT', 'MN', 'MM', 'NI', 'PK', 'PA', 'PH', 'SN', 'SY', 'UG',
        'VU', 'YE', 'ZW', 'IR', 'KP',
    ];

    // High-risk industries (NAICS codes)
    public const HIGH_RISK_INDUSTRIES = [
        '5223', // Activities Related to Credit Intermediation
        '5239', // Other Financial Investment Activities
        '7132', // Gambling Industries
        '4539', // Other Miscellaneous Store Retailers (includes pawn shops)
        '5331', // Lessors of Nonfinancial Intangible Assets
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(
            function ($profile) {
                if (! $profile->profile_number) {
                    $profile->profile_number = static::generateProfileNumber();
                }
            }
        );
    }

    public static function generateProfileNumber(): string
    {
        $year = date('Y');
        $lastProfile = static::whereYear('created_at', $year)
            ->orderBy('profile_number', 'desc')
            ->first();

        if ($lastProfile) {
            $lastNumber = intval(substr($lastProfile->profile_number, -5));
            $newNumber = str_pad((string) ($lastNumber + 1), 5, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '00001';
        }

        return "CRP-{$year}-{$newNumber}";
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany
     */
    public function screenings(): HasMany
    {
        return $this->hasMany(AmlScreening::class, 'entity_id')
            ->where('entity_type', User::class);
    }

    // Helper methods
    public function isHighRisk(): bool
    {
        return in_array($this->risk_rating, [self::RISK_RATING_HIGH, self::RISK_RATING_PROHIBITED]);
    }

    public function isLowRisk(): bool
    {
        return $this->risk_rating === self::RISK_RATING_LOW;
    }

    public function isProhibited(): bool
    {
        return $this->risk_rating === self::RISK_RATING_PROHIBITED;
    }

    public function requiresEnhancedDueDiligence(): bool
    {
        return $this->cdd_level === self::CDD_LEVEL_ENHANCED || $this->isHighRisk();
    }

    public function isPEP(): bool
    {
        return $this->is_pep;
    }

    public function isSanctioned(): bool
    {
        return $this->is_sanctioned;
    }

    public function hasAdverseMedia(): bool
    {
        return $this->has_adverse_media;
    }

    public function requiresReview(): bool
    {
        return $this->next_review_at && $this->next_review_at->isPast();
    }

    public function hasExpiredCDD(): bool
    {
        return $this->cdd_expires_at && $this->cdd_expires_at->isPast();
    }

    public function calculateRiskScore(): float
    {
        $score = 0;
        $weights = [
            'geographic' => 0.25,
            'product'    => 0.20,
            'channel'    => 0.15,
            'customer'   => 0.25,
            'behavioral' => 0.15,
        ];

        // Geographic risk
        if ($this->geographic_risk) {
            $geoScore = $this->calculateGeographicRisk();
            $score += $geoScore * $weights['geographic'];
        }

        // Product risk
        if ($this->product_risk) {
            $productScore = $this->calculateProductRisk();
            $score += $productScore * $weights['product'];
        }

        // Channel risk
        if ($this->channel_risk) {
            $channelScore = $this->calculateChannelRisk();
            $score += $channelScore * $weights['channel'];
        }

        // Customer risk
        $customerScore = $this->calculateCustomerRisk();
        $score += $customerScore * $weights['customer'];

        // Behavioral risk
        if ($this->behavioral_risk) {
            $behavioralScore = $this->calculateBehavioralRisk();
            $score += $behavioralScore * $weights['behavioral'];
        }

        return round($score, 2);
    }

    protected function calculateGeographicRisk(): float
    {
        $score = 0;
        $countries = $this->geographic_risk['countries'] ?? [];

        foreach ($countries as $country) {
            if (in_array($country, self::HIGH_RISK_COUNTRIES)) {
                $score = max($score, 80);
            } elseif (in_array($country, ['US', 'GB', 'DE', 'FR', 'JP', 'CA', 'AU'])) {
                $score = max($score, 20);
            } else {
                $score = max($score, 40);
            }
        }

        return $score;
    }

    protected function calculateProductRisk(): float
    {
        $score = 20; // Base score
        $products = $this->product_risk['products'] ?? [];

        if (in_array('international_wire', $products)) {
            $score = max($score, 60);
        }
        if (in_array('crypto', $products)) {
            $score = max($score, 80);
        }
        if (in_array('cash_intensive', $products)) {
            $score = max($score, 70);
        }

        return $score;
    }

    protected function calculateChannelRisk(): float
    {
        $channel = $this->channel_risk['onboarding_channel'] ?? 'online';

        return match ($channel) {
            'face_to_face'      => 10,
            'online_verified'   => 30,
            'online_unverified' => 60,
            'third_party'       => 70,
            default             => 40,
        };
    }

    protected function calculateCustomerRisk(): float
    {
        $score = 20; // Base score

        if ($this->is_pep) {
            $score = max($score, 80);
        }
        if ($this->is_sanctioned) {
            return 100; // Maximum risk
        }
        if ($this->has_adverse_media) {
            $score = max($score, 70);
        }
        if ($this->complex_structure) {
            $score = max($score, 60);
        }
        if ($this->business_type && in_array($this->industry_code, self::HIGH_RISK_INDUSTRIES)) {
            $score = max($score, 70);
        }

        return $score;
    }

    protected function calculateBehavioralRisk(): float
    {
        $score = 20; // Base score

        if ($this->suspicious_activities_count > 5) {
            $score = 80;
        } elseif ($this->suspicious_activities_count > 2) {
            $score = 60;
        } elseif ($this->suspicious_activities_count > 0) {
            $score = 40;
        }

        // Check for rapid changes in transaction patterns
        $patterns = $this->behavioral_risk['patterns'] ?? [];
        if (in_array('rapid_volume_increase', $patterns)) {
            $score = max($score, 70);
        }
        if (in_array('unusual_geography', $patterns)) {
            $score = max($score, 60);
        }

        return $score;
    }

    public function determineRiskRating(): string
    {
        $score = $this->risk_score;

        if ($this->is_sanctioned) {
            return self::RISK_RATING_PROHIBITED;
        }

        if ($score >= 70) {
            return self::RISK_RATING_HIGH;
        } elseif ($score >= 40) {
            return self::RISK_RATING_MEDIUM;
        } else {
            return self::RISK_RATING_LOW;
        }
    }

    public function determineCDDLevel(): string
    {
        if ($this->isHighRisk() || $this->is_pep) {
            return self::CDD_LEVEL_ENHANCED;
        } elseif ($this->isLowRisk() && ! $this->complex_structure) {
            return self::CDD_LEVEL_SIMPLIFIED;
        } else {
            return self::CDD_LEVEL_STANDARD;
        }
    }

    public function getTransactionLimits(): array
    {
        return [
            'daily'   => $this->daily_transaction_limit,
            'monthly' => $this->monthly_transaction_limit,
            'single'  => $this->single_transaction_limit,
        ];
    }

    public function updateRiskAssessment(): void
    {
        $this->risk_score = $this->calculateRiskScore();
        $this->risk_rating = $this->determineRiskRating();
        $this->cdd_level = $this->determineCDDLevel();
        $this->last_assessment_at = now();
        $this->next_review_at = $this->calculateNextReviewDate();

        // Add to risk history
        $history = $this->risk_history ?? [];
        $history[] = [
            'date'    => now()->toIso8601String(),
            'score'   => $this->risk_score,
            'rating'  => $this->risk_rating,
            'factors' => $this->getRiskFactorsSummary(),
        ];
        $this->risk_history = $history;

        $this->save();
    }

    protected function calculateNextReviewDate(): \Carbon\Carbon
    {
        return match ($this->risk_rating) {
            self::RISK_RATING_HIGH   => now()->addMonths(3),
            self::RISK_RATING_MEDIUM => now()->addMonths(6),
            self::RISK_RATING_LOW    => now()->addYear(),
            default                  => now()->addMonth(),
        };
    }

    protected function getRiskFactorsSummary(): array
    {
        $factors = [];

        if ($this->is_pep) {
            $factors[] = 'PEP';
        }
        if ($this->is_sanctioned) {
            $factors[] = 'Sanctioned';
        }
        if ($this->has_adverse_media) {
            $factors[] = 'Adverse Media';
        }
        if ($this->complex_structure) {
            $factors[] = 'Complex Structure';
        }
        if ($this->suspicious_activities_count > 0) {
            $factors[] = 'Suspicious Activities';
        }

        return $factors;
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
