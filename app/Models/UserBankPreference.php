<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserBankPreference extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_uuid',
        'bank_code',
        'bank_name',
        'allocation_percentage',
        'is_primary',
        'status',
        'metadata',
    ];

    protected $casts = [
        'allocation_percentage' => 'decimal:2',
        'is_primary' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Available banks for the platform
     */
    const AVAILABLE_BANKS = [
        'PAYSERA' => [
            'code' => 'PAYSERA',
            'name' => 'Paysera',
            'country' => 'LT',
            'type' => 'emi',
            'default_allocation' => 40.0,
        ],
        'DEUTSCHE' => [
            'code' => 'DEUTSCHE',
            'name' => 'Deutsche Bank',
            'country' => 'DE',
            'type' => 'traditional',
            'default_allocation' => 30.0,
        ],
        'SANTANDER' => [
            'code' => 'SANTANDER',
            'name' => 'Santander',
            'country' => 'ES',
            'type' => 'traditional',
            'default_allocation' => 30.0,
        ],
    ];

    /**
     * Get the user that owns the bank preference
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_uuid', 'uuid');
    }

    /**
     * Scope to get active preferences
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Validate that user's allocations sum to 100%
     */
    public static function validateAllocations(string $userUuid): bool
    {
        $total = self::where('user_uuid', $userUuid)
            ->active()
            ->sum('allocation_percentage');
            
        return abs($total - 100) < 0.01; // Allow for small floating point differences
    }

    /**
     * Get default bank allocations for new users
     */
    public static function getDefaultAllocations(): array
    {
        return [
            [
                'bank_code' => 'PAYSERA',
                'bank_name' => 'Paysera',
                'allocation_percentage' => 40.0,
                'is_primary' => true,
                'status' => 'active',
            ],
            [
                'bank_code' => 'DEUTSCHE',
                'bank_name' => 'Deutsche Bank',
                'allocation_percentage' => 30.0,
                'is_primary' => false,
                'status' => 'active',
            ],
            [
                'bank_code' => 'SANTANDER',
                'bank_name' => 'Santander',
                'allocation_percentage' => 30.0,
                'is_primary' => false,
                'status' => 'active',
            ],
        ];
    }
}