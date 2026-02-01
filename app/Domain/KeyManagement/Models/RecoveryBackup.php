<?php

declare(strict_types=1);

namespace App\Domain\KeyManagement\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property string $uuid
 * @property string $user_uuid
 * @property string $encrypted_backup
 * @property string $encryption_method
 * @property string $key_version
 * @property string $backup_hash
 * @property bool $is_verified
 * @property \Carbon\Carbon|null $verified_at
 * @property \Carbon\Carbon|null $last_used_at
 * @property int $usage_count
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder|static query()
 * @method static \Illuminate\Database\Eloquent\Builder|static forUser(string $userUuid)
 * @method static \Illuminate\Database\Eloquent\Builder|static verified()
 */
class RecoveryBackup extends Model
{
    use HasFactory;
    use HasUuids;
    use SoftDeletes;

    protected $table = 'recovery_backups';

    protected $primaryKey = 'uuid';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'user_uuid',
        'encrypted_backup',
        'encryption_method',
        'key_version',
        'backup_hash',
        'is_verified',
        'verified_at',
        'last_used_at',
        'usage_count',
    ];

    protected $casts = [
        'is_verified'  => 'boolean',
        'verified_at'  => 'datetime',
        'last_used_at' => 'datetime',
        'usage_count'  => 'integer',
    ];

    protected $hidden = [
        'encrypted_backup',
    ];

    /**
     * @return BelongsTo<User, RecoveryBackup>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<RecoveryBackup> $query
     * @return \Illuminate\Database\Eloquent\Builder<RecoveryBackup>
     */
    public function scopeForUser($query, string $userUuid)
    {
        return $query->where('user_uuid', $userUuid);
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder<RecoveryBackup> $query
     * @return \Illuminate\Database\Eloquent\Builder<RecoveryBackup>
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    public function markVerified(): void
    {
        $this->update([
            'is_verified' => true,
            'verified_at' => now(),
        ]);
    }

    public function recordUsage(): void
    {
        $this->increment('usage_count');
        $this->update(['last_used_at' => now()]);
    }

    public function verifyHash(string $decryptedBackup): bool
    {
        return hash('sha256', $decryptedBackup) === $this->backup_hash;
    }
}
