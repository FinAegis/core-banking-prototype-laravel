<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    use HasFactory, HasUuids;

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
        'payload' => 'array',
        'response_headers' => 'array',
        'attempt_number' => 'integer',
        'response_status' => 'integer',
        'duration_ms' => 'integer',
        'delivered_at' => 'datetime',
        'next_retry_at' => 'datetime',
    ];

    /**
     * Delivery statuses
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
     * Scope to get pending deliveries
     */
    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to get failed deliveries
     */
    public function scopeFailed($query)
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Scope to get deliveries ready for retry
     */
    public function scopeReadyForRetry($query)
    {
        return $query->where('status', self::STATUS_FAILED)
            ->where('next_retry_at', '<=', now());
    }

    /**
     * Mark delivery as successful
     */
    public function markAsDelivered(int $statusCode, ?string $responseBody = null, ?array $responseHeaders = null, int $durationMs = 0): void
    {
        $this->update([
            'status' => self::STATUS_DELIVERED,
            'response_status' => $statusCode,
            'response_body' => $responseBody,
            'response_headers' => $responseHeaders,
            'duration_ms' => $durationMs,
            'delivered_at' => now(),
        ]);

        $this->webhook->markAsSuccessful();
    }

    /**
     * Mark delivery as failed
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

        $this->update([
            'status' => self::STATUS_FAILED,
            'error_message' => $errorMessage,
            'response_status' => $statusCode,
            'response_body' => $responseBody,
            'next_retry_at' => $nextRetryAt,
        ]);

        $this->webhook->markAsFailed();
    }

    /**
     * Create a retry delivery
     */
    public function createRetry(): self
    {
        return self::create([
            'webhook_uuid' => $this->webhook_uuid,
            'event_type' => $this->event_type,
            'payload' => $this->payload,
            'attempt_number' => $this->attempt_number + 1,
            'status' => self::STATUS_PENDING,
        ]);
    }
}