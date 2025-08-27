<?php

namespace App\Domain\Exchange\Workflows;

use App\Domain\Exchange\Activities\CalculatePoolSharesActivity;
use App\Domain\Exchange\Activities\LockLiquidityActivity;
use App\Domain\Exchange\Activities\ReleaseLiquidityActivity;
use App\Domain\Exchange\Activities\TransferLiquidityActivity;
use App\Domain\Exchange\Activities\ValidateLiquidityActivity;
use App\Domain\Exchange\Aggregates\LiquidityPool;
use App\Domain\Exchange\Events\EmergencyPoolPaused;
use App\Domain\Exchange\Events\PoolSlippageExceeded;
use App\Domain\Exchange\Services\CircuitBreakerService;
use App\Domain\Exchange\ValueObjects\LiquidityAdditionInput;
use App\Domain\Exchange\Workflows\Policies\LiquidityRetryPolicy;
use Brick\Math\BigDecimal;
use DomainException;
use Exception;
use Generator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Workflow\Activity;
use Workflow\ActivityStub;
use Workflow\Workflow;

class EnhancedLiquidityManagementWorkflow extends Workflow
{
    private array $lockedBalances = [];

    private bool $liquidityTransferred = false;

    private array $compensationLog = [];

    private CircuitBreakerService $circuitBreaker;

    public function __construct()
    {
        $this->circuitBreaker = new CircuitBreakerService();
    }

    public function addLiquidity(LiquidityAdditionInput $input): Generator
    {
        $startTime = microtime(true);
        $context = [
            'workflow_id' => $this->workflowId(),
            'pool_id'     => $input->poolId,
            'provider_id' => $input->providerId,
            'operation'   => 'add_liquidity',
        ];

        try {
            // Step 1: Validate with circuit breaker
            yield from $this->executeWithCircuitBreaker(
                'liquidity_validation',
                fn () => ActivityStub::make(ValidateLiquidityActivity::class)
                    ->withRetryOptions(LiquidityRetryPolicy::standard())
                    ->execute($input),
                $context
            );

            // Step 2: Lock funds with enhanced error handling
            $lockBase = yield from $this->lockFundsWithRetry(
                $input->providerId,
                $input->baseCurrency,
                $input->baseAmount,
                $input->poolId,
                $context
            );
            $this->lockedBalances[] = $lockBase;

            $lockQuote = yield from $this->lockFundsWithRetry(
                $input->providerId,
                $input->quoteCurrency,
                $input->quoteAmount,
                $input->poolId,
                $context
            );
            $this->lockedBalances[] = $lockQuote;

            // Step 3: Calculate shares with slippage protection
            $shares = yield ActivityStub::make(CalculatePoolSharesActivity::class)
                ->withRetryOptions(LiquidityRetryPolicy::standard())
                ->execute($input);

            // Validate slippage
            if (BigDecimal::of($shares['shares'])->isLessThan($input->minShares)) {
                $slippagePercentage = BigDecimal::of($input->minShares)
                    ->minus($shares['shares'])
                    ->dividedBy($input->minShares, 18)
                    ->multipliedBy(100);

                $this->recordSlippageExceeded(
                    $input->poolId,
                    'add_liquidity',
                    $input->minShares,
                    $shares['shares'],
                    $slippagePercentage->__toString()
                );

                throw new DomainException('Slippage tolerance exceeded');
            }

            // Step 4: Transfer funds with circuit breaker
            yield from $this->executeWithCircuitBreaker(
                'liquidity_transfer',
                fn () => ActivityStub::make(TransferLiquidityActivity::class)
                    ->withRetryOptions(LiquidityRetryPolicy::critical())
                    ->execute(
                        [
                        'from_account_id' => $input->providerId,
                        'to_pool_id'      => $input->poolId,
                        'base_currency'   => $input->baseCurrency,
                        'base_amount'     => $input->baseAmount,
                        'quote_currency'  => $input->quoteCurrency,
                        'quote_amount'    => $input->quoteAmount,
                        ]
                    ),
                $context
            );
            $this->liquidityTransferred = true;

            // Step 5: Update pool state with idempotency
            yield from $this->updatePoolStateWithRetry(
                $input->poolId,
                fn ($pool) => $pool->addLiquidity(
                    providerId: $input->providerId,
                    baseAmount: $input->baseAmount,
                    quoteAmount: $input->quoteAmount,
                    minShares: $input->minShares,
                    metadata: array_merge(
                        $context,
                        [
                        'timestamp'         => now()->toIso8601String(),
                        'execution_time_ms' => (microtime(true) - $startTime) * 1000,
                        'shares_minted'     => $shares['shares'],
                        ]
                    )
                )
            );

            Log::info(
                'Liquidity added successfully',
                array_merge(
                    $context,
                    [
                    'shares_minted'     => $shares['shares'],
                    'execution_time_ms' => (microtime(true) - $startTime) * 1000,
                    ]
                )
            );

            return [
                'success'           => true,
                'shares_minted'     => $shares['shares'],
                'pool_id'           => $input->poolId,
                'provider_id'       => $input->providerId,
                'execution_time_ms' => (microtime(true) - $startTime) * 1000,
            ];
        } catch (Exception $e) {
            Log::error(
                'Failed to add liquidity',
                array_merge(
                    $context,
                    [
                    'error'             => $e->getMessage(),
                    'execution_time_ms' => (microtime(true) - $startTime) * 1000,
                    ]
                )
            );

            // Enhanced compensation with detailed logging
            yield from $this->compensateAddLiquidity($e, $context);

            // Check if pool should be paused
            if ($this->shouldPausePool($e, $input->poolId)) {
                yield from $this->pausePoolEmergency($input->poolId, $e->getMessage());
            }

            return [
                'success'           => false,
                'error'             => $e->getMessage(),
                'compensation_log'  => $this->compensationLog,
                'execution_time_ms' => (microtime(true) - $startTime) * 1000,
            ];
        }
    }

