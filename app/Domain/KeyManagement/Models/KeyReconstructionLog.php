<?php

declare(strict_types=1);

namespace App\Domain\KeyManagement\Models;

use App\Domain\KeyManagement\Enums\ShardType;
use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $uuid
 * @property string $user_uuid
 * @property string $key_version
 * @property array<string> $shards_used
 * @property string $purpose
 * @property string $ip_address
 * @property string|null $user_agent
 * @property string|null $device_id
 * @property bool $success
 * @property string|null $failure_reason
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|static query()
 * @method static \Illuminate\Database\Eloquent\Builder|static forUser(string $userUuid)
 * @method static \Illuminate\Database\Eloquent\Builder|static successful()
 * @method static \Illuminate\Database\Eloquent\Builder|static failed()
 */
class KeyReconstructionLog extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'key_reconstruction_logs';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_uuid',
        'key_version',
        'shards_used',
        'purpose',
        'ip_address',
        'user_agent',
        'device_id',
        'success',
        'failure_reason',
    ];

    protected $casts = [
        'shards_used' => 'array',
        'success'     => 'boolean',
    ];

    /**
     * @return BelongsTo<User, KeyReconstructionLog>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<KeyReconstructionLog> $query
     * @return \Illuminate\Database\Eloquent\Builder<KeyReconstructionLog>
     */
    public function scopeForUser($query, string $userUuid)
    {
        return $query->where('user_uuid', $userUuid);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<KeyReconstructionLog> $query
     * @return \Illuminate\Database\Eloquent\Builder<KeyReconstructionLog>
     */
    public function scopeSuccessful($query)
    {
        return $query->where('success', true);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<KeyReconstructionLog> $query
     * @return \Illuminate\Database\Eloquent\Builder<KeyReconstructionLog>
     */
    public function scopeFailed($query)
    {
        return $query->where('success', false);
    }

    /**
     * Get the shard types that were used in this reconstruction.
     *
     * @return array<ShardType>
     */
    public function getShardTypes(): array
    {
        return array_map(
            fn (string $type) => ShardType::from($type),
            $this->shards_used
        );
    }

    /**
     * Check if reconstruction was recent (within the given minutes).
     */
    public function isRecent(int $minutes = 5): bool
    {
        return $this->created_at->diffInMinutes(now()) <= $minutes;
    }
}
