<?php

namespace App\Domain\Webhook\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
class WebhookDelivery extends Model
{
    use UsesTenantConnection;
    use HasFactory;
    use HasUuids;

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'uuid',
        'webhook_uuid',
        'event_type',
        'payload',
        'attempt_number',
        'status',
        'response_status',
        'response_body',
        'response_headers',
        'duration_ms',
        'error_message',
        'delivered_at',
        'next_retry_at',
    ];

    protected $casts = [
        'payload'          => 'array',
        'response_headers' => 'array',
        'attempt_number'   => 'integer',
        'response_status'  => 'integer',
        'duration_ms'      => 'integer',
        'delivered_at'     => 'datetime',
        'next_retry_at'    => 'datetime',
    ];

    /**
     * Delivery statuses.
     */
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_DELIVERED = 'delivered';

    public const STATUS_FAILED = 'failed';

    /**
     * Get the webhook that owns the delivery.
     */
    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class, 'webhook_uuid', 'uuid');
    }

    /**
     * Scope to get pending deliveries.
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to get failed deliveries.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope to get deliveries ready for retry.
     */
    public function scopeReadyForRetry($query)
    {
        return $query->where('status', self::STATUS_FAILED)
            ->where('next_retry_at', '<=', now());
    }

    /**
     * Mark delivery as successful.
     */
    public function markAsDelivered(int $statusCode, ?string $responseBody = null, ?array $responseHeaders = null, int $durationMs = 0): void
    {
        $this->update(
            [
            'status'           => self::STATUS_DELIVERED,
            'response_status'  => $statusCode,
            'response_body'    => $responseBody,
            'response_headers' => $responseHeaders,
            'duration_ms'      => $durationMs,
            'delivered_at'     => now(),
            ]
        );

        $this->webhook->markAsSuccessful();
    }

    /**
     * Mark delivery as failed.
     */
    public function markAsFailed(?string $errorMessage = null, ?int $statusCode = null, ?string $responseBody = null): void
    {
        $maxAttempts = $this->webhook->retry_attempts;
        $nextRetryAt = null;

        if ($this->attempt_number < $maxAttempts) {
            // Exponential backoff: 1min, 5min, 15min, etc.
            $delayMinutes = pow(2, $this->attempt_number) * 5;
            $nextRetryAt = now()->addMinutes($delayMinutes);
        }

        $this->update(
            [
            'status'          => self::STATUS_FAILED,
            'error_message'   => $errorMessage,
            'response_status' => $statusCode,
            'response_body'   => $responseBody,
            'next_retry_at'   => $nextRetryAt,
            ]
        );

        $this->webhook->markAsFailed();
    }

    /**
     * Create a retry delivery.
     */
    public function createRetry(): self
    {
        return self::create(
            [
            'webhook_uuid'   => $this->webhook_uuid,
            'event_type'     => $this->event_type,
            'payload'        => $this->payload,
            'attempt_number' => $this->attempt_number + 1,
            'status'         => self::STATUS_PENDING,
            ]
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
