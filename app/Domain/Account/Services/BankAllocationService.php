<?php

namespace App\Domain\Account\Services;

use App\Models\User;
use App\Models\UserBankPreference;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BankAllocationService
{
    /**
     * Set up default bank allocations for a new user
     */
    public function setupDefaultAllocations(User $user): Collection
    {
        $defaultAllocations = UserBankPreference::getDefaultAllocations();
        $preferences = collect();

        DB::transaction(function () use ($user, $defaultAllocations, &$preferences) {
            foreach ($defaultAllocations as $allocation) {
                $preference = $user->bankPreferences()->create([
                    'bank_code' => $allocation['bank_code'],
                    'bank_name' => $allocation['bank_name'],
                    'allocation_percentage' => $allocation['allocation_percentage'],
                    'is_primary' => $allocation['is_primary'],
                    'status' => $allocation['status'],
                    'metadata' => $allocation['metadata'] ?? [],
                ]);
                $preferences->push($preference);
            }
        });

        return $preferences;
    }

    /**
     * Update user's bank allocations
     * 
     * @param User $user
     * @param array $allocations Array of ['bank_code' => percentage]
     * @return Collection
     * @throws \Exception
     */
    public function updateAllocations(User $user, array $allocations): Collection
    {
        // Validate total equals 100%
        $total = array_sum($allocations);
        if (abs($total - 100) > 0.01) {
            throw new \Exception("Allocations must sum to 100%, got {$total}%");
        }

        // Validate all banks are available
        foreach ($allocations as $bankCode => $percentage) {
            if (!isset(UserBankPreference::AVAILABLE_BANKS[$bankCode])) {
                throw new \Exception("Invalid bank code: {$bankCode}");
            }
            if ($percentage < 0 || $percentage > 100) {
                throw new \Exception("Invalid allocation percentage: {$percentage}%");
            }
        }

        $preferences = collect();

        DB::transaction(function () use ($user, $allocations, &$preferences) {
            // Delete existing preferences (to avoid unique constraint violations)
            $user->bankPreferences()->delete();

            // Create new preferences
            $isFirst = true;
            foreach ($allocations as $bankCode => $percentage) {
                if ($percentage == 0) {
                    continue; // Skip banks with 0% allocation
                }

                $bankInfo = UserBankPreference::AVAILABLE_BANKS[$bankCode];
                $preference = $user->bankPreferences()->create([
                    'bank_code' => $bankCode,
                    'bank_name' => $bankInfo['name'],
                    'allocation_percentage' => $percentage,
                    'is_primary' => $isFirst,
                    'status' => 'active',
                    'metadata' => $bankInfo,
                ]);
                
                $preferences->push($preference);
                $isFirst = false;
            }
        });

        return $preferences;
    }

    /**
     * Add a new bank to user's allocation
     */
    public function addBank(User $user, string $bankCode, float $percentage): UserBankPreference
    {
        if (!isset(UserBankPreference::AVAILABLE_BANKS[$bankCode])) {
            throw new \Exception("Invalid bank code: {$bankCode}");
        }

        // Check if bank already exists
        $existing = $user->bankPreferences()
            ->where('bank_code', $bankCode)
            ->where('status', 'active')
            ->first();

        if ($existing) {
            throw new \Exception("Bank {$bankCode} is already in user's allocation");
        }

        // Validate new total
        $currentTotal = $user->bankPreferences()
            ->active()
            ->sum('allocation_percentage');

        if (($currentTotal + $percentage) > 100.01) {
            throw new \Exception("Adding {$percentage}% would exceed 100% total allocation");
        }

        $bankInfo = UserBankPreference::AVAILABLE_BANKS[$bankCode];
        
        return $user->bankPreferences()->create([
            'bank_code' => $bankCode,
            'bank_name' => $bankInfo['name'],
            'allocation_percentage' => $percentage,
            'is_primary' => false,
            'status' => 'active',
            'metadata' => $bankInfo,
        ]);
    }

    /**
     * Remove a bank from user's allocation
     */
    public function removeBank(User $user, string $bankCode): bool
    {
        $preference = $user->bankPreferences()
            ->where('bank_code', $bankCode)
            ->where('status', 'active')
            ->first();

        if (!$preference) {
            throw new \Exception("Bank {$bankCode} not found in user's allocation");
        }

        if ($preference->is_primary) {
            throw new \Exception("Cannot remove primary bank");
        }

        // Deactivate the bank preference
        $preference->update(['status' => 'suspended']);

        // Check if allocations still sum to 100%
        if (!UserBankPreference::validateAllocations($user->uuid)) {
            // Reactivate if removal breaks allocation
            $preference->update(['status' => 'active']);
            throw new \Exception("Removing bank would break 100% allocation requirement");
        }

        return true;
    }

    /**
     * Set a bank as primary
     */
    public function setPrimaryBank(User $user, string $bankCode): UserBankPreference
    {
        $preference = $user->bankPreferences()
            ->where('bank_code', $bankCode)
            ->where('status', 'active')
            ->first();

        if (!$preference) {
            throw new \Exception("Bank {$bankCode} not found in user's active allocation");
        }

        DB::transaction(function () use ($user, $preference) {
            // Remove primary flag from all banks
            $user->bankPreferences()->update(['is_primary' => false]);
            
            // Set new primary
            $preference->update(['is_primary' => true]);
        });

        return $preference->fresh();
    }

    /**
     * Get distribution summary for display
     */
    public function getDistributionSummary(User $user, int $amountInCents): array
    {
        try {
            $distribution = UserBankPreference::calculateDistribution($user->uuid, $amountInCents);
            $totalInsurance = UserBankPreference::getTotalInsuranceCoverage($user->uuid);
            $isDiversified = UserBankPreference::isDiversified($user->uuid);

            return [
                'distribution' => $distribution,
                'total_amount' => $amountInCents,
                'total_insurance_coverage' => $totalInsurance,
                'is_diversified' => $isDiversified,
                'bank_count' => count($distribution),
            ];
        } catch (\Exception $e) {
            return [
                'error' => $e->getMessage(),
                'distribution' => [],
                'total_amount' => $amountInCents,
                'total_insurance_coverage' => 0,
                'is_diversified' => false,
                'bank_count' => 0,
            ];
        }
    }
}