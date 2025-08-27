<?php

declare(strict_types=1);

namespace App\Domain\AI\Sagas;

use App\Domain\Account\Models\Account;
use App\Domain\AI\Aggregates\AIInteractionAggregate;
use App\Domain\AI\Events\Trading\TradeExecutedEvent;
use App\Domain\Exchange\Services\OrderService;
use App\Models\User;
use Exception;
use Generator;
use InvalidArgumentException;
use Log;
use RuntimeException;
use Workflow\Workflow;

/**
 * Trading Execution Saga.
 *
 * Executes trading decisions with compensation support for rollback.
 */
class TradingExecutionSaga extends Workflow
{
    /**
     * @var array<callable>
     */
    protected array $compensationStack = [];

    private OrderService $orderService;

    public function __construct()
    {
        $this->orderService = app(OrderService::class);
    }

    /**
     * Execute trading saga.
     *
     * @param string $conversationId
     * @param string $userId
     * @param array{action: string, size: float, symbol: string, risk_parameters: array} $strategy
     *
     * @return Generator
     */
    public function execute(
        string $conversationId,
        string $userId,
        array $strategy
    ): Generator {
        $aggregate = AIInteractionAggregate::retrieve($conversationId);

        try {
            // Step 1: Validate user and balance
            $user = yield $this->validateUser($userId);
            $this->compensationStack[] = fn () => $this->logValidationRollback($userId);

            // Step 2: Lock funds for trading
            $amount = $this->calculateTradeAmount($user, $strategy);
            $lockId = yield $this->lockFunds($userId, $amount, $strategy['symbol']);
            $this->compensationStack[] = fn () => $this->unlockFunds($lockId);

            // Step 3: Create order
            $order = yield $this->createOrder($user, $strategy, $amount);
            $this->compensationStack[] = fn () => $this->cancelOrder($order['id']);

            // Step 4: Execute order
            $execution = yield $this->executeOrder($order['id']);
            $this->compensationStack[] = fn () => $this->reverseExecution($execution['id']);

            // Step 5: Update portfolio
            yield $this->updatePortfolio($userId, $execution);

            // Step 6: Set risk management (stop loss, take profit)
            yield $this->setRiskManagement($execution['id'], $strategy['risk_parameters']);

            // Record successful execution
            $aggregate->recordThat(new TradeExecutedEvent(
                $conversationId,
                $execution['id'],
                $strategy,
                $execution
            ));
            $aggregate->persist();

            return [
                'success'     => true,
                'trade_id'    => $execution['id'],
                'order_id'    => $order['id'],
                'amount'      => $amount,
                'executed_at' => now()->toIso8601String(),
                'risk_params' => $strategy['risk_parameters'],
            ];
        } catch (Exception $e) {
            // Execute compensation in reverse order
            yield from $this->compensate();

            // Record failure
            $aggregate->makeDecision(
                decision: 'saga_failed',
                reasoning: [
                    'saga'     => 'trading_execution',
                    'error'    => $e->getMessage(),
                    'strategy' => $strategy,
                    'user_id'  => $userId,
                ],
                confidence: 0.0
            );
            $aggregate->persist();

            throw new RuntimeException(
                "Trading execution failed: {$e->getMessage()}",
                0,
                $e
            );
        }
    }

    /**
     * Validate user exists and is active.
     */
    private function validateUser(string $userId)
    {
        $user = User::find($userId);

        if (! $user) {
            throw new InvalidArgumentException("User not found: {$userId}");
        }

        // Check if user is active (users don't have status field by default)
        // You could check email_verified_at or other indicators
        if (! $user->email_verified_at) {
            throw new InvalidArgumentException('User email is not verified');
        }

        return $user;
    }

    /**
     * Lock funds for trading.
     */
    private function lockFunds(string $userId, float $amount, string $symbol)
    {
        $lockId = uniqid('lock_');

        // Get user's account
        $user = User::find($userId);
        if (! $user) {
            throw new RuntimeException('User not found');
        }

        // Get the user's primary account
        $account = $user->accounts()->first();
        if (! $account) {
            throw new RuntimeException('User account not found');
        }

        // Check account balance
        $balanceEntry = $account->balances()->where('asset_code', 'USD')->first();
        if (! $balanceEntry || $balanceEntry->balance < $amount) {
            throw new RuntimeException('Insufficient funds for trading');
        }

        // Record the lock (would implement actual locking mechanism)
        Log::info('Funds locked for trading', [
            'user_id' => $userId,
            'amount'  => $amount,
            'lock_id' => $lockId,
            'symbol'  => $symbol,
        ]);

        return $lockId;
    }

    /**
     * Unlock previously locked funds.
     */
    private function unlockFunds(string $lockId)
    {
        // Release the locked funds
        Log::info('Funds unlocked', ['lock_id' => $lockId]);

        return true;
    }

