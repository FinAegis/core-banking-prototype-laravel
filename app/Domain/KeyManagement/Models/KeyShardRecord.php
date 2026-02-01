<?php

declare(strict_types=1);

namespace App\Domain\KeyManagement\Models;

use App\Domain\KeyManagement\Enums\ShardStatus;
use App\Domain\KeyManagement\Enums\ShardType;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $uuid
 * @property string $user_uuid
 * @property ShardType $shard_type
 * @property int $shard_index
 * @property string $encrypted_data
 * @property string $encrypted_for
 * @property string $key_version
 * @property ShardStatus $status
 * @property string|null $public_key_hash
 * @property \Carbon\Carbon|null $last_accessed_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|static query()
 * @method static \Illuminate\Database\Eloquent\Builder|static forUser(string $userUuid)
 * @method static \Illuminate\Database\Eloquent\Builder|static active()
 * @method static \Illuminate\Database\Eloquent\Builder|static ofType(ShardType $type)
 */
class KeyShardRecord extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'key_shards';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_uuid',
        'shard_type',
        'shard_index',
        'encrypted_data',
        'encrypted_for',
        'key_version',
        'status',
        'public_key_hash',
        'last_accessed_at',
    ];

    protected $casts = [
        'shard_type'       => ShardType::class,
        'status'           => ShardStatus::class,
        'shard_index'      => 'integer',
        'last_accessed_at' => 'datetime',
    ];

    protected $hidden = [
        'encrypted_data',
    ];

    /**
     * @return BelongsTo<User, KeyShardRecord>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<KeyShardRecord> $query
     * @return \Illuminate\Database\Eloquent\Builder<KeyShardRecord>
     */
    public function scopeForUser($query, string $userUuid)
    {
        return $query->where('user_uuid', $userUuid);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<KeyShardRecord> $query
     * @return \Illuminate\Database\Eloquent\Builder<KeyShardRecord>
     */
    public function scopeActive($query)
    {
        return $query->where('status', ShardStatus::ACTIVE);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<KeyShardRecord> $query
     * @return \Illuminate\Database\Eloquent\Builder<KeyShardRecord>
     */
    public function scopeOfType($query, ShardType $type)
    {
        return $query->where('shard_type', $type);
    }

    public function isActive(): bool
    {
        return $this->status === ShardStatus::ACTIVE;
    }

    public function isHsmStored(): bool
    {
        return $this->shard_type->isHsmStored();
    }

    public function markAccessed(): void
    {
        $this->update(['last_accessed_at' => now()]);
    }

    public function revoke(): void
    {
        $this->update(['status' => ShardStatus::REVOKED]);
    }
}
