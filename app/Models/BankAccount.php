<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccount extends Model
{
    use HasFactory, HasUuids;

    /**
     * Get the columns that should receive a unique identifier.
     */
    public function uniqueIds(): array
    {
        return ['id'];
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_uuid',
        'bank_code',
        'external_id',
        'account_number',
        'iban',
        'swift',
        'currency',
        'account_type',
        'status',
        'metadata',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'account_number',
        'iban',
    ];

    /**
     * Get the user that owns the bank account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * Get the decrypted account number.
     */
    public function getFullAccountNumberAttribute(): string
    {
        return decrypt($this->account_number_encrypted);
    }

    /**
     * Get display-friendly account info.
     */
    public function getDisplayNameAttribute(): string
    {
        $lastFour = substr($this->account_number, -4);
        return "{$this->bank_code} - ****{$lastFour}";
    }
    
    /**
     * Scope a query to only include verified bank accounts.
     */
    public function scopeVerified($query)
    {
        return $query->where('status', 'verified');
    }
}