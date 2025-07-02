<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankConnectionModel extends Model
{
    use HasFactory;
    
    protected $table = 'bank_connections';
    
    protected $keyType = 'string';
    
    public $incrementing = false;
    
    protected $fillable = [
        'id',
        'user_uuid',
        'bank_code',
        'status',
        'credentials',
        'permissions',
        'last_sync_at',
        'expires_at',
        'metadata',
    ];
    
    protected $casts = [
        'permissions' => 'array',
        'metadata' => 'array',
        'last_sync_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
    
    /**
     * Get the user that owns the bank connection
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }
    
    /**
     * Scope to get active connections
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
    
    /**
     * Check if connection is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
    
    /**
     * Check if connection needs renewal
     */
    public function needsRenewal(): bool
    {
        if (!$this->expires_at) {
            return false;
        }
        
        return $this->expires_at->diffInDays(now()) <= 7;
    }
}