<?php

namespace App\Domain\FinancialInstitution\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

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
class FinancialInstitutionApplication extends Model
{
    use UsesTenantConnection;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'application_number',
        'institution_name',
        'legal_name',
        'registration_number',
        'tax_id',
        'country',
        'institution_type',
        'assets_under_management',
        'years_in_operation',
        'primary_regulator',
        'regulatory_license_number',
        'contact_name',
        'contact_email',
        'contact_phone',
        'contact_position',
        'contact_department',
        'headquarters_address',
        'headquarters_city',
        'headquarters_state',
        'headquarters_postal_code',
        'headquarters_country',
        'business_description',
        'target_markets',
        'product_offerings',
        'expected_monthly_transactions',
        'expected_monthly_volume',
        'required_currencies',
        'integration_requirements',
        'requires_api_access',
        'requires_webhooks',
        'requires_reporting',
        'security_certifications',
        'has_aml_program',
        'has_kyc_procedures',
        'has_data_protection_policy',
        'is_pci_compliant',
        'is_gdpr_compliant',
        'compliance_certifications',
        'status',
        'review_stage',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
        'rejection_reason',
        'risk_rating',
        'risk_factors',
        'risk_score',
        'required_documents',
        'submitted_documents',
        'documents_verified',
        'agreement_start_date',
        'agreement_end_date',
        'fee_structure',
        'service_level_agreement',
        'partner_id',
        'api_client_id',
        'sandbox_access_granted',
        'production_access_granted',
        'onboarding_completed_at',
        'metadata',
        'source',
        'referral_code',
    ];

    protected $casts = [
        'assets_under_management'    => 'decimal:2',
        'expected_monthly_volume'    => 'decimal:2',
        'risk_score'                 => 'decimal:2',
        'target_markets'             => 'array',
        'product_offerings'          => 'array',
        'required_currencies'        => 'array',
        'integration_requirements'   => 'array',
        'security_certifications'    => 'array',
        'compliance_certifications'  => 'array',
        'risk_factors'               => 'array',
        'required_documents'         => 'array',
        'submitted_documents'        => 'array',
        'fee_structure'              => 'array',
        'service_level_agreement'    => 'array',
        'metadata'                   => 'array',
        'requires_api_access'        => 'boolean',
        'requires_webhooks'          => 'boolean',
        'requires_reporting'         => 'boolean',
        'has_aml_program'            => 'boolean',
        'has_kyc_procedures'         => 'boolean',
        'has_data_protection_policy' => 'boolean',
        'is_pci_compliant'           => 'boolean',
        'is_gdpr_compliant'          => 'boolean',
        'documents_verified'         => 'boolean',
        'sandbox_access_granted'     => 'boolean',
        'production_access_granted'  => 'boolean',
        'reviewed_at'                => 'datetime',
        'onboarding_completed_at'    => 'datetime',
        'agreement_start_date'       => 'date',
        'agreement_end_date'         => 'date',
    ];

    /**
     * Available institution types.
     */
    public const INSTITUTION_TYPES = [
        'bank'              => 'Commercial Bank',
        'credit_union'      => 'Credit Union',
        'investment_firm'   => 'Investment Firm',
        'payment_processor' => 'Payment Processor',
        'fintech'           => 'FinTech Company',
        'emi'               => 'Electronic Money Institution',
        'broker_dealer'     => 'Broker-Dealer',
        'insurance'         => 'Insurance Company',
        'other'             => 'Other Financial Institution',
    ];

    /**
     * Application statuses.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_ON_HOLD = 'on_hold';

    /**
     * Review stages.
     */
    public const STAGE_INITIAL = 'initial';

    public const STAGE_COMPLIANCE = 'compliance';

    public const STAGE_TECHNICAL = 'technical';

    public const STAGE_LEGAL = 'legal';

    public const STAGE_FINAL = 'final';

    /**
     * Risk ratings.
     */
    public const RISK_LOW = 'low';

    public const RISK_MEDIUM = 'medium';

    public const RISK_HIGH = 'high';

    /**
     * Boot method.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(
            function ($application) {
                if (empty($application->application_number)) {
                    $application->application_number = static::generateApplicationNumber();
                }
            }
        );
    }

    /**
     * Generate unique application number.
     */
    public static function generateApplicationNumber(): string
    {
        $year = date('Y');
        $lastApplication = static::whereYear('created_at', $year)
            ->orderBy('application_number', 'desc')
            ->first();

        if ($lastApplication) {
            $lastNumber = intval(substr($lastApplication->application_number, -5));
            $newNumber = str_pad((string) ($lastNumber + 1), 5, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '00001';
        }

        return "FIA-{$year}-{$newNumber}";
    }

    /**
     * Get the reviewer.
     */
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by', 'uuid');
    }

    /**
     * Get the partner (after approval).
     */
    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function partner()
    {
        return $this->belongsTo(FinancialInstitutionPartner::class, 'partner_id', 'id');
    }

    /**
     * Scope for pending applications.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope for under review applications.
     */
    public function scopeUnderReview($query)
    {
        return $query->where('status', self::STATUS_UNDER_REVIEW);
    }

    /**
     * Scope for approved applications.
     */
    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_APPROVED);
    }

    /**
     * Check if application is editable.
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_ON_HOLD]);
    }

    /**
     * Check if application is reviewable.
     */
    public function isReviewable(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_UNDER_REVIEW]);
    }

    /**
     * Get required documents based on institution type.
     */
    public function getRequiredDocuments(): array
    {
        $baseDocuments = [
            'certificate_of_incorporation' => 'Certificate of Incorporation',
            'regulatory_license'           => 'Regulatory License',
            'audited_financials'           => 'Audited Financial Statements (Last 3 Years)',
            'aml_policy'                   => 'AML/KYC Policy Document',
            'data_protection_policy'       => 'Data Protection Policy',
        ];

        // Add type-specific documents
        switch ($this->institution_type) {
            case 'bank':
                $baseDocuments['banking_license'] = 'Banking License';
                $baseDocuments['basel_compliance'] = 'Basel III Compliance Certificate';
                break;
            case 'investment_firm':
                $baseDocuments['mifid_compliance'] = 'MiFID II Compliance Certificate';
                break;
            case 'payment_processor':
                $baseDocuments['pci_certificate'] = 'PCI-DSS Compliance Certificate';
                break;
        }

        return $baseDocuments;
    }

    /**
     * Calculate risk score.
     */
    public function calculateRiskScore(): float
    {
        $score = 0;
        $factors = [];

        // Country risk (simplified)
        $highRiskCountries = ['AF', 'IR', 'KP', 'MM', 'SY', 'YE'];
        if (in_array($this->country, $highRiskCountries)) {
            $score += 30;
            $factors[] = 'high_risk_country';
        }

        // Institution type risk
        $typeRisk = [
            'bank'              => 10,
            'credit_union'      => 10,
            'investment_firm'   => 15,
            'payment_processor' => 20,
            'fintech'           => 25,
            'emi'               => 20,
            'broker_dealer'     => 15,
            'insurance'         => 10,
            'other'             => 30,
        ];
        $score += $typeRisk[$this->institution_type] ?? 30;

        // Compliance factors
        if (! $this->has_aml_program) {
            $score += 20;
            $factors[] = 'no_aml_program';
        }
        if (! $this->has_kyc_procedures) {
            $score += 15;
            $factors[] = 'no_kyc_procedures';
        }
        if (! $this->is_pci_compliant && $this->institution_type === 'payment_processor') {
            $score += 15;
            $factors[] = 'no_pci_compliance';
        }

        // Size factor (larger institutions typically lower risk)
        if ($this->assets_under_management && $this->assets_under_management < 10000000) {
            $score += 10;
            $factors[] = 'small_institution';
        }

        // Years in operation
        if ($this->years_in_operation < 3) {
            $score += 15;
            $factors[] = 'new_institution';
        }

        // Update risk assessment
        $this->risk_score = min($score, 100);
        $this->risk_factors = $factors;

        // Determine risk rating
        if ($score <= 30) {
            $this->risk_rating = self::RISK_LOW;
        } elseif ($score <= 60) {
            $this->risk_rating = self::RISK_MEDIUM;
        } else {
            $this->risk_rating = self::RISK_HIGH;
        }

        return $this->risk_score;
    }

    /**
     * Get display status.
     */
    public function getStatusBadgeColor(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING      => 'warning',
            self::STATUS_UNDER_REVIEW => 'info',
            self::STATUS_APPROVED     => 'success',
            self::STATUS_REJECTED     => 'danger',
            self::STATUS_ON_HOLD      => 'secondary',
            default                   => 'secondary',
        };
    }

    /**
     * Get risk rating color.
     */
    public function getRiskRatingColor(): string
    {
        return match ($this->risk_rating) {
            self::RISK_LOW    => 'success',
            self::RISK_MEDIUM => 'warning',
            self::RISK_HIGH   => 'danger',
            default           => 'secondary',
        };
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
