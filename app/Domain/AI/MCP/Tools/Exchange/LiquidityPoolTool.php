<?php

declare(strict_types=1);

namespace App\Domain\AI\MCP\Tools\Exchange;

use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use App\Domain\Exchange\Services\LiquidityPoolService;
use App\Domain\Exchange\ValueObjects\LiquidityAdditionInput;
use App\Domain\Exchange\ValueObjects\LiquidityRemovalInput;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class LiquidityPoolTool implements MCPToolInterface
{
    public function __construct(
        private readonly LiquidityPoolService $liquidityPoolService
    ) {
    }

    public function getName(): string
    {
        return 'exchange.liquidity_pool';
    }

    public function getCategory(): string
    {
        return 'exchange';
    }

    public function getDescription(): string
    {
        return 'Manage liquidity pools for exchange operations';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'action' => [
                    'type'        => 'string',
                    'description' => 'Action to perform',
                    'enum'        => ['create', 'add_liquidity', 'remove_liquidity', 'get_info', 'get_metrics', 'list_pools', 'my_positions'],
                ],
                'pool_id' => [
                    'type'        => 'string',
                    'description' => 'UUID of the liquidity pool',
                    'pattern'     => '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$',
                ],
                'base_currency' => [
                    'type'        => 'string',
                    'description' => 'Base currency code',
                    'enum'        => ['USD', 'EUR', 'GBP', 'BTC', 'ETH', 'USDC', 'USDT', 'DAI'],
                ],
                'quote_currency' => [
                    'type'        => 'string',
                    'description' => 'Quote currency code',
                    'enum'        => ['USD', 'EUR', 'GBP', 'BTC', 'ETH', 'USDC', 'USDT', 'DAI'],
                ],
                'base_amount' => [
                    'type'        => 'string',
                    'description' => 'Amount of base currency to add/remove',
                    'pattern'     => '^\\d+(\\.\\d+)?$',
                ],
                'quote_amount' => [
                    'type'        => 'string',
                    'description' => 'Amount of quote currency to add/remove',
                    'pattern'     => '^\\d+(\\.\\d+)?$',
                ],
                'shares' => [
                    'type'        => 'string',
                    'description' => 'Amount of LP shares to remove',
                    'pattern'     => '^\\d+(\\.\\d+)?$',
                ],
                'fee_rate' => [
                    'type'        => 'string',
                    'description' => 'Fee rate for the pool (e.g., 0.003 for 0.3%)',
                    'pattern'     => '^0\\.\\d+$',
                    'default'     => '0.003',
                ],
                'account_id' => [
                    'type'        => 'string',
                    'description' => 'Account UUID for the liquidity provider',
                    'pattern'     => '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$',
                ],
                'user_uuid' => [
                    'type'        => 'string',
                    'description' => 'UUID of the user (optional, defaults to current user)',
                    'pattern'     => '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$',
                ],
            ],
            'required' => ['action'],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'pool_id'         => ['type' => 'string'],
                'base_currency'   => ['type' => 'string'],
                'quote_currency'  => ['type' => 'string'],
                'base_reserve'    => ['type' => 'string'],
                'quote_reserve'   => ['type' => 'string'],
                'total_shares'    => ['type' => 'string'],
                'fee_rate'        => ['type' => 'string'],
                'tvl'             => ['type' => 'string'],
                'apy'             => ['type' => 'string'],
                'volume_24h'      => ['type' => 'string'],
                'fees_24h'        => ['type' => 'string'],
                'price_impact'    => ['type' => 'string'],
                'shares_received' => ['type' => 'string'],
                'shares_removed'  => ['type' => 'string'],
                'base_received'   => ['type' => 'string'],
                'quote_received'  => ['type' => 'string'],
                'status'          => ['type' => 'string'],
                'positions'       => ['type' => 'array'],
                'pools'           => ['type' => 'array'],
                'message'         => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $parameters, ?string $conversationId = null): ToolExecutionResult
    {
        try {
            $action = $parameters['action'];

            Log::info('MCP Tool: Liquidity pool action', [
                'action'          => $action,
                'conversation_id' => $conversationId,
            ]);

            // Get the user
            $user = $this->getUser($parameters['user_uuid'] ?? null);

            if (! $user && in_array($action, ['add_liquidity', 'remove_liquidity', 'my_positions'])) {
                return ToolExecutionResult::failure('User authentication required for this action');
            }

            return match ($action) {
                'create'           => $this->createPool($parameters, $user),
                'add_liquidity'    => $this->addLiquidity($parameters, $user),
                'remove_liquidity' => $this->removeLiquidity($parameters, $user),
                'get_info'         => $this->getPoolInfo($parameters),
                'get_metrics'      => $this->getPoolMetrics($parameters),
                'list_pools'       => $this->listPools(),
                'my_positions'     => $this->getMyPositions($user),
                default            => ToolExecutionResult::failure("Unknown action: {$action}"),
            };
        } catch (\Exception $e) {
            Log::error('MCP Tool error: exchange.liquidity_pool', [
                'error'      => $e->getMessage(),
                'parameters' => $parameters,
            ]);

            return ToolExecutionResult::failure($e->getMessage());
        }
    }

    private function getUser(?string $userUuid): ?User
    {
        if ($userUuid) {
            return User::where('uuid', $userUuid)->first();
        }

        return Auth::user();
    }

    private function createPool(array $parameters, ?User $user): ToolExecutionResult
    {
        // Check authorization
        if (! $this->canCreatePool($user)) {
            return ToolExecutionResult::failure('Unauthorized to create liquidity pools');
        }

        if (! isset($parameters['base_currency']) || ! isset($parameters['quote_currency'])) {
            return ToolExecutionResult::failure('Base and quote currencies are required');
        }

        try {
            $poolId = $this->liquidityPoolService->createPool(
                baseCurrency: $parameters['base_currency'],
                quoteCurrency: $parameters['quote_currency'],
                feeRate: $parameters['fee_rate'] ?? '0.003',
                metadata: [
                    'created_by'      => $user?->uuid,
                    'conversation_id' => $parameters['conversation_id'] ?? null,
                ]
            );

            $pool = $this->liquidityPoolService->getPool($poolId);

            return ToolExecutionResult::success([
                'pool_id'        => $poolId,
                'base_currency'  => $pool->base_currency,
                'quote_currency' => $pool->quote_currency,
                'fee_rate'       => $pool->fee_rate,
                'status'         => $pool->status,
                'message'        => 'Liquidity pool created successfully',
            ]);
        } catch (\DomainException $e) {
            return ToolExecutionResult::failure($e->getMessage());
        }
    }

    private function addLiquidity(array $parameters, ?User $user): ToolExecutionResult
    {
        if (! $user) {
            return ToolExecutionResult::failure('User authentication required');
        }

        if (! isset($parameters['pool_id']) && ! (isset($parameters['base_currency']) && isset($parameters['quote_currency']))) {
            return ToolExecutionResult::failure('Pool ID or currency pair required');
        }

        if (! isset($parameters['base_amount']) || ! isset($parameters['quote_amount'])) {
            return ToolExecutionResult::failure('Base and quote amounts are required');
        }

        // Get pool ID if not provided
        $poolId = $parameters['pool_id'] ?? null;
        if (! $poolId && isset($parameters['base_currency']) && isset($parameters['quote_currency'])) {
            $pool = $this->liquidityPoolService->getPoolByPair(
                $parameters['base_currency'],
                $parameters['quote_currency']
            );
            if (! $pool) {
                return ToolExecutionResult::failure('Pool not found for the given currency pair');
            }
            $poolId = $pool->pool_id;
        }

        try {
            // Get pool to retrieve currency information
            $pool = $this->liquidityPoolService->getPool($poolId);
            if (! $pool) {
                return ToolExecutionResult::failure('Pool not found');
            }

            $input = new LiquidityAdditionInput(
                poolId: $poolId,
                providerId: $parameters['account_id'] ?? $user->uuid,
                baseCurrency: $pool->base_currency,
                quoteCurrency: $pool->quote_currency,
                baseAmount: $parameters['base_amount'],
                quoteAmount: $parameters['quote_amount']
            );

            $result = $this->liquidityPoolService->addLiquidity($input);

            return ToolExecutionResult::success([
                'pool_id'         => $poolId,
                'shares_received' => $result['shares_received'] ?? '0',
                'base_added'      => $parameters['base_amount'],
                'quote_added'     => $parameters['quote_amount'],
                'status'          => 'success',
                'message'         => 'Liquidity added successfully',
            ]);
        } catch (\Exception $e) {
            return ToolExecutionResult::failure($e->getMessage());
        }
    }

    private function removeLiquidity(array $parameters, ?User $user): ToolExecutionResult
    {
        if (! $user) {
            return ToolExecutionResult::failure('User authentication required');
        }

        if (! isset($parameters['pool_id']) && ! (isset($parameters['base_currency']) && isset($parameters['quote_currency']))) {
            return ToolExecutionResult::failure('Pool ID or currency pair required');
        }

        if (! isset($parameters['shares'])) {
            return ToolExecutionResult::failure('Number of shares to remove is required');
        }

        // Get pool ID if not provided
        $poolId = $parameters['pool_id'] ?? null;
        if (! $poolId && isset($parameters['base_currency']) && isset($parameters['quote_currency'])) {
            $pool = $this->liquidityPoolService->getPoolByPair(
                $parameters['base_currency'],
                $parameters['quote_currency']
            );
            if (! $pool) {
                return ToolExecutionResult::failure('Pool not found for the given currency pair');
            }
            $poolId = $pool->pool_id;
        }

        try {
            $input = new LiquidityRemovalInput(
                poolId: $poolId,
                providerId: $parameters['account_id'] ?? $user->uuid,
                shares: $parameters['shares']
            );

            $result = $this->liquidityPoolService->removeLiquidity($input);

            return ToolExecutionResult::success([
                'pool_id'        => $poolId,
                'shares_removed' => $parameters['shares'],
                'base_received'  => $result['base_received'] ?? '0',
                'quote_received' => $result['quote_received'] ?? '0',
                'status'         => 'success',
                'message'        => 'Liquidity removed successfully',
            ]);
        } catch (\Exception $e) {
            return ToolExecutionResult::failure($e->getMessage());
        }
    }

    private function getPoolInfo(array $parameters): ToolExecutionResult
    {
        if (! isset($parameters['pool_id']) && ! (isset($parameters['base_currency']) && isset($parameters['quote_currency']))) {
            return ToolExecutionResult::failure('Pool ID or currency pair required');
        }

        $pool = null;
        if (isset($parameters['pool_id'])) {
            $pool = $this->liquidityPoolService->getPool($parameters['pool_id']);
        } elseif (isset($parameters['base_currency']) && isset($parameters['quote_currency'])) {
            $pool = $this->liquidityPoolService->getPoolByPair(
                $parameters['base_currency'],
                $parameters['quote_currency']
            );
        }

        if (! $pool) {
            return ToolExecutionResult::failure('Pool not found');
        }

        return ToolExecutionResult::success([
            'pool_id'        => $pool->pool_id,
            'base_currency'  => $pool->base_currency,
            'quote_currency' => $pool->quote_currency,
            'base_reserve'   => $pool->base_reserve,
            'quote_reserve'  => $pool->quote_reserve,
            'total_shares'   => $pool->total_shares,
            'fee_rate'       => $pool->fee_rate,
            'status'         => $pool->status,
            'message'        => 'Pool information retrieved',
        ]);
    }

    private function getPoolMetrics(array $parameters): ToolExecutionResult
    {
        if (! isset($parameters['pool_id'])) {
            return ToolExecutionResult::failure('Pool ID is required');
        }

        try {
            $metrics = $this->liquidityPoolService->getPoolMetrics($parameters['pool_id']);

            return ToolExecutionResult::success([
                'pool_id'      => $parameters['pool_id'],
                'tvl'          => $metrics['tvl'] ?? '0',
                'apy'          => $metrics['apy'] ?? '0',
                'volume_24h'   => $metrics['volume_24h'] ?? '0',
                'fees_24h'     => $metrics['fees_24h'] ?? '0',
                'price_impact' => $metrics['price_impact'] ?? '0',
                'k_value'      => $metrics['k_value'] ?? '0',
                'message'      => 'Pool metrics retrieved',
            ]);
        } catch (\Exception $e) {
            return ToolExecutionResult::failure($e->getMessage());
        }
    }

    private function listPools(): ToolExecutionResult
    {
        $pools = $this->liquidityPoolService->getActivePools();

        $poolList = $pools->map(function ($pool) {
            return [
                'pool_id'       => $pool->pool_id,
                'pair'          => $pool->base_currency . '/' . $pool->quote_currency,
                'base_reserve'  => $pool->base_reserve,
                'quote_reserve' => $pool->quote_reserve,
                'total_shares'  => $pool->total_shares,
                'fee_rate'      => $pool->fee_rate,
                'status'        => $pool->status,
            ];
        })->toArray();

        return ToolExecutionResult::success([
            'pools'   => $poolList,
            'count'   => count($poolList),
            'message' => sprintf('Found %d active liquidity pools', count($poolList)),
        ]);
    }

    private function getMyPositions(?User $user): ToolExecutionResult
    {
        if (! $user) {
            return ToolExecutionResult::failure('User authentication required');
        }

        $positions = $this->liquidityPoolService->getProviderPositions($user->uuid);

        $positionList = $positions->map(function ($position) {
            return [
                'pool_id'          => $position->pool_id,
                'shares'           => $position->shares,
                'share_percentage' => $position->share_percentage,
                'base_currency'    => $position->pool->base_currency ?? null,
                'quote_currency'   => $position->pool->quote_currency ?? null,
                'created_at'       => $position->created_at->toIso8601String(),
            ];
        })->toArray();

        return ToolExecutionResult::success([
            'positions' => $positionList,
            'count'     => count($positionList),
            'message'   => sprintf('Found %d liquidity positions', count($positionList)),
        ]);
    }

    private function canCreatePool(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        // Check for specific role (User model has hasRole method)
        if ($user->hasRole(['admin', 'market_maker', 'liquidity_provider'])) {
            return true;
        }

        // Check for specific permission (User model has can method)
        if ($user->can('create-liquidity-pool')) {
            return true;
        }

        // Check if user has minimum requirements (e.g., KYC approved)
        if ($user->kyc_status === 'approved') {
            return true;
        }

        return false;
    }

    public function getCapabilities(): array
    {
        return [
            'read',
            'write',
            'liquidity-management',
            'defi',
            'exchange',
        ];
    }

    public function isCacheable(): bool
    {
        return true;
    }

    public function getCacheTtl(): int
    {
        return 60; // Cache for 1 minute
    }

    public function validateInput(array $parameters): bool
    {
        // Action is required
        if (! isset($parameters['action'])) {
            return false;
        }

        // Validate action
        $validActions = ['create', 'add_liquidity', 'remove_liquidity', 'get_info', 'get_metrics', 'list_pools', 'my_positions'];
        if (! in_array($parameters['action'], $validActions)) {
            return false;
        }

        // Validate amounts if provided
        if (isset($parameters['base_amount']) && ! preg_match('/^\d+(\.\d+)?$/', $parameters['base_amount'])) {
            return false;
        }

        if (isset($parameters['quote_amount']) && ! preg_match('/^\d+(\.\d+)?$/', $parameters['quote_amount'])) {
            return false;
        }

        if (isset($parameters['shares']) && ! preg_match('/^\d+(\.\d+)?$/', $parameters['shares'])) {
            return false;
        }

        return true;
    }

    public function authorize(?string $userId): bool
    {
        // All actions can be performed if user is authenticated
        // The actual authorization is done per-action in the execute method
        return true;
    }
}
