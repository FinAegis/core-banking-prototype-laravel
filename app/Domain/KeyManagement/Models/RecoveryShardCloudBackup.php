<?php

declare(strict_types=1);

namespace App\Domain\KeyManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $uuid
 * @property int $user_id
 * @property string $device_id
 * @property string $backup_provider
 * @property string $encrypted_shard_hash
 * @property string $shard_version
 * @property string|null $encrypted_shard
 * @property array<string, mixed>|null $metadata
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<RecoveryShardCloudBackup>|static query()
 * @method static \Illuminate\Database\Eloquent\Builder<RecoveryShardCloudBackup>|static forUser(int $userId)
 */
class RecoveryShardCloudBackup extends Model
{
    use HasUuids;

    protected $table = 'recovery_shard_cloud_backups';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_id',
        'device_id',
        'backup_provider',
        'encrypted_shard_hash',
        'shard_version',
        'encrypted_shard',
        'metadata',
    ];

    protected $hidden = [
        'encrypted_shard',
    ];

    protected $casts = [
        'metadata'        => 'array',
        'encrypted_shard' => 'encrypted',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<RecoveryShardCloudBackup> $query
     * @return \Illuminate\Database\Eloquent\Builder<RecoveryShardCloudBackup>
     */
    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
