<?php

declare(strict_types=1);

namespace App\Domain\X402\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * Tracks active paid WebSocket channel subscriptions.
 *
 * @property string $id
 * @property int|null $user_id
 * @property string|null $agent_id
 * @property string $channel
 * @property string $protocol
 * @property string|null $payment_id
 * @property string|null $amount
 * @property string|null $network
 * @property \Illuminate\Support\Carbon $expires_at
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class WebSocketSubscription extends Model
{
    use HasUuids;

    protected $table = 'websocket_subscriptions';

    protected $fillable = [
        'user_id',
        'agent_id',
        'channel',
        'protocol',
        'payment_id',
        'amount',
        'network',
        'expires_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    /**
     * Scope to active (non-expired) subscriptions.
     *
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('expires_at', '>', now());
    }

    /**
     * Scope to a specific channel.
     *
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForChannel(Builder $query, string $channel): Builder
    {
        return $query->where('channel', $channel);
    }

    /**
     * Scope to a specific user.
     *
     * @param Builder<self> $query
     * @return Builder<self>
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Check if this subscription is still active.
     */
    public function isActive(): bool
    {
        return $this->expires_at->isFuture();
    }
}
