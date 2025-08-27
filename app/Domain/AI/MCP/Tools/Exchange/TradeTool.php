<?php

declare(strict_types=1);

namespace App\Domain\AI\MCP\Tools\Exchange;

use App\Domain\Account\Models\Account;
use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use App\Domain\Asset\Models\Asset;
use App\Domain\Exchange\Services\ExchangeService;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TradeTool implements MCPToolInterface
{
    public function __construct(
        private readonly ExchangeService $exchangeService
    ) {
    }

    public function getName(): string
    {
        return 'exchange.trade';
    }

    public function getCategory(): string
    {
        return 'exchange';
    }

    public function getDescription(): string
    {
        return 'Execute a trade order on the exchange';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'account_uuid' => [
                    'type'        => 'string',
                    'description' => 'The UUID of the trading account',
                    'pattern'     => '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$',
                ],
                'type' => [
                    'type'        => 'string',
                    'description' => 'Order type: buy or sell',
                    'enum'        => ['buy', 'sell'],
                ],
                'order_type' => [
                    'type'        => 'string',
                    'description' => 'Order execution type',
                    'enum'        => ['market', 'limit', 'stop', 'stop-limit'],
                ],
                'base_currency' => [
                    'type'        => 'string',
                    'description' => 'Base currency (what you\'re buying/selling)',
                    'pattern'     => '^[A-Z]{3,10}$',
                ],
                'quote_currency' => [
                    'type'        => 'string',
                    'description' => 'Quote currency (what you\'re paying with/receiving)',
                    'pattern'     => '^[A-Z]{3,10}$',
                ],
                'amount' => [
                    'type'        => 'number',
                    'description' => 'Amount of base currency to trade',
                    'minimum'     => 0.00000001,
                ],
                'price' => [
                    'type'        => 'number',
                    'description' => 'Limit price (required for limit orders)',
                    'minimum'     => 0.00000001,
                ],
                'stop_price' => [
                    'type'        => 'number',
                    'description' => 'Stop price (required for stop orders)',
                    'minimum'     => 0.00000001,
                ],
                'time_in_force' => [
                    'type'        => 'string',
                    'description' => 'How long the order remains active',
                    'enum'        => ['GTC', 'IOC', 'FOK', 'GTD'],
                    'default'     => 'GTC',
                ],
            ],
            'required' => ['account_uuid', 'type', 'order_type', 'base_currency', 'quote_currency', 'amount'],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'order_id'           => ['type' => 'string'],
                'account_uuid'       => ['type' => 'string'],
                'type'               => ['type' => 'string'],
                'order_type'         => ['type' => 'string'],
                'base_currency'      => ['type' => 'string'],
                'quote_currency'     => ['type' => 'string'],
                'amount'             => ['type' => 'number'],
                'price'              => ['type' => 'number'],
                'status'             => ['type' => 'string'],
                'filled_amount'      => ['type' => 'number'],
                'remaining_amount'   => ['type' => 'number'],
                'average_fill_price' => ['type' => 'number'],
                'fee_amount'         => ['type' => 'number'],
                'fee_currency'       => ['type' => 'string'],
                'created_at'         => ['type' => 'string'],
                'updated_at'         => ['type' => 'string'],
                'time_in_force'      => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $parameters, ?string $conversationId = null): ToolExecutionResult
    {
        try {
            $accountUuid = $parameters['account_uuid'];
            $type = $parameters['type'];
            $orderType = $parameters['order_type'];
            $baseCurrency = $parameters['base_currency'];
            $quoteCurrency = $parameters['quote_currency'];
            /** @var numeric-string $amount */
            $amount = (string) $parameters['amount'];
            /** @var numeric-string|null $price */
            $price = isset($parameters['price']) ? (string) $parameters['price'] : null;
            $stopPrice = isset($parameters['stop_price']) ? (string) $parameters['stop_price'] : null;
            $timeInForce = $parameters['time_in_force'] ?? 'GTC';

            Log::info('MCP Tool: Executing trade', [
                'account_uuid'    => $accountUuid,
                'type'            => $type,
                'order_type'      => $orderType,
                'pair'            => "{$baseCurrency}/{$quoteCurrency}",
                'amount'          => $amount,
                'conversation_id' => $conversationId,
            ]);

            // Get account from database
            $account = Account::where('uuid', $accountUuid)->first();

            if (! $account) {
                return ToolExecutionResult::failure("Account not found: {$accountUuid}");
            }

            // Check authorization
            if (! $this->canTradeOnAccount($account)) {
                return ToolExecutionResult::failure('Unauthorized trading access to account');
            }

            // Validate currencies exist and are tradeable
            $baseAsset = Asset::where('code', $baseCurrency)->first();
            $quoteAsset = Asset::where('code', $quoteCurrency)->first();

            if (! $baseAsset || ! $quoteAsset) {
                return ToolExecutionResult::failure('Invalid currency pair');
            }

            if (! $baseAsset->is_tradeable || ! $quoteAsset->is_tradeable) {
                return ToolExecutionResult::failure('Currency pair not available for trading');
            }

            // Validate order type requirements
            if ($orderType === 'limit' && ! $price) {
                return ToolExecutionResult::failure('Price is required for limit orders');
            }

            if (in_array($orderType, ['stop', 'stop-limit']) && ! $stopPrice) {
                return ToolExecutionResult::failure('Stop price is required for stop orders');
            }

            // Check account balance for the order
            if ($type === 'buy') {
                // For buy orders, check quote currency balance
                $requiredCurrency = $quoteCurrency;
                /** @var numeric-string $requiredAmount */
                $requiredAmount = $orderType === 'market' ?
                    $this->estimateMarketOrderCost($amount, $baseCurrency, $quoteCurrency) :
                    bcmul($amount, (string) ($price ?? '0'), 18);
            } else {
                // For sell orders, check base currency balance
                $requiredCurrency = $baseCurrency;
                /** @var numeric-string $requiredAmount */
                $requiredAmount = $amount;
            }

            $availableBalance = $account->getBalance($requiredCurrency) / 100; // Convert from cents
            if (bccomp((string) $availableBalance, $requiredAmount, 8) < 0) {
                return ToolExecutionResult::failure(
                    sprintf(
                        'Insufficient balance. Required: %s %s, Available: %s %s',
                        $requiredAmount,
                        $requiredCurrency,
                        $availableBalance,
                        $requiredCurrency
                    )
                );
            }

            // Place the order using ExchangeService
            $orderResult = $this->exchangeService->placeOrder(
                accountId: (string) $account->id,
                type: $type,
                orderType: $orderType,
                baseCurrency: $baseCurrency,
                quoteCurrency: $quoteCurrency,
                amount: $amount,
                price: $price,
                stopPrice: $stopPrice,
                metadata: [
                    'time_in_force'   => $timeInForce,
                    'conversation_id' => $conversationId,
                    'placed_via'      => 'mcp_tool',
                ]
            );

            // Calculate estimated fee (0.1% for maker, 0.2% for taker)
            $feeRate = $orderType === 'limit' ? '0.001' : '0.002';
            $feeAmount = bcmul($amount, $feeRate, 8);
            $feeCurrency = $type === 'buy' ? $baseCurrency : $quoteCurrency;

            $response = [
                'order_id'           => $orderResult['order_id'],
                'account_uuid'       => $accountUuid,
                'type'               => $type,
                'order_type'         => $orderType,
                'base_currency'      => $baseCurrency,
                'quote_currency'     => $quoteCurrency,
                'amount'             => (float) $amount,
                'price'              => $price ? (float) $price : null,
                'status'             => $orderResult['status'] ?? 'pending',
                'filled_amount'      => 0,
                'remaining_amount'   => (float) $amount,
                'average_fill_price' => 0,
                'fee_amount'         => (float) $feeAmount,
                'fee_currency'       => $feeCurrency,
                'created_at'         => now()->toIso8601String(),
                'updated_at'         => now()->toIso8601String(),
                'time_in_force'      => $timeInForce,
            ];

            return ToolExecutionResult::success($response);
        } catch (Exception $e) {
            Log::error('MCP Tool error: exchange.trade', [
                'error'      => $e->getMessage(),
                'parameters' => $parameters,
            ]);

            return ToolExecutionResult::failure($e->getMessage());
        }
    }

    public function getCapabilities(): array
    {
        return [
            'write',
            'trading',
            'order-management',
            'multi-currency',
            'balance-check',
        ];
    }

    public function isCacheable(): bool
    {
        return false; // Trading operations should never be cached
    }

    public function getCacheTtl(): int
    {
        return 0;
    }

    public function validateInput(array $parameters): bool
    {
        // UUID validation
        if (! isset($parameters['account_uuid'])) {
            return false;
        }

        $uuid = $parameters['account_uuid'];
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
            return false;
        }

        // Order type validation
        if (! in_array($parameters['type'] ?? '', ['buy', 'sell'])) {
            return false;
        }

        if (! in_array($parameters['order_type'] ?? '', ['market', 'limit', 'stop', 'stop-limit'])) {
            return false;
        }

        // Currency validation
        foreach (['base_currency', 'quote_currency'] as $field) {
            if (! isset($parameters[$field])) {
                return false;
            }

            $currency = $parameters[$field];
            if (! preg_match('/^[A-Z]{3,10}$/', $currency)) {
                return false;
            }
        }

        // Amount validation
        if (! isset($parameters['amount']) || $parameters['amount'] <= 0) {
            return false;
        }

        // Price validation for limit orders
        if ($parameters['order_type'] === 'limit' && ! isset($parameters['price'])) {
            return false;
        }

        return true;
    }

    public function authorize(?string $userId): bool
    {
        // Trading requires authentication
        if (! $userId && ! Auth::check()) {
            return false;
        }

        return true;
    }

    private function canTradeOnAccount($account): bool
    {
        // Check if current user owns the account or has trading permission
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        // Check ownership via user_uuid
        if ($account->user_uuid === $user->uuid) {
            return true;
        }

        // Check for trading permission
        if (method_exists($user, 'can') && $user->can('trade', $account)) {
            return true;
        }

        // Check for admin permission
        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            return true;
        }

        return false;
    }

    /**
     * @param numeric-string $amount
     * @return numeric-string
     */
    private function estimateMarketOrderCost(string $amount, string $baseCurrency, string $quoteCurrency): string
    {
        // For market orders, estimate cost based on current market price
        // In production, this would fetch from order book or last trade price
        // For now, we'll use a simple multiplier
        try {
            $marketData = $this->exchangeService->getMarketData($baseCurrency, $quoteCurrency);
            /** @var numeric-string $estimatedPrice */
            $estimatedPrice = (string) ($marketData['last_price'] ?? '1');

            return bcmul($amount, $estimatedPrice, 18);
        } catch (Exception $e) {
            // If we can't get market data, use a conservative estimate
            return bcmul($amount, '10000', 18); // High estimate to ensure sufficient balance
        }
    }
}
