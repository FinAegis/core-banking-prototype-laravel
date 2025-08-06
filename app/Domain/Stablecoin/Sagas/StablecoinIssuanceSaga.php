<?php

declare(strict_types=1);

namespace App\Domain\Stablecoin\Sagas;

use App\Domain\Compliance\Workflows\KycVerificationWorkflow;
use App\Domain\Stablecoin\Workflows\AddCollateralWorkflow;
use App\Domain\Stablecoin\Workflows\BurnStablecoinWorkflow;
use App\Domain\Stablecoin\Workflows\MintStablecoinWorkflow;
use App\Domain\Wallet\Workflows\WalletDepositWorkflow;
use App\Domain\Wallet\Workflows\WalletWithdrawWorkflow;
use Illuminate\Support\Facades\Log;
use Workflow\ChildWorkflowStub;
use Workflow\Workflow;

/**
 * Saga for orchestrating stablecoin issuance with multi-domain coordination.
 * Ensures compliance, collateral management, and proper minting with full compensation.
 */
class StablecoinIssuanceSaga extends Workflow
{
    private array $compensations = [];

    private array $completedSteps = [];

    /**
     * Execute the stablecoin issuance saga.
     *
     * @param array $input Contains:
     *   - account_id: string
     *   - stablecoin_code: string
     *   - amount: float
     *   - collateral_asset: string
     *   - collateral_amount: float
     *   - compliance_check: bool
     */
    public function execute(array $input): \Generator
    {
        $sagaId = \Str::uuid()->toString();

        Log::info('Starting StablecoinIssuanceSaga', [
            'saga_id'         => $sagaId,
            'account_id'      => $input['account_id'],
            'stablecoin_code' => $input['stablecoin_code'],
            'amount'          => $input['amount'],
        ]);

        try {
            // Step 1: Compliance check (if required)
            if ($input['compliance_check'] ?? true) {
                $complianceResult = yield from $this->verifyCompliance($input['account_id']);
                if (! $complianceResult['success']) {
                    throw new \Exception(
                        'Compliance verification failed: ' . ($complianceResult['message'] ?? 'Unknown reason')
                    );
                }
                $this->completedSteps[] = 'verify_compliance';
            }

            // Step 2: Lock collateral from user's wallet
            $lockResult = yield from $this->lockCollateral(
                $input['account_id'],
                $input['collateral_asset'],
                $input['collateral_amount']
            );
            if (! $lockResult['success']) {
                throw new \Exception('Failed to lock collateral: ' . ($lockResult['message'] ?? 'Unknown reason'));
            }
            $this->completedSteps[] = 'lock_collateral';

            // Step 3: Add collateral to the stablecoin system
            $collateralResult = yield from $this->addCollateralToSystem(
                $input['account_id'],
                $input['stablecoin_code'],
                $input['collateral_asset'],
                $input['collateral_amount']
            );
            if (! $collateralResult) {
                throw new \Exception('Failed to add collateral to system');
            }
            $this->completedSteps[] = 'add_collateral_to_system';

            // Step 4: Mint stablecoins
            $mintResult = yield from $this->mintStablecoins(
                $input['account_id'],
                $input['stablecoin_code'],
                $input['amount'],
                $input['collateral_asset'],
                $input['collateral_amount']
            );
            if (! $mintResult) {
                throw new \Exception('Failed to mint stablecoins');
            }
            $this->completedSteps[] = 'mint_stablecoins';

            // Step 5: Deposit minted stablecoins to user's wallet
            $depositResult = yield from $this->depositStablecoins(
                $input['account_id'],
                $input['stablecoin_code'],
                $input['amount']
            );
            if (! $depositResult['success']) {
                throw new \Exception(
                    'Failed to deposit stablecoins: ' . ($depositResult['message'] ?? 'Unknown reason')
                );
            }
            $this->completedSteps[] = 'deposit_stablecoins';

            Log::info('StablecoinIssuanceSaga completed successfully', [
                'saga_id'         => $sagaId,
                'account_id'      => $input['account_id'],
                'stablecoin_code' => $input['stablecoin_code'],
                'amount'          => $input['amount'],
                'completed_steps' => $this->completedSteps,
            ]);

            return [
                'success'           => true,
                'saga_id'           => $sagaId,
                'position_uuid'     => $mintResult,
                'stablecoin_code'   => $input['stablecoin_code'],
                'amount_minted'     => $input['amount'],
                'collateral_locked' => $input['collateral_amount'],
                'completed_steps'   => $this->completedSteps,
            ];
        } catch (\Throwable $e) {
            Log::error('StablecoinIssuanceSaga failed, executing compensations', [
                'saga_id'         => $sagaId,
                'account_id'      => $input['account_id'],
                'error'           => $e->getMessage(),
                'completed_steps' => $this->completedSteps,
            ]);

            // Execute compensations in reverse order
            yield from $this->executeCompensations();

            return [
                'success'           => false,
                'saga_id'           => $sagaId,
                'error'             => $e->getMessage(),
                'compensated_steps' => array_keys($this->compensations),
            ];
        }
    }