    private function lockFundsWithRetry(
        string $accountId,
        string $currency,
        string $amount,
        string $poolId,
        array $context
    ): Generator {
        $maxAttempts = 3;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            try {
                return yield ActivityStub::make(LockLiquidityActivity::class)
                    ->withRetryOptions(LiquidityRetryPolicy::critical())
                    ->execute(
                        [
                        'account_id' => $accountId,
                        'currency'   => $currency,
                        'amount'     => $amount,
                        'pool_id'    => $poolId,
                        'attempt'    => $attempt + 1,
                        ]
                    );
            } catch (Exception $e) {
                $attempt++;

                if ($attempt >= $maxAttempts) {
                    throw $e;
                }

                Log::warning(
                    'Retrying fund lock',
                    array_merge(
                        $context,
                        [
                        'attempt'  => $attempt,
                        'currency' => $currency,
                        'error'    => $e->getMessage(),
                        ]
                    )
                );

                // Exponential backoff
                yield $this->timer(pow(2, $attempt) * 1000);
            }
        }
    }

    private function updatePoolStateWithRetry(string $poolId, callable $operation): Generator
    {
        $maxAttempts = 5;
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            try {
                $pool = LiquidityPool::retrieve($poolId);
                $operation($pool);
                $pool->persist();

                return;
            } catch (Exception $e) {
                $attempt++;

                if ($attempt >= $maxAttempts || ! $this->isRetryableError($e)) {
                    throw $e;
                }

                Log::warning(
                    'Retrying pool state update',
                    [
                    'pool_id' => $poolId,
                    'attempt' => $attempt,
                    'error'   => $e->getMessage(),
                    ]
                );

                yield $this->timer(100 * $attempt); // Linear backoff
            }
        }
    }

    private function executeWithCircuitBreaker(
        string $service,
        callable $operation,
        array $context
    ): Generator {
        try {
            return yield $this->circuitBreaker->call($service, $operation, $context);
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), 'Circuit breaker is')) {
                Log::error(
                    'Circuit breaker preventing operation',
                    [
                    'service' => $service,
                    'context' => $context,
                    ]
                );
                throw new DomainException('Service temporarily unavailable due to high error rate');
            }
            throw $e;
        }
    }

    private function compensateAddLiquidity(Exception $error, array $context): Generator
    {
        $this->compensationLog[] = [
            'started_at' => now()->toIso8601String(),
            'reason'     => $error->getMessage(),
            'context'    => $context,
        ];

        // Release locked balances in parallel
        $releaseActivities = [];
        foreach ($this->lockedBalances as $lock) {
            $releaseActivities[] = ActivityStub::make(ReleaseLiquidityActivity::class)
                ->withRetryOptions(LiquidityRetryPolicy::critical())
                ->execute($lock);
        }

        // Execute releases in parallel
        $results = yield Activity::all($releaseActivities);

        foreach ($results as $index => $result) {
            if ($result instanceof Exception) {
                $this->compensationLog[] = [
                    'action' => 'release_lock_failed',
                    'lock'   => $this->lockedBalances[$index],
                    'error'  => $result->getMessage(),
                ];
            } else {
                $this->compensationLog[] = [
                    'action' => 'release_lock_success',
                    'lock'   => $this->lockedBalances[$index],
                ];
            }
        }

        // Handle liquidity transfer reversal if needed
        if ($this->liquidityTransferred) {
            $this->compensationLog[] = [
                'action' => 'liquidity_transfer_reversal_required',
                'status' => 'manual_intervention_needed',
            ];
        }

        $this->compensationLog[] = [
            'completed_at' => now()->toIso8601String(),
            'status'       => 'compensation_completed',
        ];
    }

    private function shouldPausePool(Exception $error, string $poolId): bool
    {
        // Pause pool on critical errors
        $criticalErrors = [
            'Insufficient liquidity',
            'Pool ratio severely imbalanced',
            'External service failure',
        ];

        foreach ($criticalErrors as $criticalError) {
            if (str_contains($error->getMessage(), $criticalError)) {
                return true;
            }
        }

        // Check error frequency
        $recentErrors = Cache::get("pool:{$poolId}:errors", 0);
        if ($recentErrors >= 10) {
            return true;
        }

        return false;
    }

    private function pausePoolEmergency(string $poolId, string $reason): Generator
    {
        try {
            $pool = LiquidityPool::retrieve($poolId);
            $pool->updateParameters(
                isActive: false,
                metadata: [
                'paused_by'    => 'system',
                'pause_reason' => $reason,
                ]
            )->persist();

            // Record emergency pause event
            event(
                new EmergencyPoolPaused(
                    poolId: $poolId,
                    reason: $reason,
                    pausedBy: 'system',
                    pausedAt: now()->toIso8601String(),
                    poolState: [], // Would include current pool state
                    metadata: ['workflow_id' => $this->workflowId()]
                )
            );

            Log::critical(
                'Pool paused due to emergency',
                [
                'pool_id' => $poolId,
                'reason'  => $reason,
                ]
            );

            yield; // Required for Generator return type
        } catch (Exception $e) {
            Log::error(
                'Failed to pause pool',
                [
                'pool_id' => $poolId,
                'error'   => $e->getMessage(),
                ]
            );

            yield; // Required for Generator return type
        }
    }

    private function recordSlippageExceeded(
        string $poolId,
        string $transactionType,
        string $expectedAmount,
        string $actualAmount,
        string $slippagePercentage
    ): void {
        event(
            new PoolSlippageExceeded(
                poolId: $poolId,
                transactionType: $transactionType,
                expectedAmount: $expectedAmount,
                actualAmount: $actualAmount,
                slippagePercentage: $slippagePercentage,
                maxSlippageTolerance: '1', // 1% default
                metadata: ['workflow_id' => $this->workflowId()]
            )
        );
    }

    private function isRetryableError(Exception $e): bool
    {
        $nonRetryableErrors = [
            DomainException::class,
            InvalidArgumentException::class,
        ];

        foreach ($nonRetryableErrors as $errorClass) {
            if ($e instanceof $errorClass) {
                return false;
            }
        }

        return true;
    }
}
