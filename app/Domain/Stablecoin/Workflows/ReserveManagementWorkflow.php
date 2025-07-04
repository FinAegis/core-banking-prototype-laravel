<?php

namespace App\Domain\Stablecoin\Workflows;

use App\Domain\Stablecoin\Activities\ReserveManagementActivity;
use App\Domain\Stablecoin\Workflows\Data\ReserveDepositData;
use App\Domain\Stablecoin\Workflows\Data\ReserveWithdrawalData;
use App\Domain\Stablecoin\Workflows\Data\ReserveRebalanceData;
use Workflow\ActivityStub;
use Workflow\Workflow;

class ReserveManagementWorkflow extends Workflow
{
    private ActivityStub|ReserveManagementActivity $activity;
    
    public function __construct()
    {
        $this->activity = Workflow::newActivityStub(
            ReserveManagementActivity::class,
            ActivityStub::options()
                ->withStartToCloseTimeout(300)
                ->withRetryAttempts(3)
        );
    }
    
    public function depositReserve(ReserveDepositData $data): \Generator
    {
        try {
            // Verify deposit on blockchain
            yield $this->activity->verifyBlockchainDeposit(
                $data->transactionHash,
                $data->asset,
                $data->expectedAmount
            );
            
            // Lock funds in custodian
            yield $this->activity->lockFundsInCustodian(
                $data->custodianId,
                $data->asset,
                $data->amount,
                $data->transactionHash
            );
            
            // Update reserve pool
            yield $this->activity->updateReservePool(
                $data->poolId,
                $data->asset,
                $data->amount,
                'deposit'
            );
            
            // Emit success event
            yield $this->activity->emitReserveEvent(
                'deposit_completed',
                $data->toArray()
            );
            
            return [
                'success' => true,
                'pool_id' => $data->poolId,
                'asset' => $data->asset,
                'amount' => $data->amount,
                'timestamp' => now()->toIso8601String()
            ];
            
        } catch (\Throwable $e) {
            yield Workflow::asyncDetached(
                fn() => $this->compensateDeposit($data, $e->getMessage())
            );
            
            throw $e;
        }
    }
    
    public function withdrawReserve(ReserveWithdrawalData $data): \Generator
    {
        try {
            // Verify collateralization ratio
            yield $this->activity->verifyCollateralizationRatio(
                $data->poolId,
                $data->asset,
                $data->amount
            );
            
            // Create withdrawal request in custodian
            $withdrawalId = yield $this->activity->createWithdrawalRequest(
                $data->custodianId,
                $data->asset,
                $data->amount,
                $data->destinationAddress
            );
            
            // Update reserve pool
            yield $this->activity->updateReservePool(
                $data->poolId,
                $data->asset,
                $data->amount,
                'withdrawal'
            );
            
            // Execute withdrawal on blockchain
            $txHash = yield $this->activity->executeBlockchainWithdrawal(
                $withdrawalId,
                $data->custodianId,
                $data->asset,
                $data->amount,
                $data->destinationAddress
            );
            
            return [
                'success' => true,
                'withdrawal_id' => $withdrawalId,
                'transaction_hash' => $txHash,
                'timestamp' => now()->toIso8601String()
            ];
            
        } catch (\Throwable $e) {
            yield Workflow::asyncDetached(
                fn() => $this->compensateWithdrawal($data, $e->getMessage())
            );
            
            throw $e;
        }
    }
    
    public function rebalanceReserves(ReserveRebalanceData $data): \Generator
    {
        $executedSwaps = [];
        
        try {
            // Calculate required swaps
            $swaps = yield $this->activity->calculateRebalanceSwaps(
                $data->poolId,
                $data->targetAllocations,
                $data->maxSlippage
            );
            
            // Execute swaps
            foreach ($swaps as $swap) {
                $result = yield $this->activity->executeSwap(
                    $swap['from_asset'],
                    $swap['to_asset'],
                    $swap['amount'],
                    $swap['min_output']
                );
                
                $executedSwaps[] = array_merge($swap, [
                    'executed_output' => $result['output'],
                    'transaction_hash' => $result['tx_hash']
                ]);
            }
            
            // Update reserve allocations
            yield $this->activity->updateReserveAllocations(
                $data->poolId,
                $executedSwaps
            );
            
            return [
                'success' => true,
                'swaps' => $executedSwaps,
                'new_allocations' => yield $this->activity->getReserveAllocations($data->poolId),
                'timestamp' => now()->toIso8601String()
            ];
            
        } catch (\Throwable $e) {
            // Reverse executed swaps
            yield Workflow::asyncDetached(
                fn() => $this->compensateRebalance($executedSwaps, $e->getMessage())
            );
            
            throw $e;
        }
    }
    
    private function compensateDeposit(ReserveDepositData $data, string $error): \Generator
    {
        try {
            // Unlock funds if they were locked
            yield $this->activity->unlockFundsInCustodian(
                $data->custodianId,
                $data->asset,
                $data->amount
            );
            
            // Log compensation
            yield $this->activity->logCompensation(
                'deposit_failed',
                $data->toArray(),
                $error
            );
        } catch (\Throwable $e) {
            // Log critical compensation failure
            yield $this->activity->logCriticalError(
                'deposit_compensation_failed',
                ['original_error' => $error, 'compensation_error' => $e->getMessage()]
            );
        }
    }
    
    private function compensateWithdrawal(ReserveWithdrawalData $data, string $error): \Generator
    {
        try {
            // Restore reserve balance if it was updated
            yield $this->activity->updateReservePool(
                $data->poolId,
                $data->asset,
                $data->amount,
                'deposit' // Reverse the withdrawal
            );
            
            // Cancel withdrawal request
            yield $this->activity->cancelWithdrawalRequest(
                $data->custodianId,
                $data->asset,
                $data->amount
            );
            
            yield $this->activity->logCompensation(
                'withdrawal_failed',
                $data->toArray(),
                $error
            );
        } catch (\Throwable $e) {
            yield $this->activity->logCriticalError(
                'withdrawal_compensation_failed',
                ['original_error' => $error, 'compensation_error' => $e->getMessage()]
            );
        }
    }
    
    private function compensateRebalance(array $executedSwaps, string $error): \Generator
    {
        try {
            // Reverse swaps in opposite order
            foreach (array_reverse($executedSwaps) as $swap) {
                yield $this->activity->executeSwap(
                    $swap['to_asset'],
                    $swap['from_asset'],
                    $swap['executed_output'],
                    '0' // Accept any output for compensation
                );
            }
            
            yield $this->activity->logCompensation(
                'rebalance_failed',
                ['swaps' => $executedSwaps],
                $error
            );
        } catch (\Throwable $e) {
            yield $this->activity->logCriticalError(
                'rebalance_compensation_failed',
                ['original_error' => $error, 'compensation_error' => $e->getMessage()]
            );
        }
    }
}