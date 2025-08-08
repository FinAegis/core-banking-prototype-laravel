<?php

namespace App\Http\Controllers\Api;

use App\Domain\Exchange\Services\LiquidityPoolService;
use App\Domain\Exchange\ValueObjects\LiquidityAdditionInput;
use App\Domain\Exchange\ValueObjects\LiquidityRemovalInput;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(
 *     name="Liquidity Pool",
 *     description="Liquidity pool management endpoints"
 * )
 */
class LiquidityPoolController extends Controller
{
    public function __construct(
        private readonly LiquidityPoolService $liquidityService
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/liquidity/pools",
     *     tags={"Liquidity Pool"},
     *     summary="Get all active liquidity pools",
     *
     * @OA\Response(
     *         response=200,
     *         description="List of active pools",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="pools",          type="array",
     *
     * @OA\Items(
     *
     * @OA\Property(property="pool_id",        type="string"),
     * @OA\Property(property="base_currency",  type="string"),
     * @OA\Property(property="quote_currency", type="string"),
     * @OA\Property(property="tvl",            type="string"),
     * @OA\Property(property="apy",            type="string")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $pools = $this->liquidityService->getActivePools();

        $poolData = $pools->map(
            function ($pool) {
                $metrics = $this->liquidityService->getPoolMetrics($pool->pool_id);

                return [
                    'pool_id'        => $pool->pool_id,
                    'base_currency'  => $pool->base_currency,
                    'quote_currency' => $pool->quote_currency,
                    'base_reserve'   => $pool->base_reserve,
                    'quote_reserve'  => $pool->quote_reserve,
                    'fee_rate'       => $pool->fee_rate,
                    'tvl'            => $metrics['tvl'],
                    'apy'            => $metrics['apy'],
                    'volume_24h'     => $pool->volume_24h,
                ];
            }
        );

        return response()->json(
            [
                'pools' => $poolData,
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/liquidity/pools/{poolId}",
     *     tags={"Liquidity Pool"},
     *     summary="Get pool details",
     *
     * @OA\Parameter(
     *         name="poolId",
     *         in="path",
     *         required=true,
     *
     * @OA\Schema(type="string", format="uuid")
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Pool details with metrics"
     *     )
     * )
     */
    public function show(string $poolId): JsonResponse
    {
        $pool = $this->liquidityService->getPool($poolId);

        if (! $pool) {
            return response()->json(['error' => 'Pool not found'], 404);
        }

        $metrics = $this->liquidityService->getPoolMetrics($poolId);

        return response()->json(
            [
                'pool' => array_merge($pool->toArray(), ['metrics' => $metrics]),
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/liquidity/pools",
     *     tags={"Liquidity Pool"},
     *     summary="Create a new liquidity pool",
     *     security={{"bearerAuth":{}}},
     *
     * @OA\RequestBody(
     *         required=true,
     *
     * @OA\JsonContent(
     *             required={"base_currency", "quote_currency"},
     *
     * @OA\Property(property="base_currency",  type="string", example="BTC"),
     * @OA\Property(property="quote_currency", type="string", example="EUR"),
     * @OA\Property(property="fee_rate",       type="string", example="0.003")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=201,
     *         description="Pool created",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="pool_id",        type="string")
     *         )
     *     )
     * )
     */
    public function create(Request $request): JsonResponse
    {
        $this->middleware('auth:sanctum');

        $validated = $request->validate(
            [
                'base_currency'  => 'required|string|size:3',
                'quote_currency' => 'required|string|size:3',
                'fee_rate'       => 'nullable|numeric|between:0.0001,0.01',
            ]
        );

        try {
            $poolId = $this->liquidityService->createPool(
                $validated['base_currency'],
                $validated['quote_currency'],
                $validated['fee_rate'] ?? '0.003'
            );

            return response()->json(
                [
                    'pool_id' => $poolId,
                    'message' => 'Liquidity pool created successfully',
                ],
                201
            );
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/liquidity/add",
     *     tags={"Liquidity Pool"},
     *     summary="Add liquidity to a pool",
     *     security={{"bearerAuth":{}}},
     *
     * @OA\RequestBody(
     *         required=true,
     *
     * @OA\JsonContent(
     *             required={"pool_id", "base_amount", "quote_amount"},
     *
     * @OA\Property(property="pool_id",      type="string", format="uuid"),
     * @OA\Property(property="base_amount",  type="string", example="0.1"),
     * @OA\Property(property="quote_amount", type="string", example="4800"),
     * @OA\Property(property="min_shares",   type="string", example="0")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Liquidity added"
     *     )
     * )
     */
    public function addLiquidity(Request $request): JsonResponse
    {
        $this->middleware('auth:sanctum');

        $validated = $request->validate(
            [
                'pool_id'      => 'required|uuid',
                'base_amount'  => 'required|numeric|min:0.00000001',
                'quote_amount' => 'required|numeric|min:0.00000001',
                'min_shares'   => 'nullable|numeric|min:0',
            ]
        );

        $pool = $this->liquidityService->getPool($validated['pool_id']);
        if (! $pool) {
            return response()->json(['error' => 'Pool not found'], 404);
        }

        try {
            $result = $this->liquidityService->addLiquidity(
                new LiquidityAdditionInput(
                    poolId: $validated['pool_id'],
                    providerId: $request->user()->account->id,
                    baseCurrency: $pool->base_currency,
                    quoteCurrency: $pool->quote_currency,
                    baseAmount: $validated['base_amount'],
                    quoteAmount: $validated['quote_amount'],
                    minShares: $validated['min_shares'] ?? '0'
                )
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/liquidity/remove",
     *     tags={"Liquidity Pool"},
     *     summary="Remove liquidity from a pool",
     *     security={{"bearerAuth":{}}},
     *
     * @OA\RequestBody(
     *         required=true,
     *
     * @OA\JsonContent(
     *             required={"pool_id", "shares"},
     *
     * @OA\Property(property="pool_id",          type="string", format="uuid"),
     * @OA\Property(property="shares",           type="string", example="100"),
     * @OA\Property(property="min_base_amount",  type="string", example="0"),
     * @OA\Property(property="min_quote_amount", type="string", example="0")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Liquidity removed"
     *     )
     * )
     */
    public function removeLiquidity(Request $request): JsonResponse
    {
        $this->middleware('auth:sanctum');

        $validated = $request->validate(
            [
                'pool_id'          => 'required|uuid',
                'shares'           => 'required|numeric|min:0.00000001',
                'min_base_amount'  => 'nullable|numeric|min:0',
                'min_quote_amount' => 'nullable|numeric|min:0',
            ]
        );

        try {
            $result = $this->liquidityService->removeLiquidity(
                new LiquidityRemovalInput(
                    poolId: $validated['pool_id'],
                    providerId: $request->user()->account->id,
                    shares: $validated['shares'],
                    minBaseAmount: $validated['min_base_amount'] ?? '0',
                    minQuoteAmount: $validated['min_quote_amount'] ?? '0'
                )
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/liquidity/swap",
     *     tags={"Liquidity Pool"},
     *     summary="Execute a swap through liquidity pool",
     *     security={{"bearerAuth":{}}},
     *
     * @OA\RequestBody(
     *         required=true,
     *
     * @OA\JsonContent(
     *             required={"pool_id", "input_currency", "input_amount"},
     *
     * @OA\Property(property="pool_id",           type="string", format="uuid"),
     * @OA\Property(property="input_currency",    type="string", example="BTC"),
     * @OA\Property(property="input_amount",      type="string", example="0.1"),
     * @OA\Property(property="min_output_amount", type="string", example="4700")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Swap executed"
     *     )
     * )
     */
    public function swap(Request $request): JsonResponse
    {
        $this->middleware('auth:sanctum');

        $validated = $request->validate(
            [
                'pool_id'           => 'required|uuid',
                'input_currency'    => 'required|string|size:3',
                'input_amount'      => 'required|numeric|min:0.00000001',
                'min_output_amount' => 'nullable|numeric|min:0',
            ]
        );

        try {
            $result = $this->liquidityService->swap(
                poolId: $validated['pool_id'],
                accountId: $request->user()->account->id,
                inputCurrency: $validated['input_currency'],
                inputAmount: $validated['input_amount'],
                minOutputAmount: $validated['min_output_amount'] ?? '0'
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/liquidity/positions",
     *     tags={"Liquidity Pool"},
     *     summary="Get user's liquidity positions",
     *     security={{"bearerAuth":{}}},
     *
     * @OA\Response(
     *         response=200,
     *         description="User's positions"
     *     )
     * )
     */
    public function positions(Request $request): JsonResponse
    {
        $this->middleware('auth:sanctum');

        $positions = $this->liquidityService->getProviderPositions($request->user()->account->id);

        $positionData = $positions->map(
            function ($position) {
                return [
                    'pool_id'               => $position->pool_id,
                    'base_currency'         => $position->pool->base_currency,
                    'quote_currency'        => $position->pool->quote_currency,
                    'shares'                => $position->shares,
                    'share_percentage'      => $position->share_percentage,
                    'current_value'         => $position->current_value,
                    'pending_rewards'       => $position->pending_rewards,
                    'total_rewards_claimed' => $position->total_rewards_claimed,
                ];
            }
        );

        return response()->json(
            [
                'positions' => $positionData,
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/liquidity/claim-rewards",
     *     tags={"Liquidity Pool"},
     *     summary="Claim pending rewards",
     *     security={{"bearerAuth":{}}},
     *
     * @OA\RequestBody(
     *         required=true,
     *
     * @OA\JsonContent(
     *             required={"pool_id"},
     *
     * @OA\Property(property="pool_id", type="string", format="uuid")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Rewards claimed"
     *     )
     * )
     */
    public function claimRewards(Request $request): JsonResponse
    {
        $this->middleware('auth:sanctum');

        $validated = $request->validate(
            [
                'pool_id' => 'required|uuid',
            ]
        );

        try {
            $rewards = $this->liquidityService->claimRewards(
                $validated['pool_id'],
                $request->user()->account->id
            );

            return response()->json(
                [
                    'success' => true,
                    'rewards' => $rewards,
                ]
            );
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/liquidity/il-protection/{positionId}",
     *     tags={"Liquidity Pool"},
     *     summary="Calculate impermanent loss for a position",
     *     security={{"bearerAuth":{}}},
     * @OA\Parameter(
     *         name="positionId",
     *         in="path",
     *         required=true,
     * @OA\Schema(type="integer")
     *     ),
     * @OA\Response(
     *         response=200,
     *         description="IL calculation details",
     * @OA\JsonContent(
     * @OA\Property(property="position_id",        type="integer"),
     * @OA\Property(property="entry_price",        type="string"),
     * @OA\Property(property="current_price",      type="string"),
     * @OA\Property(property="impermanent_loss",   type="string"),
     * @OA\Property(property="impermanent_loss_percent", type="string"),
     * @OA\Property(property="is_protected",       type="boolean")
     *         )
     *     )
     * )
     */
    public function calculateImpermanentLoss(string $positionId, Request $request): JsonResponse
    {
        $this->middleware('auth:sanctum');

        try {
            $ilData = $this->liquidityService->calculateImpermanentLoss($positionId);

            return response()->json($ilData);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/liquidity/il-protection/enable",
     *     tags={"Liquidity Pool"},
     *     summary="Enable IL protection for a pool",
     *     security={{"bearerAuth":{}}},
     * @OA\RequestBody(
     *         required=true,
     * @OA\JsonContent(
     *             required={"pool_id"},
     * @OA\Property(property="pool_id",               type="string", format="uuid"),
     * @OA\Property(property="protection_threshold",  type="string", example="0.02"),
     * @OA\Property(property="max_coverage",          type="string", example="0.80"),
     * @OA\Property(property="min_holding_hours",     type="integer", example=168),
     * @OA\Property(property="fund_size",             type="string", example="100000")
     *         )
     *     ),
     * @OA\Response(
     *         response=200,
     *         description="IL protection enabled"
     *     )
     * )
     */
    public function enableImpermanentLossProtection(Request $request): JsonResponse
    {
        $this->middleware('auth:sanctum');

        $validated = $request->validate([
            'pool_id'              => 'required|uuid',
            'protection_threshold' => 'nullable|numeric|min:0|max:0.1',
            'max_coverage'         => 'nullable|numeric|min:0|max:1',
            'min_holding_hours'    => 'nullable|integer|min:24',
            'fund_size'            => 'nullable|numeric|min:0',
        ]);

        try {
            $this->liquidityService->enableImpermanentLossProtection(
                poolId: $validated['pool_id'],
                protectionThreshold: $validated['protection_threshold'] ?? '0.02',
                maxCoverage: $validated['max_coverage'] ?? '0.80',
                minHoldingPeriodHours: $validated['min_holding_hours'] ?? 168,
                fundSize: $validated['fund_size'] ?? '0'
            );

            return response()->json([
                'success' => true,
                'message' => 'IL protection enabled for pool',
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/liquidity/il-protection/process-claims",
     *     tags={"Liquidity Pool"},
     *     summary="Process IL protection claims for a pool",
     *     security={{"bearerAuth":{}}},
     * @OA\RequestBody(
     *         required=true,
     * @OA\JsonContent(
     *             required={"pool_id"},
     * @OA\Property(property="pool_id", type="string", format="uuid")
     *         )
     *     ),
     * @OA\Response(
     *         response=200,
     *         description="Claims processed",
     * @OA\JsonContent(
     * @OA\Property(property="success", type="boolean"),
     * @OA\Property(property="claims",  type="array",
     * @OA\Items(
     * @OA\Property(property="provider_id",    type="string"),
     * @OA\Property(property="compensation",   type="string"),
     * @OA\Property(property="compensation_currency", type="string")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function processImpermanentLossProtectionClaims(Request $request): JsonResponse
    {
        $this->middleware('auth:sanctum');

        $validated = $request->validate([
            'pool_id' => 'required|uuid',
        ]);

        try {
            $claims = $this->liquidityService->processImpermanentLossProtectionClaims($validated['pool_id']);

            return response()->json([
                'success'           => true,
                'claims'            => $claims,
                'total_compensated' => $claims->sum('compensation'),
            ]);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/liquidity/il-protection/fund-requirements/{poolId}",
     *     tags={"Liquidity Pool"},
     *     summary="Get IL protection fund requirements",
     *     security={{"bearerAuth":{}}},
     * @OA\Parameter(
     *         name="poolId",
     *         in="path",
     *         required=true,
     * @OA\Schema(type="string", format="uuid")
     *     ),
     * @OA\Response(
     *         response=200,
     *         description="Fund requirements",
     * @OA\JsonContent(
     * @OA\Property(property="pool_id",                  type="string"),
     * @OA\Property(property="total_liquidity_value",    type="string"),
     * @OA\Property(property="protected_value",          type="string"),
     * @OA\Property(property="max_potential_compensation", type="string"),
     * @OA\Property(property="recommended_fund_size",    type="string")
     *         )
     *     )
     * )
     */
    public function getImpermanentLossProtectionFundRequirements(string $poolId): JsonResponse
    {
        try {
            $requirements = $this->liquidityService->getImpermanentLossProtectionFundRequirements($poolId);

            return response()->json($requirements);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/liquidity/analytics/{poolId}",
     *     tags={"Liquidity Pool"},
     *     summary="Get pool analytics and performance metrics",
     * @OA\Parameter(
     *         name="poolId",
     *         in="path",
     *         required=true,
     * @OA\Schema(type="string", format="uuid")
     *     ),
     * @OA\Response(
     *         response=200,
     *         description="Pool analytics",
     * @OA\JsonContent(
     * @OA\Property(property="pool_id",        type="string"),
     * @OA\Property(property="tvl",            type="string"),
     * @OA\Property(property="volume_24h",     type="string"),
     * @OA\Property(property="fees_24h",       type="string"),
     * @OA\Property(property="apy",            type="string"),
     * @OA\Property(property="provider_count", type="integer"),
     * @OA\Property(property="price_history",  type="array",
     * @OA\Items(
     * @OA\Property(property="timestamp", type="string"),
     * @OA\Property(property="price",     type="string")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getPoolAnalytics(string $poolId): JsonResponse
    {
        try {
            $metrics = $this->liquidityService->getPoolMetrics($poolId);

            // Add historical data (mock for now, would come from time-series DB)
            $metrics['price_history'] = [
                ['timestamp' => now()->subDays(7)->toIso8601String(), 'price' => $metrics['spot_price']],
                ['timestamp' => now()->subDays(6)->toIso8601String(), 'price' => $metrics['spot_price']],
                ['timestamp' => now()->subDays(5)->toIso8601String(), 'price' => $metrics['spot_price']],
                ['timestamp' => now()->subDays(4)->toIso8601String(), 'price' => $metrics['spot_price']],
                ['timestamp' => now()->subDays(3)->toIso8601String(), 'price' => $metrics['spot_price']],
                ['timestamp' => now()->subDays(2)->toIso8601String(), 'price' => $metrics['spot_price']],
                ['timestamp' => now()->subDays(1)->toIso8601String(), 'price' => $metrics['spot_price']],
                ['timestamp' => now()->toIso8601String(), 'price' => $metrics['spot_price']],
            ];

            return response()->json($metrics);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 400);
        }
    }
}