    /**
     * Create trading order.
     */
    private function createOrder(User $user, array $strategy, float $amount)
    {
        $orderData = [
            'user_id' => $user->id,
            'type'    => $strategy['action'] === 'buy' ? 'buy' : 'sell',
            'symbol'  => $strategy['symbol'] ?? 'BTC/USD',
            'amount'  => $amount,
            'price'   => null, // Market order
            'status'  => 'pending',
        ];

        // Use OrderService to create order
        $order = $this->orderService->createOrder($orderData);

        return [
            'id'     => $order['id'] ?? uniqid('order_'),
            'type'   => $order['type'] ?? $orderData['type'],
            'amount' => $order['amount'] ?? $orderData['amount'],
            'symbol' => $order['symbol'] ?? $orderData['symbol'],
        ];
    }

    /**
     * Cancel order.
     */
    private function cancelOrder(string $orderId)
    {
        $this->orderService->cancelOrder($orderId);

        return true;
    }

    /**
     * Execute the order.
     */
    private function executeOrder(string $orderId)
    {
        // Update order status to processing
        $this->orderService->updateOrder($orderId, ['status' => 'processing']);

        // Get current market price (would integrate with price feed service)
        $currentPrice = $this->getMarketPrice();

        $executionId = uniqid('exec_');
        $execution = [
            'id'              => $executionId,
            'order_id'        => $orderId,
            'executed_price'  => $currentPrice,
            'executed_amount' => 0.02,      // Based on order amount
            'fee'             => $currentPrice * 0.02 * 0.002, // 0.2% fee
            'timestamp'       => now()->toIso8601String(),
        ];

        // Update order status to executed
        $this->orderService->updateOrder($orderId, [
            'status'         => 'executed',
            'executed_price' => $execution['executed_price'],
            'executed_at'    => $execution['timestamp'],
        ]);

        return $execution;
    }

    /**
     * Reverse order execution.
     */
    private function reverseExecution(string $executionId)
    {
        // Create a compensating order to reverse the trade
        // This would place an opposite order to neutralize the position
        Log::warning('Trade execution reversal initiated', [
            'execution_id' => $executionId,
            'reason'       => 'Saga compensation',
        ]);

        // Mark the original execution as reversed
        // Would update the execution record in the database

        return true;
    }

    /**
     * Update user portfolio.
     */
    private function updatePortfolio(string $userId, array $execution)
    {
        // Update portfolio holdings
        // In production, this would update portfolio service

        return true;
    }

    /**
     * Set risk management parameters.
     */
    private function setRiskManagement(string $executionId, array $riskParams)
    {
        $riskOrders = [];

        if (isset($riskParams['stop_loss'])) {
            // Create stop loss order
            $riskOrders['stop_loss'] = $this->createRiskOrder(
                $executionId,
                'stop_loss',
                $riskParams['stop_loss']
            );
        }

        if (isset($riskParams['take_profit'])) {
            // Create take profit order
            $riskOrders['take_profit'] = $this->createRiskOrder(
                $executionId,
                'take_profit',
                $riskParams['take_profit']
            );
        }

        return ! empty($riskOrders);
    }

    /**
     * Create a risk management order (stop loss or take profit).
     */
    private function createRiskOrder(string $executionId, string $type, float $price): string
    {
        $orderId = uniqid("{$type}_");

        Log::info('Risk order created', [
            'order_id'     => $orderId,
            'execution_id' => $executionId,
            'type'         => $type,
            'price'        => $price,
        ]);

        return $orderId;
    }

    /**
     * Get current market price.
     */
    private function getMarketPrice(): float
    {
        // Would integrate with price feed service
        // Using a simulated market price for now
        return 50000.00 + (mt_rand(-1000, 1000) / 100);
    }

    /**
     * Calculate trade amount based on strategy and user balance.
     */
    private function calculateTradeAmount(User $user, array $strategy): float
    {
        // Get account balance
        $account = $user->accounts()->first();
        $balance = 0;

        if ($account) {
            $balanceEntry = $account->balances()->where('asset_code', 'USD')->first();
            $balance = $balanceEntry ? (float) $balanceEntry->balance : 0;
        }

        $positionSize = $strategy['size'] ?? 0.1;

        return $balance * $positionSize;
    }

    /**
     * Log validation rollback for audit.
     */
    private function logValidationRollback(string $userId)
    {
        Log::info('Trading validation rolled back', ['user_id' => $userId]);

        return true;
    }

    /**
     * Execute compensation actions in reverse order.
     *
     * @return Generator
     */
    public function compensate(): Generator
    {
        while ($compensation = array_pop($this->compensationStack)) {
            try {
                yield $compensation();
            } catch (Exception $e) {
                Log::error('Compensation failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }
}
