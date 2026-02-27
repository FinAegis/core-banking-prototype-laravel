<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Cached shielded balance model.
 *
 * Stores a local cache of the user's shielded token balances per network,
 * synced from the RAILGUN bridge service. The bridge queries balances from
 * the on-chain privacy pool state.
 *
 * @property int $id
 * @property int $user_id
 * @property string $railgun_address
 * @property string $token
 * @property string $network
 * @property string $balance
 * @property \Carbon\Carbon|null $last_synced_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ShieldedBalance extends Model
{
    protected $table = 'shielded_balances';

    protected $fillable = [
        'user_id',
        'railgun_address',
        'token',
        'network',
        'balance',
        'last_synced_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_synced_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope for balances owned by a user.
     *
     * @param \Illuminate\Database\Eloquent\Builder<ShieldedBalance> $query
     * @return \Illuminate\Database\Eloquent\Builder<ShieldedBalance>
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for balances on a specific network.
     *
     * @param \Illuminate\Database\Eloquent\Builder<ShieldedBalance> $query
     * @return \Illuminate\Database\Eloquent\Builder<ShieldedBalance>
     */
    public function scopeForNetwork($query, string $network)
    {
        return $query->where('network', $network);
    }

    /**
     * Check if the balance is stale (older than the given seconds).
     */
    public function isStale(int $maxAgeSeconds = 60): bool
    {
        if ($this->last_synced_at === null) {
            return true;
        }

        return $this->last_synced_at->diffInSeconds(now()) > $maxAgeSeconds;
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiResponse(): array
    {
        return [
            'token'          => $this->token,
            'balance'        => $this->balance,
            'network'        => $this->network,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
        ];
    }
}
