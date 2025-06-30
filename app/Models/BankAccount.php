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
        return ['uuid'];
    }

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'user_id',
        'bank_name',
        'account_number',
        'account_number_encrypted',
        'account_holder_name',
        'routing_number',
        'iban',
        'swift',
        'verified',
        'verified_at',
        'is_default',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'verified' => 'boolean',
        'is_default' => 'boolean',
        'verified_at' => 'datetime',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'account_number_encrypted',
    ];

    /**
     * Get the user that owns the bank account.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
        return "{$this->bank_name} - ****{$this->account_number}";
    }
}