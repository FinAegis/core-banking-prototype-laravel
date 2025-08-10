<?php

declare(strict_types=1);

namespace App\Domain\AI\Sagas;

use App\Domain\AI\Aggregates\AIInteractionAggregate;
use App\Domain\AI\Events\Trading\TradeExecutedEvent;
use App\Domain\Exchange\Services\OrderService;
use App\Domain\Wallet\Services\WalletService;
use App\Models\User;
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

    private WalletService $walletService;

    public function __construct()
    {
        $this->orderService = app(OrderService::class);
        $this->walletService = app(WalletService::class);
    }

    /**
     * Execute trading saga.
     *
     * @param string $conversationId
     * @param string $userId
     * @param array{action: string, size: float, symbol: string, risk_parameters: array} $strategy
     *
     * @return \Generator
     */
    public function execute(
        string $conversationId,
        string $userId,
        array $strategy
    ): \Generator {
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
        } catch (\Exception $e) {
            // Execute compensation in reverse order
            yield from $this->compensate();

            // Record failure
            $aggregate->recordSagaFailed(
                'trading_execution',
                $e->getMessage(),
                [
                    'strategy' => $strategy,
                    'user_id'  => $userId,
                ]
            );
            $aggregate->persist();

            throw new \RuntimeException(
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
            throw new \InvalidArgumentException("User not found: {$userId}");
        }

        if ($user->status !== 'active') {
            throw new \InvalidArgumentException('User account is not active');
        }

        return $user;
    }

    /**
     * Lock funds for trading.
     */
    private function lockFunds(string $userId, float $amount, string $symbol)
    {
        $lockId = uniqid('lock_');

        // In production, this would interact with WalletService
        $balance = $this->walletService->getBalance($userId, 'USD');

        if ($balance < $amount) {
            throw new \RuntimeException('Insufficient funds for trading');
        }

        // Lock the funds
        $this->walletService->lockFunds($userId, 'USD', $amount, $lockId);

        return $lockId;
    }

    /**
     * Unlock previously locked funds.
     */
    private function unlockFunds(string $lockId)
    {
        $this->walletService->unlockFunds($lockId);

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

        // In production, this would use OrderService
        $order = $this->orderService->createOrder($orderData);

        return [
            'id'     => $order->id,
            'type'   => $order->type,
            'amount' => $order->amount,
            'symbol' => $order->symbol,
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
        $execution = $this->orderService->executeOrder($orderId);

        return [
            'id'              => $execution->id,
            'order_id'        => $orderId,
            'executed_price'  => $execution->price,
            'executed_amount' => $execution->amount,
            'fee'             => $execution->fee,
            'timestamp'       => $execution->created_at,
        ];
    }

    /**
     * Reverse order execution.
     */
    private function reverseExecution(string $executionId)
    {
        // Create reverse trade
        $this->orderService->reverseExecution($executionId);

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
        if (isset($riskParams['stop_loss'])) {
            $this->orderService->setStopLoss($executionId, $riskParams['stop_loss']);
        }

        if (isset($riskParams['take_profit'])) {
            $this->orderService->setTakeProfit($executionId, $riskParams['take_profit']);
        }

        return true;
    }

    /**
     * Calculate trade amount based on strategy and user balance.
     */
    private function calculateTradeAmount(User $user, array $strategy): float
    {
        $balance = $this->walletService->getBalance($user->id, 'USD');
        $positionSize = $strategy['size'] ?? 0.1;

        return $balance * $positionSize;
    }

    /**
     * Log validation rollback for audit.
     */
    private function logValidationRollback(string $userId)
    {
        \Log::info('Trading validation rolled back', ['user_id' => $userId]);

        return true;
    }

    /**
     * Execute compensation actions in reverse order.
     *
     * @return \Generator
     */
    public function compensate(): \Generator
    {
        while ($compensation = array_pop($this->compensationStack)) {
            try {
                yield $compensation();
            } catch (\Exception $e) {
                \Log::error('Compensation failed', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }
    }
}
