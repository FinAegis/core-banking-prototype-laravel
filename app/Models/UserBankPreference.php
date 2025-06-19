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
            'deposit_insurance' => 100000, // EUR
            'swift_code' => 'EVPULT22XXX',
            'features' => ['instant_transfers', 'multi_currency', 'api_access'],
        ],
        'DEUTSCHE' => [
            'code' => 'DEUTSCHE',
            'name' => 'Deutsche Bank',
            'country' => 'DE',
            'type' => 'traditional',
            'default_allocation' => 30.0,
            'deposit_insurance' => 100000, // EUR
            'swift_code' => 'DEUTDEFF',
            'features' => ['corporate_banking', 'international_wire', 'premium_support'],
        ],
        'SANTANDER' => [
            'code' => 'SANTANDER',
            'name' => 'Santander',
            'country' => 'ES',
            'type' => 'traditional',
            'default_allocation' => 30.0,
            'deposit_insurance' => 100000, // EUR
            'swift_code' => 'BSCHESMMXXX',
            'features' => ['global_presence', 'trade_finance', 'multi_currency'],
        ],
        'REVOLUT' => [
            'code' => 'REVOLUT',
            'name' => 'Revolut',
            'country' => 'LT',
            'type' => 'emi',
            'default_allocation' => 0.0,
            'deposit_insurance' => 100000, // EUR
            'swift_code' => 'REVOGB21',
            'features' => ['instant_transfers', 'crypto_support', 'api_access'],
        ],
        'WISE' => [
            'code' => 'WISE',
            'name' => 'Wise',
            'country' => 'BE',
            'type' => 'emi',
            'default_allocation' => 0.0,
            'deposit_insurance' => 100000, // EUR
            'swift_code' => 'TRWIGB22',
            'features' => ['low_fees', 'multi_currency', 'borderless_account'],
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
                'metadata' => self::AVAILABLE_BANKS['PAYSERA'],
            ],
            [
                'bank_code' => 'DEUTSCHE',
                'bank_name' => 'Deutsche Bank',
                'allocation_percentage' => 30.0,
                'is_primary' => false,
                'status' => 'active',
                'metadata' => self::AVAILABLE_BANKS['DEUTSCHE'],
            ],
            [
                'bank_code' => 'SANTANDER',
                'bank_name' => 'Santander',
                'allocation_percentage' => 30.0,
                'is_primary' => false,
                'status' => 'active',
                'metadata' => self::AVAILABLE_BANKS['SANTANDER'],
            ],
        ];
    }

    /**
     * Calculate fund distribution across user's banks
     * 
     * @param string $userUuid
     * @param int $amountInCents
     * @return array Distribution of funds per bank
     */
    public static function calculateDistribution(string $userUuid, int $amountInCents): array
    {
        $preferences = self::where('user_uuid', $userUuid)
            ->active()
            ->orderBy('is_primary', 'desc')
            ->orderBy('allocation_percentage', 'desc')
            ->get();

        if ($preferences->isEmpty()) {
            throw new \Exception('No bank preferences found for user');
        }

        // Validate allocations sum to 100%
        if (!self::validateAllocations($userUuid)) {
            throw new \Exception('Bank allocations do not sum to 100%');
        }

        $distribution = [];
        $remainingAmount = $amountInCents;
        $totalAllocated = 0;

        foreach ($preferences as $index => $preference) {
            // Calculate amount for this bank
            if ($index === $preferences->count() - 1) {
                // Last bank gets remaining amount to handle rounding
                $bankAmount = $remainingAmount;
            } else {
                $bankAmount = (int) round($amountInCents * ($preference->allocation_percentage / 100));
                $remainingAmount -= $bankAmount;
            }

            $totalAllocated += $bankAmount;

            $distribution[] = [
                'bank_code' => $preference->bank_code,
                'bank_name' => $preference->bank_name,
                'amount' => $bankAmount,
                'percentage' => $preference->allocation_percentage,
                'is_primary' => $preference->is_primary,
                'metadata' => $preference->metadata,
            ];
        }

        // Ensure total allocated equals original amount
        if ($totalAllocated !== $amountInCents) {
            throw new \Exception('Distribution calculation error: amounts do not match');
        }

        return $distribution;
    }

    /**
     * Get total deposit insurance coverage for user
     */
    public static function getTotalInsuranceCoverage(string $userUuid): int
    {
        $preferences = self::where('user_uuid', $userUuid)
            ->active()
            ->get();

        $totalCoverage = 0;
        foreach ($preferences as $preference) {
            $bankInfo = self::AVAILABLE_BANKS[$preference->bank_code] ?? null;
            if ($bankInfo) {
                $totalCoverage += $bankInfo['deposit_insurance'] ?? 0;
            }
        }

        return $totalCoverage;
    }

    /**
     * Check if user has diversified bank allocation
     */
    public static function isDiversified(string $userUuid): bool
    {
        $preferences = self::where('user_uuid', $userUuid)
            ->active()
            ->get();

        // Consider diversified if using at least 2 banks
        // and no single bank has more than 60% allocation
        if ($preferences->count() < 2) {
            return false;
        }

        $maxAllocation = $preferences->max('allocation_percentage');
        return $maxAllocation <= 60.0;
    }
}