<?php

namespace App\Domain\Wallet\Models;

use App\Domain\Shared\Traits\UsesTenantConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SecureKeyStorage extends Model
{
    use UsesTenantConnection;

    protected $table = 'secure_key_storage';

    protected $fillable = [
        'wallet_id',
        'encrypted_data',
        'auth_tag',
        'iv',
        'salt',
        'key_version',
        'storage_type',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'metadata'    => 'array',
        'is_active'   => 'boolean',
        'key_version' => 'integer',
    ];

    /**
     * Get the access logs for this key storage.
     */
    public function accessLogs(): HasMany
    {
        return $this->hasMany(KeyAccessLog::class, 'wallet_id', 'wallet_id');
    }

    /**
     * Scope to active keys only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope to specific storage type.
     */
    public function scopeStorageType($query, string $type)
    {
        return $query->where('storage_type', $type);
    }
}
