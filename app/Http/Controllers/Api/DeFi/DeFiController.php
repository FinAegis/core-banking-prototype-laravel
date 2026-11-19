<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\DeFi;

use App\Domain\CrossChain\Enums\CrossChainNetwork;
use App\Domain\DeFi\Contracts\LendingProtocolInterface;
use App\Domain\DeFi\Contracts\LiquidStakingInterface;
use App\Domain\DeFi\Enums\DeFiProtocol;
use App\Domain\DeFi\Services\DeFiPortfolioService;
use App\Domain\DeFi\Services\DeFiPositionTrackerService;
use App\Domain\DeFi\Services\SwapRouterService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;
use Throwable;

#[OA\Tag(
    name: 'DeFi',
    description: 'DEX aggregation and DeFi protocol data (integration adapters; no yield offered)'
)]
class DeFiController extends Controller
{
    public function __construct(
        private readonly SwapRouterService $swapRouter,
        private readonly DeFiPortfolioService $portfolioService,
        private readonly DeFiPositionTrackerService $positionTracker,
        private readonly LendingProtocolInterface $lendingProtocol,
        private readonly LiquidStakingInterface $stakingProtocol,
    ) {
    }

    /**
     * List supported DeFi protocols with chain availability.
     */
    #[OA\Get(
        path: '/api/v1/defi/protocols',
        operationId: 'defiProtocols',
        summary: 'List supported DeFi protocols',
        description: 'Returns all supported DeFi protocols with display names and categories.',
        tags: ['DeFi'],
        security: [['sanctum' => []]]
    )]
    #[OA\Response(
        response: 200,
        description: 'List of supported DeFi protocols',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object', properties: [
        new OA\Property(property: 'name', type: 'string', example: 'uniswap_v3'),
        new OA\Property(property: 'display', type: 'string', example: 'Uniswap V3'),
        new OA\Property(property: 'category', type: 'string', example: 'dex'),
        ])),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    public function protocols(): JsonResponse
    {
        $protocols = array_map(fn (DeFiProtocol $p) => [
            'name'     => $p->value,
            'display'  => $p->getDisplayName(),
            'category' => $p->getCategory(),
        ], DeFiProtocol::cases());

        return response()->json([
            'success' => true,
            'data'    => array_values($protocols),
        ]);
    }

    /**
     * Get multi-DEX swap quote.
     *
     * Finds the best swap route across multiple DEX protocols for the given
     * token pair on the specified chain.
     */
    #[OA\Post(
        path: '/api/v1/defi/swap/quote',
        operationId: 'defiSwapQuote',
        summary: 'Get multi-DEX swap quote',
        description: 'Finds the best swap route across multiple DEX protocols for the given token pair on the specified chain.',
        tags: ['DeFi'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['chain', 'from_token', 'to_token', 'amount'], properties: [
        new OA\Property(property: 'chain', type: 'string', enum: ['ethereum', 'polygon', 'bsc', 'bitcoin', 'solana', 'tron', 'arbitrum', 'optimism', 'base'], example: 'ethereum'),
        new OA\Property(property: 'from_token', type: 'string', maxLength: 20, example: 'USDC'),
        new OA\Property(property: 'to_token', type: 'string', maxLength: 20, example: 'ETH'),
        new OA\Property(property: 'amount', type: 'string', example: '1000.50', description: 'Decimal amount as string'),
        new OA\Property(property: 'slippage', type: 'number', format: 'float', minimum: 0.01, maximum: 5, nullable: true, example: 0.5, description: 'Slippage tolerance percentage (default 0.5)'),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'Swap quote returned',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'protocol', type: 'string', example: 'uniswap_v3'),
        new OA\Property(property: 'chain', type: 'string', example: 'ethereum'),
        new OA\Property(property: 'from_token', type: 'string', example: 'USDC'),
        new OA\Property(property: 'to_token', type: 'string', example: 'ETH'),
        new OA\Property(property: 'amount_in', type: 'string', example: '1000.50'),
        new OA\Property(property: 'amount_out', type: 'string', example: '0.425'),
        new OA\Property(property: 'price_impact', type: 'string', example: '0.12'),
        new OA\Property(property: 'fee', type: 'string', example: '3.00'),
        new OA\Property(property: 'route', type: 'array', items: new OA\Items(type: 'string')),
        ]),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Quote failed',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'error', type: 'object', properties: [
        new OA\Property(property: 'code', type: 'string', example: 'ERR_DEFI_001'),
        new OA\Property(property: 'message', type: 'string', example: 'No route found for token pair'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    public function swapQuote(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'chain'      => ['required', 'string', Rule::in(array_column(CrossChainNetwork::cases(), 'value'))],
            'from_token' => 'required|string|max:20',
            'to_token'   => 'required|string|max:20',
            'amount'     => 'required|string|regex:/^\d+(\.\d+)?$/',
            'slippage'   => 'nullable|numeric|min:0.01|max:5',
        ]);

        try {
            $chain = CrossChainNetwork::from($validated['chain']);
            $slippage = (float) ($validated['slippage'] ?? 0.5);

            $quote = $this->swapRouter->findBestRoute(
                $chain,
                $validated['from_token'],
                $validated['to_token'],
                $validated['amount'],
                $slippage,
            );

            return response()->json([
                'success' => true,
                'data'    => $quote->toArray(),
            ]);
        } catch (Throwable $e) {
            Log::error('DeFi swap quote failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'ERR_DEFI_001',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Execute swap via best route.
     *
     * Finds the best route and executes the token swap on-chain via the
     * optimal DEX protocol.
     */
    #[OA\Post(
        path: '/api/v1/defi/swap/execute',
        operationId: 'defiSwapExecute',
        summary: 'Execute swap via best route',
        description: 'Finds the best route and executes the token swap on-chain via the optimal DEX protocol.',
        tags: ['DeFi'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['chain', 'from_token', 'to_token', 'amount', 'wallet_address'], properties: [
        new OA\Property(property: 'chain', type: 'string', enum: ['ethereum', 'polygon', 'bsc', 'bitcoin', 'solana', 'tron', 'arbitrum', 'optimism', 'base'], example: 'ethereum'),
        new OA\Property(property: 'from_token', type: 'string', maxLength: 20, example: 'USDC'),
        new OA\Property(property: 'to_token', type: 'string', maxLength: 20, example: 'ETH'),
        new OA\Property(property: 'amount', type: 'string', example: '1000.50', description: 'Decimal amount as string'),
        new OA\Property(property: 'wallet_address', type: 'string', maxLength: 100, example: '0x742d35Cc6634C0532925a3b844Bc454e4438f44e'),
        new OA\Property(property: 'slippage', type: 'number', format: 'float', minimum: 0.01, maximum: 5, nullable: true, example: 0.5, description: 'Slippage tolerance percentage (default 0.5)'),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'Swap executed successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'tx_hash', type: 'string', example: '0xabc123...'),
        new OA\Property(property: 'protocol', type: 'string', example: 'uniswap_v3'),
        new OA\Property(property: 'chain', type: 'string', example: 'ethereum'),
        new OA\Property(property: 'from_token', type: 'string', example: 'USDC'),
        new OA\Property(property: 'to_token', type: 'string', example: 'ETH'),
        new OA\Property(property: 'amount_in', type: 'string', example: '1000.50'),
        new OA\Property(property: 'amount_out', type: 'string', example: '0.425'),
        new OA\Property(property: 'status', type: 'string', example: 'confirmed'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Swap execution failed',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'error', type: 'object', properties: [
        new OA\Property(property: 'code', type: 'string', example: 'ERR_DEFI_002'),
        new OA\Property(property: 'message', type: 'string', example: 'Swap execution failed'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    public function swapExecute(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'chain'          => ['required', 'string', Rule::in(array_column(CrossChainNetwork::cases(), 'value'))],
            'from_token'     => 'required|string|max:20',
            'to_token'       => 'required|string|max:20',
            'amount'         => 'required|string|regex:/^\d+(\.\d+)?$/',
            'wallet_address' => 'required|string|max:100',
            'slippage'       => 'nullable|numeric|min:0.01|max:5',
        ]);

        try {
            $chain = CrossChainNetwork::from($validated['chain']);
            $slippage = (float) ($validated['slippage'] ?? 0.5);

            $quote = $this->swapRouter->findBestRoute(
                $chain,
                $validated['from_token'],
                $validated['to_token'],
                $validated['amount'],
                $slippage,
            );

            $result = $this->swapRouter->executeSwap($quote, $validated['wallet_address']);

            return response()->json([
                'success' => true,
                'data'    => $result,
            ]);
        } catch (Throwable $e) {
            Log::error('DeFi swap execution failed', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'ERR_DEFI_002',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Get lending markets.
     *
     * Returns available lending markets for the specified chain, including
     * supply/borrow rates and available liquidity.
     */
    #[OA\Get(
        path: '/api/v1/defi/lending/markets',
        operationId: 'defiLendingMarkets',
        summary: 'Get lending markets',
        description: 'Returns available lending markets for the specified chain, including supply/borrow rates and available liquidity.',
        tags: ['DeFi'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'chain', in: 'query', required: false, description: 'Blockchain network (defaults to ethereum)', schema: new OA\Schema(type: 'string', enum: ['ethereum', 'polygon', 'bsc', 'bitcoin', 'solana', 'tron', 'arbitrum', 'optimism', 'base'], default: 'ethereum')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Lending markets returned',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object', properties: [
        new OA\Property(property: 'asset', type: 'string', example: 'USDC'),
        new OA\Property(property: 'supply_apy', type: 'number', format: 'float', example: 3.45),
        new OA\Property(property: 'borrow_apy', type: 'number', format: 'float', example: 5.12),
        new OA\Property(property: 'total_supply', type: 'string', example: '1250000000.00'),
        new OA\Property(property: 'total_borrow', type: 'string', example: '890000000.00'),
        new OA\Property(property: 'utilization', type: 'number', format: 'float', example: 71.2),
        new OA\Property(property: 'protocol', type: 'string', example: 'aave_v3'),
        ])),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Failed to fetch lending markets',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'error', type: 'object', properties: [
        new OA\Property(property: 'code', type: 'string', example: 'ERR_DEFI_003'),
        new OA\Property(property: 'message', type: 'string', example: 'Failed to fetch lending markets'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 422,
        description: 'Invalid chain value',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'error', type: 'object', properties: [
        new OA\Property(property: 'code', type: 'string', example: 'ERR_DEFI_003'),
        new OA\Property(property: 'message', type: 'string', example: 'Invalid chain value.'),
        ]),
        ])
    )]
    public function lendingMarkets(Request $request): JsonResponse
    {
        $chain = $request->query('chain', 'ethereum');

        try {
            $network = CrossChainNetwork::tryFrom((string) $chain);
            if ($network === null) {
                return response()->json([
                    'success' => false,
                    'error'   => [
                        'code'    => 'ERR_DEFI_003',
                        'message' => 'Invalid chain value.',
                    ],
                ], 422);
            }

            $markets = $this->lendingProtocol->getMarkets($network);

            return response()->json([
                'success' => true,
                'data'    => $markets,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'ERR_DEFI_003',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * Get full DeFi portfolio across chains.
     *
     * Returns an aggregated DeFi portfolio summary for the given wallet address
     * spanning all supported chains and protocols.
     */
    #[OA\Get(
        path: '/api/v1/defi/portfolio',
        operationId: 'defiPortfolio',
        summary: 'Get DeFi portfolio across chains',
        description: 'Returns an aggregated DeFi portfolio summary for the given wallet address spanning all supported chains and protocols.',
        tags: ['DeFi'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'wallet_address', in: 'query', required: true, description: 'Wallet address to retrieve portfolio for', schema: new OA\Schema(type: 'string', maxLength: 100, example: '0x742d35Cc6634C0532925a3b844Bc454e4438f44e')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Portfolio summary returned',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'total_value_usd', type: 'string', example: '125430.50'),
        new OA\Property(property: 'positions', type: 'array', items: new OA\Items(type: 'object', properties: [
        new OA\Property(property: 'protocol', type: 'string', example: 'aave_v3'),
        new OA\Property(property: 'chain', type: 'string', example: 'ethereum'),
        new OA\Property(property: 'type', type: 'string', example: 'lending'),
        new OA\Property(property: 'value_usd', type: 'string', example: '50000.00'),
        ])),
        new OA\Property(property: 'chains', type: 'array', items: new OA\Items(type: 'object', properties: [
        new OA\Property(property: 'chain', type: 'string', example: 'ethereum'),
        new OA\Property(property: 'value_usd', type: 'string', example: '100000.00'),
        ])),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    public function portfolio(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'wallet_address' => 'required|string|max:100',
        ]);

        $summary = $this->portfolioService->getPortfolioSummary($validated['wallet_address']);

        return response()->json([
            'success' => true,
            'data'    => $summary,
        ]);
    }

    /**
     * Get active DeFi positions with optional filters.
     *
     * Returns all active DeFi positions for a wallet address. Results can be
     * filtered by chain and/or protocol.
     */
    #[OA\Get(
        path: '/api/v1/defi/positions',
        operationId: 'defiPositions',
        summary: 'Get active DeFi positions',
        description: 'Returns all active DeFi positions for a wallet address with optional chain and protocol filters.',
        tags: ['DeFi'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'wallet_address', in: 'query', required: true, description: 'Wallet address to retrieve positions for', schema: new OA\Schema(type: 'string', maxLength: 100, example: '0x742d35Cc6634C0532925a3b844Bc454e4438f44e')),
        new OA\Parameter(name: 'chain', in: 'query', required: false, description: 'Filter by blockchain network', schema: new OA\Schema(type: 'string', enum: ['ethereum', 'polygon', 'bsc', 'bitcoin', 'solana', 'tron', 'arbitrum', 'optimism', 'base'])),
        new OA\Parameter(name: 'protocol', in: 'query', required: false, description: 'Filter by DeFi protocol', schema: new OA\Schema(type: 'string', enum: ['uniswap_v3', 'aave_v3', 'curve', 'lido', 'demo'])),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Active positions returned',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object', properties: [
        new OA\Property(property: 'id', type: 'string', example: 'pos_abc123'),
        new OA\Property(property: 'protocol', type: 'string', example: 'aave_v3'),
        new OA\Property(property: 'chain', type: 'string', example: 'ethereum'),
        new OA\Property(property: 'type', type: 'string', example: 'lending'),
        new OA\Property(property: 'asset', type: 'string', example: 'USDC'),
        new OA\Property(property: 'amount', type: 'string', example: '10000.00'),
        new OA\Property(property: 'value_usd', type: 'string', example: '10000.00'),
        new OA\Property(property: 'apy', type: 'number', format: 'float', example: 3.45),
        new OA\Property(property: 'status', type: 'string', example: 'active'),
        ])),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    public function positions(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'wallet_address' => 'required|string|max:100',
            'chain'          => ['nullable', 'string', Rule::in(array_column(CrossChainNetwork::cases(), 'value'))],
            'protocol'       => ['nullable', 'string', Rule::in(array_column(DeFiProtocol::cases(), 'value'))],
        ]);

        $chainFilter = isset($validated['chain']) ? CrossChainNetwork::from($validated['chain']) : null;
        $protocolFilter = isset($validated['protocol']) ? DeFiProtocol::from($validated['protocol']) : null;

        $positions = $this->positionTracker->getActivePositions(
            $validated['wallet_address'],
            $chainFilter,
            $protocolFilter,
        );

        return response()->json([
            'success' => true,
            'data'    => array_map(fn ($p) => $p->toArray(), $positions),
        ]);
    }

    /**
     * Get liquid staking info and user positions.
     *
     * Returns staking rate data (informational) and the user's staked balance for the specified chain
     * via the Lido liquid staking protocol.
     */
    #[OA\Post(
        path: '/api/v1/defi/staking/stake',
        operationId: 'defiStaking',
        summary: 'Get staking info and positions',
        description: 'Returns staking rate data (informational) and the user\'s staked balance for the specified chain via the Lido liquid staking protocol.',
        tags: ['DeFi'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['chain', 'wallet_address'], properties: [
        new OA\Property(property: 'chain', type: 'string', enum: ['ethereum', 'polygon', 'bsc', 'bitcoin', 'solana', 'tron', 'arbitrum', 'optimism', 'base'], example: 'ethereum'),
        new OA\Property(property: 'wallet_address', type: 'string', maxLength: 100, example: '0x742d35Cc6634C0532925a3b844Bc454e4438f44e'),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'Staking info returned',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'protocol', type: 'string', example: 'lido'),
        new OA\Property(property: 'staking_apy', type: 'number', format: 'float', example: 4.85),
        new OA\Property(property: 'staked_balance', type: 'string', example: '32.50'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Staking info retrieval failed',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'error', type: 'object', properties: [
        new OA\Property(property: 'code', type: 'string', example: 'ERR_DEFI_004'),
        new OA\Property(property: 'message', type: 'string', example: 'Failed to retrieve staking info'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    public function staking(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'chain'          => ['required', 'string', Rule::in(array_column(CrossChainNetwork::cases(), 'value'))],
            'wallet_address' => 'required|string|max:100',
        ]);

        try {
            $network = CrossChainNetwork::from($validated['chain']);

            $data = [
                'protocol'       => 'lido',
                'staking_apy'    => $this->stakingProtocol->getStakingAPY($network),
                'staked_balance' => $this->stakingProtocol->getStakedBalance($network, $validated['wallet_address']),
            ];

            return response()->json([
                'success' => true,
                'data'    => $data,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'ERR_DEFI_004',
                    'message' => $e->getMessage(),
                ],
            ], 400);
        }
    }

    /**
     * List DeFi protocol rate data across chains (informational).
     *
     * Returns DeFi protocol rate data across all supported chains and
     * DeFi protocols for the given wallet address (informational; no yield offered).
     */
    #[OA\Get(
        path: '/api/v1/defi/yield/best',
        operationId: 'defiYieldBest',
        summary: 'List DeFi protocol rate data (informational)',
        description: 'Returns DeFi protocol rate data across all supported chains and DeFi protocols for the given wallet address (informational; no yield offered).',
        tags: ['DeFi'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'wallet_address', in: 'query', required: true, description: 'Wallet address to list protocol rate data for', schema: new OA\Schema(type: 'string', maxLength: 100, example: '0x742d35Cc6634C0532925a3b844Bc454e4438f44e')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Protocol rate data returned',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object', properties: [
        new OA\Property(property: 'protocol', type: 'string', example: 'aave_v3'),
        new OA\Property(property: 'chain', type: 'string', example: 'ethereum'),
        new OA\Property(property: 'asset', type: 'string', example: 'USDC'),
        new OA\Property(property: 'type', type: 'string', example: 'lending'),
        new OA\Property(property: 'apy', type: 'number', format: 'float', example: 5.25),
        new OA\Property(property: 'tvl', type: 'string', example: '1250000000.00'),
        new OA\Property(property: 'risk_level', type: 'string', example: 'low'),
        ])),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    public function yield(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'wallet_address' => 'required|string|max:100',
        ]);

        $opportunities = $this->portfolioService->getYieldOpportunities($validated['wallet_address']);

        return response()->json([
            'success' => true,
            'data'    => $opportunities,
        ]);
    }
}
