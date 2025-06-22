<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Webhook extends Model
{
    use HasFactory, HasUuids;

    protected $primaryKey = 'uuid';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'uuid',
        'name',
        'description',
        'url',
        'events',
        'headers',
        'secret',
        'is_active',
        'retry_attempts',
        'timeout_seconds',
        'last_triggered_at',
        'last_success_at',
        'last_failure_at',
        'consecutive_failures',
    ];

    protected $casts = [
        'events' => 'array',
        'headers' => 'array',
        'is_active' => 'boolean',
        'retry_attempts' => 'integer',
        'timeout_seconds' => 'integer',
        'consecutive_failures' => 'integer',
        'last_triggered_at' => 'datetime',
        'last_success_at' => 'datetime',
        'last_failure_at' => 'datetime',
    ];

    /**
     * Available webhook events
     */
    public const EVENTS = [
        'account.created' => 'Account Created',
        'account.updated' => 'Account Updated',
        'account.frozen' => 'Account Frozen',
        'account.unfrozen' => 'Account Unfrozen',
        'account.closed' => 'Account Closed',
        'transaction.created' => 'Transaction Created',
        'transaction.reversed' => 'Transaction Reversed',
        'transfer.created' => 'Transfer Created',
        'transfer.completed' => 'Transfer Completed',
        'transfer.failed' => 'Transfer Failed',
        'balance.low' => 'Low Balance Alert',
        'balance.negative' => 'Negative Balance Alert',
    ];

    /**
     * Get the deliveries for the webhook.
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'webhook_uuid', 'uuid');
    }

    /**
     * Scope to get active webhooks
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get webhooks subscribed to a specific event
     */
    public function scopeForEvent($query, string $event)
    {
        return $query->whereJsonContains('events', $event);
    }

    /**
     * Check if webhook is subscribed to an event
     */
    public function isSubscribedTo(string $event): bool
    {
        return in_array($event, $this->events ?? []);
    }

    /**
     * Mark webhook as triggered
     */
    public function markAsTriggered(): void
    {
        $this->update(['last_triggered_at' => now()]);
    }

    /**
     * Mark webhook delivery as successful
     */
    public function markAsSuccessful(): void
    {
        $this->update([
            'last_success_at' => now(),
            'consecutive_failures' => 0,
        ]);
    }

    /**
     * Mark webhook delivery as failed
     */
    public function markAsFailed(): void
    {
        $this->increment('consecutive_failures');
        $this->update(['last_failure_at' => now()]);

        // Auto-disable webhook after too many consecutive failures
        if ($this->consecutive_failures >= 10) {
            $this->update(['is_active' => false]);
        }
    }
}