<?php

namespace App\Domain\Regulatory\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
class RegulatoryFilingRecord extends Model
{
    use UsesTenantConnection;
    use HasUuids;

    protected $fillable = [
        'regulatory_report_id',
        'filing_id',
        'filing_type',
        'filing_method',
        'filing_status',
        'filing_attempt',
        'filed_at',
        'filed_by',
        'filing_credentials',
        'filing_reference',
        'filing_request',
        'filing_response',
        'response_code',
        'response_message',
        'acknowledged_at',
        'acknowledgment_number',
        'acknowledgment_details',
        'passed_validation',
        'validation_errors',
        'warnings',
        'requires_retry',
        'retry_after',
        'retry_count',
        'max_retries',
        'audit_log',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'filed_at'               => 'datetime',
        'acknowledged_at'        => 'datetime',
        'retry_after'            => 'datetime',
        'filing_credentials'     => 'encrypted:array',
        'filing_request'         => 'array',
        'filing_response'        => 'array',
        'acknowledgment_details' => 'array',
        'validation_errors'      => 'array',
        'warnings'               => 'array',
        'audit_log'              => 'array',
        'metadata'               => 'array',
        'passed_validation'      => 'boolean',
        'requires_retry'         => 'boolean',
    ];

    // Filing types
    public const TYPE_INITIAL = 'initial';

    public const TYPE_AMENDMENT = 'amendment';

    public const TYPE_CORRECTION = 'correction';

    // Filing methods
    public const METHOD_API = 'api';

    public const METHOD_MANUAL = 'manual';

    public const METHOD_EMAIL = 'email';

    public const METHOD_PORTAL = 'portal';

    // Filing statuses
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_ACKNOWLEDGED = 'acknowledged';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_FAILED = 'failed';

    // Boot method
    protected static function boot()
    {
        parent::boot();

        static::creating(
            function ($record) {
                if (empty($record->filing_id)) {
                    $record->filing_id = self::generateFilingId();
                }

                if (empty($record->filed_at)) {
                    $record->filed_at = now();
                }

                // Capture request metadata
                if (empty($record->ip_address)) {
                    $record->ip_address = request()->ip();
                }

                if (empty($record->user_agent)) {
                    $record->user_agent = request()->userAgent();
                }
            }
        );
    }

    // Relationships
    public function report(): BelongsTo
    {
        return $this->belongsTo(RegulatoryReport::class, 'regulatory_report_id');
    }

