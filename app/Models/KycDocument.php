<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KycDocument extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_uuid',
        'document_type',
        'status',
        'file_path',
        'file_hash',
        'metadata',
        'rejection_reason',
        'uploaded_at',
        'verified_at',
        'expires_at',
        'verified_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'uploaded_at' => 'datetime',
        'verified_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    /**
     * Get the user that owns the document.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * Scope for pending documents
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for verified documents
     */
    public function scopeVerified($query)
    {
        return $query->where('status', 'verified');
    }

    /**
     * Check if document is expired
     */
    public function isExpired(): bool
    {
        return $this->status === 'expired' || 
               ($this->expires_at && $this->expires_at->isPast());
    }

    /**
     * Mark document as verified
     */
    public function markAsVerified(string $verifiedBy, ?string $expiresAt = null): void
    {
        $this->update([
            'status' => 'verified',
            'verified_at' => now(),
            'verified_by' => $verifiedBy,
            'expires_at' => $expiresAt,
            'rejection_reason' => null,
        ]);
    }

    /**
     * Mark document as rejected
     */
    public function markAsRejected(string $reason, string $rejectedBy): void
    {
        $this->update([
            'status' => 'rejected',
            'rejection_reason' => $reason,
            'verified_by' => $rejectedBy,
            'verified_at' => now(),
        ]);
    }
}