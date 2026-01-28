<?php

namespace App\Domain\Compliance\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Crypt;

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
class KycVerification extends Model
{
    use UsesTenantConnection;
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'verification_number',
        'type',
        'status',
        'provider',
        'provider_reference',
        'verification_data',
        'extracted_data',
        'checks_performed',
        'confidence_score',
        'document_type',
        'document_number',
        'document_country',
        'document_expiry',
        'first_name',
        'last_name',
        'middle_name',
        'date_of_birth',
        'gender',
        'nationality',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
        'country',
        'risk_level',
        'risk_factors',
        'pep_check',
        'sanctions_check',
        'adverse_media_check',
        'started_at',
        'completed_at',
        'expires_at',
        'failure_reason',
        'verification_report',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'verification_data'   => 'array',
        'extracted_data'      => 'array',
        'checks_performed'    => 'array',
        'risk_factors'        => 'array',
        'verification_report' => 'array',
        'confidence_score'    => 'decimal:2',
        'pep_check'           => 'boolean',
        'sanctions_check'     => 'boolean',
        'adverse_media_check' => 'boolean',
        'document_expiry'     => 'date',
        'date_of_birth'       => 'date',
        'started_at'          => 'datetime',
        'completed_at'        => 'datetime',
        'expires_at'          => 'datetime',
        'reviewed_at'         => 'datetime',
    ];

    // Encrypted fields
    protected array $encryptedFields = [
        'first_name',
        'last_name',
        'middle_name',
        'address_line1',
        'address_line2',
        'city',
        'state',
        'postal_code',
    ];

    public const TYPE_IDENTITY = 'identity';

    public const TYPE_ADDRESS = 'address';

    public const TYPE_INCOME = 'income';

    public const TYPE_ENHANCED_DUE_DILIGENCE = 'enhanced_due_diligence';

    public const STATUS_PENDING = 'pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const RISK_LEVEL_LOW = 'low';

    public const RISK_LEVEL_MEDIUM = 'medium';

    public const RISK_LEVEL_HIGH = 'high';

    public const VERIFICATION_TYPES = [
        self::TYPE_IDENTITY               => 'Identity Verification',
        self::TYPE_ADDRESS                => 'Address Verification',
        self::TYPE_INCOME                 => 'Income Verification',
        self::TYPE_ENHANCED_DUE_DILIGENCE => 'Enhanced Due Diligence',
    ];

    public const DOCUMENT_TYPES = [
        'passport'         => 'Passport',
        'driving_license'  => 'Driving License',
        'national_id'      => 'National ID Card',
        'residence_permit' => 'Residence Permit',
        'utility_bill'     => 'Utility Bill',
        'bank_statement'   => 'Bank Statement',
        'tax_return'       => 'Tax Return',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(
            function ($verification) {
                if (! $verification->verification_number) {
                    $verification->verification_number = static::generateVerificationNumber();
                }
            }
        );
    }

    public static function generateVerificationNumber(): string
    {
        $year = date('Y');
        $lastVerification = static::whereYear('created_at', $year)
            ->orderBy('verification_number', 'desc')
            ->first();

        if ($lastVerification) {
            $lastNumber = intval(substr($lastVerification->verification_number, -5));
            $newNumber = str_pad((string) ($lastNumber + 1), 5, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '00001';
        }

        return "KYC-{$year}-{$newNumber}";
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    // Accessors and Mutators for encrypted fields
    public function setFirstNameAttribute($value)
    {
        $this->attributes['first_name'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getFirstNameAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setLastNameAttribute($value)
    {
        $this->attributes['last_name'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getLastNameAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setMiddleNameAttribute($value)
    {
        $this->attributes['middle_name'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getMiddleNameAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setAddressLine1Attribute($value)
    {
        $this->attributes['address_line1'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getAddressLine1Attribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setAddressLine2Attribute($value)
    {
        $this->attributes['address_line2'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getAddressLine2Attribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setCityAttribute($value)
    {
        $this->attributes['city'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getCityAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setStateAttribute($value)
    {
        $this->attributes['state'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getStateAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    public function setPostalCodeAttribute($value)
    {
        $this->attributes['postal_code'] = $value ? Crypt::encryptString($value) : null;
    }

    public function getPostalCodeAttribute($value)
    {
        return $value ? Crypt::decryptString($value) : null;
    }

    // Helper methods
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED ||
               ($this->expires_at && $this->expires_at->isPast());
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isHighRisk(): bool
    {
        return $this->risk_level === self::RISK_LEVEL_HIGH;
    }

    public function hasPassedAllChecks(): bool
    {
        return $this->pep_check && $this->sanctions_check && $this->adverse_media_check;
    }

    public function getFullName(): string
    {
        $parts = array_filter(
            [
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            ]
        );

        return implode(' ', $parts);
    }

    public function getFullAddress(): string
    {
        $parts = array_filter(
            [
            $this->address_line1,
            $this->address_line2,
            $this->city,
            $this->state,
            $this->postal_code,
            $this->country,
            ]
        );

        return implode(', ', $parts);
    }

    public function markAsCompleted(array $data = []): void
    {
        $this->update(
            array_merge(
                $data,
                [
                'status'       => self::STATUS_COMPLETED,
                'completed_at' => now(),
                ]
            )
        );
    }

    public function markAsFailed(string $reason): void
    {
        $this->update(
            [
            'status'         => self::STATUS_FAILED,
            'failure_reason' => $reason,
            'completed_at'   => now(),
            ]
        );
    }

    public function markAsExpired(): void
    {
        $this->update(
            [
            'status' => self::STATUS_EXPIRED,
            ]
        );
    }

    public function calculateConfidenceScore(): float
    {
        $score = 0;
        $factors = 0;

        // Document verification
        if ($this->document_type && $this->document_number) {
            $score += 20;
            $factors++;
        }

        // Biometric/selfie check
        if (isset($this->verification_data['biometric_match'])) {
            $score += 30;
            $factors++;
        }

        // Data consistency
        if (isset($this->extracted_data['data_consistency'])) {
            $score += 20;
            $factors++;
        }

        // Risk checks
        if ($this->pep_check && $this->sanctions_check && $this->adverse_media_check) {
            $score += 30;
            $factors++;
        }

        return $factors > 0 ? round($score, 2) : 0;
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
