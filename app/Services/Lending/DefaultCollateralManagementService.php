<?php

namespace App\Services\Lending;

use App\Domain\Lending\DataObjects\Collateral;
use App\Domain\Lending\Enums\CollateralStatus;
use App\Domain\Lending\Enums\CollateralType;
use App\Domain\Lending\Services\CollateralManagementService;
use App\Models\LoanCollateral;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DefaultCollateralManagementService implements CollateralManagementService
{
    public function registerCollateral(array $collateralData): Collateral
    {
        $collateral = Collateral::fromArray([
            'collateral_id' => Str::uuid()->toString(),
            'loan_id' => $collateralData['loan_id'],
            'type' => $collateralData['type'],
            'description' => $collateralData['description'],
            'estimated_value' => $collateralData['estimated_value'],
            'currency' => $collateralData['currency'] ?? 'USD',
            'status' => CollateralStatus::PENDING_VERIFICATION->value,
            'verification_document_id' => $collateralData['document_id'] ?? null,
            'metadata' => $collateralData['metadata'] ?? []
        ]);

        // Store in database
        LoanCollateral::create([
            'id' => $collateral->collateralId,
            'loan_id' => $collateral->loanId,
            'type' => $collateral->type->value,
            'description' => $collateral->description,
            'estimated_value' => $collateral->estimatedValue,
            'currency' => $collateral->currency,
            'status' => $collateral->status->value,
            'verification_document_id' => $collateral->verificationDocumentId,
            'metadata' => $collateral->metadata,
            'last_valuation_date' => now()
        ]);

        Log::info('Collateral registered', [
            'collateral_id' => $collateral->collateralId,
            'loan_id' => $collateral->loanId,
            'type' => $collateral->type->value
        ]);

        return $collateral;
    }

    public function verifyCollateral(string $collateralId, string $verifiedBy): bool
    {
        $model = LoanCollateral::find($collateralId);
        
        if (!$model) {
            return false;
        }

        // In production, this would involve document verification,
        // property title checks, vehicle registration, etc.
        $verificationPassed = $this->performVerification($model);

        if ($verificationPassed) {
            $model->update([
                'status' => CollateralStatus::VERIFIED->value,
                'verified_at' => now(),
                'verified_by' => $verifiedBy
            ]);

            Log::info('Collateral verified', [
                'collateral_id' => $collateralId,
                'verified_by' => $verifiedBy
            ]);

            return true;
        }

        $model->update([
            'status' => CollateralStatus::REJECTED->value,
            'verified_at' => now(),
            'verified_by' => $verifiedBy
        ]);

        return false;
    }

    public function updateValuation(string $collateralId, string $newValue): Collateral
    {
        $model = LoanCollateral::findOrFail($collateralId);
        
        $oldValue = $model->estimated_value;
        
        $model->update([
            'estimated_value' => $newValue,
            'last_valuation_date' => now(),
            'valuation_history' => array_merge(
                $model->valuation_history ?? [],
                [[
                    'date' => now()->toIso8601String(),
                    'old_value' => $oldValue,
                    'new_value' => $newValue
                ]]
            )
        ]);

        Log::info('Collateral valuation updated', [
            'collateral_id' => $collateralId,
            'old_value' => $oldValue,
            'new_value' => $newValue
        ]);

        return $this->modelToDataObject($model);
    }

    public function releaseCollateral(string $collateralId): bool
    {
        $model = LoanCollateral::find($collateralId);
        
        if (!$model || !in_array($model->status, [CollateralStatus::VERIFIED->value])) {
            return false;
        }

        $model->update([
            'status' => CollateralStatus::RELEASED->value,
            'released_at' => now()
        ]);

        Log::info('Collateral released', ['collateral_id' => $collateralId]);

        return true;
    }

    public function liquidateCollateral(string $collateralId): array
    {
        $model = LoanCollateral::findOrFail($collateralId);
        
        // In production, this would initiate actual liquidation process
        // For now, we'll simulate it
        $liquidationValue = bcmul($model->estimated_value, '0.8', 2); // 80% of estimated value
        
        $model->update([
            'status' => CollateralStatus::LIQUIDATED->value,
            'liquidated_at' => now(),
            'liquidation_value' => $liquidationValue
        ]);

        Log::info('Collateral liquidated', [
            'collateral_id' => $collateralId,
            'estimated_value' => $model->estimated_value,
            'liquidation_value' => $liquidationValue
        ]);

        return [
            'collateral_id' => $collateralId,
            'liquidation_value' => $liquidationValue,
            'estimated_value' => $model->estimated_value,
            'recovery_rate' => 0.8
        ];
    }

    public function getCollateral(string $collateralId): ?Collateral
    {
        $model = LoanCollateral::find($collateralId);
        
        return $model ? $this->modelToDataObject($model) : null;
    }

    public function getLoanCollateral(string $loanId): Collection
    {
        return LoanCollateral::where('loan_id', $loanId)
            ->get()
            ->map(fn($model) => $this->modelToDataObject($model));
    }

    public function calculateTotalValue(string $loanId): string
    {
        $total = LoanCollateral::where('loan_id', $loanId)
            ->where('status', CollateralStatus::VERIFIED->value)
            ->sum('estimated_value');
            
        return number_format($total, 2, '.', '');
    }

    public function needsRevaluation(string $collateralId): bool
    {
        $model = LoanCollateral::find($collateralId);
        
        if (!$model || $model->status !== CollateralStatus::VERIFIED->value) {
            return false;
        }

        $type = CollateralType::from($model->type);
        $daysSinceValuation = $model->last_valuation_date->diffInDays(now());
        
        return $daysSinceValuation >= $type->getValuationFrequency();
    }

    private function performVerification(LoanCollateral $model): bool
    {
        // In production, implement actual verification logic
        // For now, simulate with random success (90% success rate)
        return rand(1, 100) <= 90;
    }

    private function modelToDataObject(LoanCollateral $model): Collateral
    {
        return new Collateral(
            collateralId: $model->id,
            loanId: $model->loan_id,
            type: CollateralType::from($model->type),
            description: $model->description,
            estimatedValue: $model->estimated_value,
            currency: $model->currency,
            status: CollateralStatus::from($model->status),
            verificationDocumentId: $model->verification_document_id,
            verifiedAt: $model->verified_at,
            verifiedBy: $model->verified_by,
            metadata: $model->metadata ?? []
        );
    }
}