<?php

namespace App\Domain\Exchange\Workflows;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Exchange\Activities\ValidateLiquidityActivity;
use App\Domain\Exchange\Activities\LockLiquidityActivity;
use App\Domain\Exchange\Activities\TransferLiquidityActivity;
use App\Domain\Exchange\Activities\ReleaseLiquidityActivity;
use App\Domain\Exchange\Activities\CalculatePoolSharesActivity;
use App\Domain\Exchange\Aggregates\LiquidityPool;
use App\Domain\Exchange\ValueObjects\LiquidityAdditionInput;
use App\Domain\Exchange\ValueObjects\LiquidityRemovalInput;
use Brick\Math\BigDecimal;
use Illuminate\Support\Str;
use Workflow\Activity;
use Workflow\Workflow;

class LiquidityManagementWorkflow extends Workflow
{
    private array $lockedBalances = [];
    private bool $liquidityTransferred = false;

    public function addLiquidity(LiquidityAdditionInput $input): \Generator
    {
        try {
            // Step 1: Validate liquidity parameters
            yield Activity::make(ValidateLiquidityActivity::class, $input);

            // Step 2: Lock provider's funds
            $lockBase = yield Activity::make(LockLiquidityActivity::class, [
                'account_id' => $input->providerId,
                'currency' => $input->baseCurrency,
                'amount' => $input->baseAmount,
                'pool_id' => $input->poolId,
            ]);
            $this->lockedBalances[] = $lockBase;

            $lockQuote = yield Activity::make(LockLiquidityActivity::class, [
                'account_id' => $input->providerId,
                'currency' => $input->quoteCurrency,
                'amount' => $input->quoteAmount,
                'pool_id' => $input->poolId,
            ]);
            $this->lockedBalances[] = $lockQuote;

            // Step 3: Calculate shares to mint
            $shares = yield Activity::make(CalculatePoolSharesActivity::class, $input);

            // Step 4: Transfer funds to pool
            yield Activity::make(TransferLiquidityActivity::class, [
                'from_account_id' => $input->providerId,
                'to_pool_id' => $input->poolId,
                'base_currency' => $input->baseCurrency,
                'base_amount' => $input->baseAmount,
                'quote_currency' => $input->quoteCurrency,
                'quote_amount' => $input->quoteAmount,
            ]);
            $this->liquidityTransferred = true;

            // Step 5: Update pool state
            LiquidityPool::retrieve($input->poolId)
                ->addLiquidity(
                    providerId: $input->providerId,
                    baseAmount: $input->baseAmount,
                    quoteAmount: $input->quoteAmount,
                    minShares: $input->minShares,
                    metadata: [
                        'workflow_id' => $this->workflowId(),
                        'timestamp' => now()->toIso8601String(),
                    ]
                )
                ->persist();

            return [
                'success' => true,
                'shares_minted' => $shares['shares'],
                'pool_id' => $input->poolId,
                'provider_id' => $input->providerId,
            ];

        } catch (\Exception $e) {
            // Compensate on failure
            yield from $this->compensateAddLiquidity($e->getMessage());
            
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function removeLiquidity(LiquidityRemovalInput $input): \Generator
    {
        try {
            // Step 1: Validate removal parameters
            yield Activity::make(ValidateLiquidityActivity::class, $input);

            // Step 2: Calculate amounts to return
            $amounts = yield Activity::make(CalculatePoolSharesActivity::class, [
                'pool_id' => $input->poolId,
                'shares' => $input->shares,
                'operation' => 'removal',
            ]);

            // Step 3: Update pool state first
            LiquidityPool::retrieve($input->poolId)
                ->removeLiquidity(
                    providerId: $input->providerId,
                    shares: $input->shares,
                    minBaseAmount: $input->minBaseAmount,
                    minQuoteAmount: $input->minQuoteAmount,
                    metadata: [
                        'workflow_id' => $this->workflowId(),
                        'timestamp' => now()->toIso8601String(),
                    ]
                )
                ->persist();

            // Step 4: Transfer funds back to provider
            yield Activity::make(TransferLiquidityActivity::class, [
                'from_pool_id' => $input->poolId,
                'to_account_id' => $input->providerId,
                'base_currency' => $amounts['base_currency'],
                'base_amount' => $amounts['base_amount'],
                'quote_currency' => $amounts['quote_currency'],
                'quote_amount' => $amounts['quote_amount'],
            ]);

            return [
                'success' => true,
                'base_amount' => $amounts['base_amount'],
                'quote_amount' => $amounts['quote_amount'],
                'shares_burned' => $input->shares,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    public function rebalancePool(string $poolId, string $targetRatio): \Generator
    {
        try {
            // Step 1: Get current pool state
            $poolState = yield Activity::make(CalculatePoolSharesActivity::class, [
                'pool_id' => $poolId,
                'operation' => 'state',
            ]);

            $currentRatio = BigDecimal::of($poolState['base_reserve'])
                ->dividedBy($poolState['quote_reserve'], 18);

            $targetRatioDecimal = BigDecimal::of($targetRatio);
            
            // Step 2: Calculate rebalancing requirements
            if ($currentRatio->isGreaterThan($targetRatioDecimal)) {
                // Need to add more quote currency
                $quoteNeeded = BigDecimal::of($poolState['base_reserve'])
                    ->dividedBy($targetRatioDecimal, 18)
                    ->minus($poolState['quote_reserve']);
                
                $rebalanceAmount = $quoteNeeded->__toString();
                $rebalanceCurrency = $poolState['quote_currency'];
            } else {
                // Need to add more base currency
                $baseNeeded = BigDecimal::of($poolState['quote_reserve'])
                    ->multipliedBy($targetRatioDecimal)
                    ->minus($poolState['base_reserve']);
                
                $rebalanceAmount = $baseNeeded->__toString();
                $rebalanceCurrency = $poolState['base_currency'];
            }

            // Step 3: Execute rebalancing via external exchanges if needed
            if (BigDecimal::of($rebalanceAmount)->isGreaterThan(0)) {
                // This would integrate with external exchange connectors
                // to source the needed liquidity
            }

            // Step 4: Update pool state
            LiquidityPool::retrieve($poolId)
                ->rebalancePool(
                    targetRatio: $targetRatio,
                    metadata: [
                        'workflow_id' => $this->workflowId(),
                        'rebalance_amount' => $rebalanceAmount,
                        'rebalance_currency' => $rebalanceCurrency,
                    ]
                )
                ->persist();

            return [
                'success' => true,
                'old_ratio' => $currentRatio->__toString(),
                'new_ratio' => $targetRatio,
                'rebalance_amount' => $rebalanceAmount,
                'rebalance_currency' => $rebalanceCurrency,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function compensateAddLiquidity(string $reason): \Generator
    {
        // Release any locked balances
        foreach ($this->lockedBalances as $lock) {
            try {
                yield Activity::make(ReleaseLiquidityActivity::class, $lock);
            } catch (\Exception $e) {
                // Log compensation failure
                \Log::error('Failed to release locked balance during compensation', [
                    'lock' => $lock,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // If liquidity was transferred, reverse it
        if ($this->liquidityTransferred) {
            // This would require a reversal activity
            \Log::warning('Liquidity transfer reversal needed', [
                'workflow_id' => $this->workflowId(),
                'reason' => $reason,
            ]);
        }
    }
}