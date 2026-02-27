<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RAILGUN wallet model.
 *
 * Stores the encrypted mnemonic and 0zk... address for each user's RAILGUN wallet.
 * Each user may have one wallet per network.
 *
 * @property string $id
 * @property int $user_id
 * @property string $railgun_address
 * @property string $encrypted_mnemonic
 * @property string $network
 * @property int $last_scan_block
 * @property string $status
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class RailgunWallet extends Model
{
    use HasUuids;

    protected $table = 'railgun_wallets';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_DISABLED = 'disabled';

    protected $fillable = [
        'user_id',
        'railgun_address',
        'encrypted_mnemonic',
        'network',
        'last_scan_block',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'encrypted_mnemonic' => 'encrypted',
            'last_scan_block'    => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    /**
     * Scope for wallets owned by a user.
     *
     * @param \Illuminate\Database\Eloquent\Builder<RailgunWallet> $query
     * @return \Illuminate\Database\Eloquent\Builder<RailgunWallet>
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for wallets on a specific network.
     *
     * @param \Illuminate\Database\Eloquent\Builder<RailgunWallet> $query
     * @return \Illuminate\Database\Eloquent\Builder<RailgunWallet>
     */
    public function scopeForNetwork($query, string $network)
    {
        return $query->where('network', $network);
    }

    /**
     * @return array<string, mixed>
     */
    public function toApiResponse(): array
    {
        return [
            'id'              => $this->id,
            'railgun_address' => $this->railgun_address,
            'network'         => $this->network,
            'last_scan_block' => $this->last_scan_block,
            'status'          => $this->status,
            'created_at'      => $this->created_at->toIso8601String(),
        ];
    }
}