    // Helper methods
    public static function generateFilingId(): string
    {
        $year = now()->format('Y');
        $month = now()->format('m');

        $lastFiling = self::where('filing_id', 'like', "FIL-{$year}{$month}-%")
            ->orderBy('filing_id', 'desc')
            ->first();

        if ($lastFiling) {
            $lastNumber = intval(substr($lastFiling->filing_id, -6));
            $newNumber = str_pad($lastNumber + 1, 6, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '000001';
        }

        return "FIL-{$year}{$month}-{$newNumber}";
    }

    public function markAsSubmitted(?string $reference = null): void
    {
        $this->update(
            [
            'filing_status'    => self::STATUS_SUBMITTED,
            'filing_reference' => $reference,
            ]
        );

        $this->addAuditEntry(
            'submitted',
            [
            'reference' => $reference,
            ]
        );
    }

    public function markAsAcknowledged(string $acknowledgmentNumber, array $details = []): void
    {
        $this->update(
            [
            'filing_status'          => self::STATUS_ACKNOWLEDGED,
            'acknowledged_at'        => now(),
            'acknowledgment_number'  => $acknowledgmentNumber,
            'acknowledgment_details' => $details,
            ]
        );

        $this->addAuditEntry(
            'acknowledged',
            [
            'acknowledgment_number' => $acknowledgmentNumber,
            ]
        );
    }

    public function markAsAccepted(): void
    {
        $this->update(
            [
            'filing_status'     => self::STATUS_ACCEPTED,
            'passed_validation' => true,
            ]
        );

        $this->addAuditEntry('accepted');

        // Update parent report status
        $this->report->update(['status' => RegulatoryReport::STATUS_ACCEPTED]);
    }

    public function markAsRejected(string $reason, array $errors = []): void
    {
        $this->update(
            [
            'filing_status'     => self::STATUS_REJECTED,
            'response_message'  => $reason,
            'validation_errors' => $errors,
            'requires_retry'    => true,
            'retry_after'       => now()->addHours(1),
            ]
        );

        $this->addAuditEntry(
            'rejected',
            [
            'reason' => $reason,
            'errors' => $errors,
            ]
        );

        // Update parent report status
        $this->report->update(['status' => RegulatoryReport::STATUS_REJECTED]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update(
            [
            'filing_status'    => self::STATUS_FAILED,
            'response_message' => $error,
            'requires_retry'   => $this->retry_count < $this->max_retries,
            'retry_after'      => now()->addMinutes(30 * ($this->retry_count + 1)), // Exponential backoff
            ]
        );

        $this->addAuditEntry(
            'failed',
            [
            'error'       => $error,
            'retry_count' => $this->retry_count,
            ]
        );
    }

    public function recordResponse(int $code, string $message, array $response = []): void
    {
        $this->update(
            [
            'response_code'    => $code,
            'response_message' => $message,
            'filing_response'  => $response,
            ]
        );
    }

    public function recordValidationErrors(array $errors): void
    {
        $this->update(
            [
            'passed_validation' => false,
            'validation_errors' => $errors,
            ]
        );
    }

    public function recordWarnings(array $warnings): void
    {
        $this->update(
            [
            'warnings' => $warnings,
            ]
        );
    }

    public function incrementRetryCount(): void
    {
        $this->increment('retry_count');
        $this->update(
            [
            'filing_attempt' => $this->filing_attempt + 1,
            ]
        );
    }

    public function canRetry(): bool
    {
        return $this->requires_retry &&
               $this->retry_count < $this->max_retries &&
               (! $this->retry_after || $this->retry_after->isPast());
    }

    public function shouldRetry(): bool
    {
        return $this->canRetry() &&
               in_array($this->filing_status, [self::STATUS_FAILED, self::STATUS_REJECTED]);
    }

    public function addAuditEntry(string $action, array $data = []): void
    {
        $auditLog = $this->audit_log ?? [];

        $auditLog[] = [
            'action'    => $action,
            'timestamp' => now()->toIso8601String(),
            'user'      => auth()->user()?->name ?? 'System',
            'data'      => $data,
        ];

        $this->update(['audit_log' => $auditLog]);
    }

    public function getProcessingTime(): ?string
    {
        if (! $this->acknowledged_at) {
            return null;
        }

        return $this->filed_at->diffForHumans(
            $this->acknowledged_at,
            [
            'parts' => 2,
            'short' => true,
            ]
        );
    }

    public function getStatusLabel(): string
    {
        return match ($this->filing_status) {
            self::STATUS_PENDING      => 'Pending',
            self::STATUS_SUBMITTED    => 'Submitted',
            self::STATUS_ACKNOWLEDGED => 'Acknowledged',
            self::STATUS_ACCEPTED     => 'Accepted',
            self::STATUS_REJECTED     => 'Rejected',
            self::STATUS_FAILED       => 'Failed',
            default                   => 'Unknown',
        };
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('filing_status', self::STATUS_PENDING);
    }

    public function scopeSuccessful($query)
    {
        return $query->whereIn('filing_status', [self::STATUS_ACCEPTED, self::STATUS_ACKNOWLEDGED]);
    }

    public function scopeFailed($query)
    {
        return $query->whereIn('filing_status', [self::STATUS_FAILED, self::STATUS_REJECTED]);
    }

    public function scopeRequiringRetry($query)
    {
        return $query->where('requires_retry', true)
            ->where('retry_count', '<', DB::raw('max_retries'))
            ->where(
                function ($q) {
                    $q->whereNull('retry_after')
                        ->orWhere('retry_after', '<=', now());
                }
            );
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
