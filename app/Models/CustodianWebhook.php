<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustodianWebhook extends Model
{
    use HasFactory;
    use HasUuids;

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'custodian_name',
        'event_type',
        'event_id',
        'headers',
        'payload',
        'signature',
        'status',
        'attempts',
        'processed_at',
        'error_message',
        'custodian_account_id',
        'transaction_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'headers' => 'array',
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    /**
     * The attributes that should have default values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'attempts' => 0,
    ];

    /**
     * Get the custodian account associated with this webhook.
     */
    public function custodianAccount(): BelongsTo
    {
        return $this->belongsTo(CustodianAccount::class, 'custodian_account_id', 'uuid');
    }

    /**
     * Scope a query to only include pending webhooks.
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include failed webhooks that can be retried.
     */
    public function scopeRetryable($query, int $maxAttempts = 3)
    {
        return $query->where('status', 'failed')
                     ->where('attempts', '<', $maxAttempts);
    }

    /**
     * Scope a query to only include failed webhooks.
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope a query to only include webhooks for a specific custodian.
     */
    public function scopeByCustodian($query, string $custodianName)
    {
        return $query->where('custodian_name', $custodianName);
    }

    /**
     * Mark the webhook as processing.
     */
    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'attempts' => $this->attempts + 1,
        ]);
    }

    /**
     * Mark the webhook as processed.
     */
    public function markAsProcessed(): void
    {
        $this->update([
            'status' => 'processed',
            'processed_at' => now(),
            'error_message' => null,
        ]);
    }

    /**
     * Mark the webhook as failed.
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Mark the webhook as ignored.
     */
    public function markAsIgnored(string $reason = null): void
    {
        $this->update([
            'status' => 'ignored',
            'processed_at' => now(),
            'error_message' => $reason,
        ]);
    }
}