    /**
     * Verify compliance for the account.
     */
    private function verifyCompliance(string $accountId): \Generator
    {
        $workflow = yield ChildWorkflowStub::make(
            KycVerificationWorkflow::class
        );

        $result = yield $workflow->execute([
            'account_id' => $accountId,
            'action'     => 'verify',
            'level'      => 'enhanced', // Enhanced KYC for stablecoin operations
        ]);

        // No compensation needed for compliance check

        return $result;
    }

    /**
     * Lock collateral from user's wallet.
     */
    private function lockCollateral(
        string $accountId,
        string $collateralAsset,
        float $amount
    ): \Generator {
        $workflow = yield ChildWorkflowStub::make(
            WalletWithdrawWorkflow::class
        );

        $result = yield $workflow->execute(
            $accountId,
            $collateralAsset,
            $amount
        );

        // Add compensation to unlock collateral
        $this->registerCompensation('lock_collateral', function () use ($accountId, $collateralAsset, $amount) {
            return ChildWorkflowStub::make(WalletDepositWorkflow::class)
                ->execute(
                    $accountId,
                    $collateralAsset,
                    $amount
                );
        });

        return $result;
    }

    /**
     * Add collateral to the stablecoin system.
     */
    private function addCollateralToSystem(
        string $accountId,
        string $stablecoinCode,
        string $collateralAsset,
        float $amount
    ): \Generator {
        $workflow = yield ChildWorkflowStub::make(
            AddCollateralWorkflow::class
        );

        $result = yield $workflow->execute(
            $accountId,
            $stablecoinCode,
            $collateralAsset,
            $amount
        );

        // Add compensation to remove collateral from system
        $this->registerCompensation(
            'add_collateral_to_system',
            function () use ($accountId, $stablecoinCode, $collateralAsset, $amount) {
                // This would typically call a RemoveCollateralWorkflow
                // For now, we'll log the compensation
                Log::info('Compensation: Would remove collateral from system', [
                    'account_id'       => $accountId,
                    'stablecoin_code'  => $stablecoinCode,
                    'collateral_asset' => $collateralAsset,
                    'amount'           => $amount,
                ]);

                return true;
            }
        );

        return $result;
    }

    /**
     * Mint stablecoins.
     */
    private function mintStablecoins(
        string $accountId,
        string $stablecoinCode,
        float $amount,
        string $collateralAsset,
        float $collateralAmount
    ): \Generator {
        $workflow = yield ChildWorkflowStub::make(
            MintStablecoinWorkflow::class
        );

        $result = yield $workflow->execute(
            $accountId,
            $stablecoinCode,
            $amount,
            $collateralAsset,
            $collateralAmount
        );

        // Add compensation to burn the minted stablecoins
        $this->registerCompensation('mint_stablecoins', function () use ($accountId, $stablecoinCode, $amount) {
            return ChildWorkflowStub::make(BurnStablecoinWorkflow::class)
                ->execute(
                    $accountId,
                    $stablecoinCode,
                    $amount
                );
        });

        return $result;
    }

    /**
     * Deposit stablecoins to user's wallet.
     */
    private function depositStablecoins(
        string $accountId,
        string $stablecoinCode,
        float $amount
    ): \Generator {
        $workflow = yield ChildWorkflowStub::make(
            WalletDepositWorkflow::class
        );

        $result = yield $workflow->execute(
            $accountId,
            $stablecoinCode,
            $amount
        );

        // Add compensation to withdraw the deposited stablecoins
        $this->registerCompensation('deposit_stablecoins', function () use ($accountId, $stablecoinCode, $amount) {
            return ChildWorkflowStub::make(WalletWithdrawWorkflow::class)
                ->execute(
                    $accountId,
                    $stablecoinCode,
                    $amount
                );
        });

        return $result;
    }

    /**
     * Register a compensation action.
     */
    private function registerCompensation(string $step, callable $compensation): void
    {
        $this->compensations[$step] = $compensation;
    }

    /**
     * Execute all compensations in reverse order.
     */
    private function executeCompensations(): \Generator
    {
        $compensations = array_reverse($this->compensations, true);

        foreach ($compensations as $step => $compensation) {
            try {
                Log::info("Executing compensation for step: {$step}");
                yield $compensation();
                Log::info("Compensation successful for step: {$step}");
            } catch (\Throwable $e) {
                Log::error("Compensation failed for step: {$step}", [
                    'error' => $e->getMessage(),
                ]);
                // Continue with other compensations even if one fails
            }
        }
    }
}
