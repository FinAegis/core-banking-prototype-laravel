<?php

namespace App\Domain\Webhook\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
class Webhook extends Model
{
    use UsesTenantConnection;
    use HasFactory;
    use HasUuids;

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
        'events'               => 'array',
        'headers'              => 'array',
        'is_active'            => 'boolean',
        'retry_attempts'       => 'integer',
        'timeout_seconds'      => 'integer',
        'consecutive_failures' => 'integer',
        'last_triggered_at'    => 'datetime',
        'last_success_at'      => 'datetime',
        'last_failure_at'      => 'datetime',
    ];

    /**
     * Available webhook events.
     */
    public const EVENTS = [
        'account.created'      => 'Account Created',
        'account.updated'      => 'Account Updated',
        'account.frozen'       => 'Account Frozen',
        'account.unfrozen'     => 'Account Unfrozen',
        'account.closed'       => 'Account Closed',
        'transaction.created'  => 'Transaction Created',
        'transaction.reversed' => 'Transaction Reversed',
        'transfer.created'     => 'Transfer Created',
        'transfer.completed'   => 'Transfer Completed',
        'transfer.failed'      => 'Transfer Failed',
        'balance.low'          => 'Low Balance Alert',
        'balance.negative'     => 'Negative Balance Alert',
    ];

    /**
     * Get the deliveries for the webhook.
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'webhook_uuid', 'uuid');
    }

    /**
     * Scope to get active webhooks.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to get webhooks subscribed to a specific event.
     */
    public function scopeForEvent($query, string $event)
    {
        return $query->whereJsonContains('events', $event);
    }

    /**
     * Check if webhook is subscribed to an event.
     */
    public function isSubscribedTo(string $event): bool
    {
        return in_array($event, $this->events ?? []);
    }

    /**
     * Mark webhook as triggered.
     */
    public function markAsTriggered(): void
    {
        $this->update(['last_triggered_at' => now()]);
    }

    /**
     * Mark webhook delivery as successful.
     */
    public function markAsSuccessful(): void
    {
        $this->update(
            [
            'last_success_at'      => now(),
            'consecutive_failures' => 0,
            ]
        );
    }

    /**
     * Mark webhook delivery as failed.
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
