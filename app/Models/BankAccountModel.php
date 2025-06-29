<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccountModel extends Model
{
    protected $table = 'bank_accounts';
    
    protected $keyType = 'string';
    
    public $incrementing = false;
    
    protected $fillable = [
        'id',
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
    
    protected $casts = [
        'metadata' => 'array',
    ];
    
    /**
     * Get the user that owns the bank account
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }
    
    /**
     * Scope to get active accounts
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
    
    /**
     * Get masked account number for display
     */
    public function getMaskedAccountNumber(): string
    {
        $decrypted = decrypt($this->account_number);
        return '...' . substr($decrypted, -4);
    }
    
    /**
     * Get masked IBAN for display
     */
    public function getMaskedIBAN(): string
    {
        $decrypted = decrypt($this->iban);
        return substr($decrypted, 0, 4) . ' **** **** **** ' . substr($decrypted, -4);
    }
}