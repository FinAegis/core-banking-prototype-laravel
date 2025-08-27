<?php

namespace App\Http\Controllers\Api;

use App\Domain\Basket\Models\BasketAsset;
use App\Domain\Basket\Services\BasketRebalancingService;
use App\Domain\Basket\Services\BasketValueCalculationService;
use App\Http\Controllers\Controller;
use DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Baskets",
 *     description="Basket asset management endpoints"
 * )
 */
class BasketController extends Controller
{
    public function __construct(
        private readonly BasketValueCalculationService $valueCalculationService,
        private readonly BasketRebalancingService $rebalancingService
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/v2/baskets",
     *     operationId="listBaskets",
     *     tags={"Baskets"},
     *     summary="List all basket assets",
     *
     * @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter by basket type",
     *         required=false,
     *
     * @OA\Schema(type="string",                         enum={"fixed", "dynamic"})
     *     ),
     *
     * @OA\Parameter(
     *         name="active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *
     * @OA\Schema(type="boolean")
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="List of basket assets",
     *
     * @OA\JsonContent(
     *             type="array",
     *
     * @OA\Items(ref="#/components/schemas/BasketAsset")
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = BasketAsset::with([
            'components.asset',
            'latestValue', // This is already defined as a hasOne relationship
        ]);

        if ($request->has('type')) {
            $query->where('type', $request->input('type'));
        }

        if ($request->has('active')) {
            $query->where('is_active', $request->boolean('active'));
        }

        $baskets = $query->get()->map(
            function ($basket) {
                $latestValue = $basket->latestValue;

                return [
                    'code'                => $basket->code,
                    'name'                => $basket->name,
                    'description'         => $basket->description,
                    'type'                => $basket->type,
                    'rebalance_frequency' => $basket->rebalance_frequency,
                    'is_active'           => $basket->is_active,
                    'latest_value'        => $latestValue ? [
                        'value'         => $latestValue->value,
                        'calculated_at' => $latestValue->calculated_at->toISOString(),
                    ] : null,
                    'components' => $basket->components->map(
                        function ($component) {
                            return [
                                'asset_code' => $component->asset_code,
                                'asset_name' => $component->asset->name ?? $component->asset_code,
                                'weight'     => $component->weight,
                                'min_weight' => $component->min_weight,
                                'max_weight' => $component->max_weight,
                            ];
                        }
                    ),
                ];
            }
        );

        return response()->json($baskets);
    }

    /**
     * @OA\Get(
     *     path="/api/v2/baskets/{code}",
     *     operationId="getBasket",
     *     tags={"Baskets"},
     *     summary="Get basket details",
     *
     * @OA\Parameter(
     *         name="code",
     *         in="path",
     *         description="Basket code",
     *         required=true,
     *
     * @OA\Schema(type="string")
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Basket details",
     *
     * @OA\JsonContent(ref="#/components/schemas/BasketAsset")
     *     ),
     *
     * @OA\Response(
     *         response=404,
     *         description="Basket not found"
     *     )
     * )
     */
    public function show(string $code): JsonResponse
    {
        $basket = BasketAsset::with(
            ['components.asset', 'values' => function ($query) {
                $query->latest('calculated_at')->limit(10);
            }]
        )->where('code', $code)->firstOrFail();

        return response()->json(
            [
                'code'                => $basket->code,
                'name'                => $basket->name,
                'description'         => $basket->description,
                'type'                => $basket->type,
                'rebalance_frequency' => $basket->rebalance_frequency,
                'last_rebalanced_at'  => $basket->last_rebalanced_at?->toISOString(),
                'is_active'           => $basket->is_active,
                'created_at'          => $basket->created_at->toISOString(),
                'components'          => $basket->components->map(
                    function ($component) {
                        return [
                            'asset_code' => $component->asset_code,
                            'asset_name' => $component->asset->name ?? $component->asset_code,
                            'weight'     => $component->weight,
                            'min_weight' => $component->min_weight,
                            'max_weight' => $component->max_weight,
                            'is_active'  => $component->is_active,
                        ];
                    }
                ),
                'recent_values' => $basket->values->map(
                    function ($value) {
                        return [
                            'value'         => $value->value,
                            'calculated_at' => $value->calculated_at->toISOString(),
                        ];
                    }
                ),
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v2/baskets",
     *     operationId="createBasket",
     *     tags={"Baskets"},
     *     summary="Create a new basket",
     *     security={{"bearerAuth":{}}},
     *
     * @OA\RequestBody(
     *         required=true,
     *
     * @OA\JsonContent(
     *             required={"code", "name", "components"},
     *
     * @OA\Property(property="code",                           type="string", example="STABLE_BASKET"),
     * @OA\Property(property="name",                           type="string", example="Stable Currency Basket"),
     * @OA\Property(property="description",                    type="string"),
     * @OA\Property(property="type",                           type="string", enum={"fixed", "dynamic"}, default="fixed"),
     * @OA\Property(property="rebalance_frequency",            type="string", enum={"daily", "weekly", "monthly", "quarterly", "never"}, default="never"),
     * @OA\Property(
     *                 property="components",
     *                 type="array",
     *
     * @OA\Items(
     *                     type="object",
     *                     required={"asset_code", "weight"},
     *
     * @OA\Property(property="asset_code",                     type="string", example="USD"),
     * @OA\Property(property="weight",                         type="number", format="float", example=40.0),
     * @OA\Property(property="min_weight",                     type="number", format="float", example=35.0),
     * @OA\Property(property="max_weight",                     type="number", format="float", example=45.0)
     *                 )
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=201,
     *         description="Basket created successfully",
     *
     * @OA\JsonContent(ref="#/components/schemas/BasketAsset")
     *     ),
     *
     * @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'code'                    => 'required|string|max:20|unique:basket_assets,code',
                'name'                    => 'required|string|max:100',
                'description'             => 'nullable|string',
                'type'                    => ['required', Rule::in(['fixed', 'dynamic'])],
                'rebalance_frequency'     => ['required', Rule::in(['daily', 'weekly', 'monthly', 'quarterly', 'never'])],
                'components'              => 'required|array|min:2',
                'components.*.asset_code' => 'required|string|exists:assets,code',
                'components.*.weight'     => 'required|numeric|min:0|max:100',
                'components.*.min_weight' => 'nullable|numeric|min:0|max:100',
                'components.*.max_weight' => 'nullable|numeric|min:0|max:100',
            ]
        );

        // Validate total weight equals 100
        $totalWeight = collect($validated['components'])->sum('weight');
        if (abs($totalWeight - 100) > 0.01) {
            return response()->json(
                [
                    'message'      => 'Component weights must sum to 100%',
                    'total_weight' => $totalWeight,
                ],
                422
            );
        }

        $basket = DB::transaction(
            function () use ($validated, $request) {
                $basket = BasketAsset::create(
                    [
                        'code'                => $validated['code'],
                        'name'                => $validated['name'],
                        'description'         => $validated['description'] ?? null,
                        'type'                => $validated['type'],
                        'rebalance_frequency' => $validated['rebalance_frequency'],
                        'created_by'          => $request->user()?->uuid,
                        'is_active'           => true,
                    ]
                );

                foreach ($validated['components'] as $componentData) {
                    $basket->components()->create($componentData);
                }

                // Create as asset
                $basket->toAsset();

                // Calculate initial value
                $this->valueCalculationService->calculateValue($basket);

                return $basket;
            }
        );

        $basket->load('components.asset');

        return response()->json(
            [
                'code'                => $basket->code,
                'name'                => $basket->name,
                'description'         => $basket->description,
                'type'                => $basket->type,
                'rebalance_frequency' => $basket->rebalance_frequency,
                'is_active'           => $basket->is_active,
                'components'          => $basket->components->map(
                    function ($component) {
                        return [
                            'asset_code' => $component->asset_code,
                            'asset_name' => $component->asset->name ?? $component->asset_code,
                            'weight'     => $component->weight,
                            'min_weight' => $component->min_weight,
                            'max_weight' => $component->max_weight,
                        ];
                    }
                ),
            ],
            201
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v2/baskets/{code}/value",
     *     operationId="getBasketValue",
     *     tags={"Baskets"},
     *     summary="Get current basket value",
     *
     * @OA\Parameter(
     *         name="code",
     *         in="path",
     *         description="Basket code",
     *         required=true,
     *
     * @OA\Schema(type="string")
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Current basket value",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="basket_code",      type="string"),
     * @OA\Property(property="value",            type="number"),
     * @OA\Property(property="calculated_at",    type="string", format="date-time"),
     * @OA\Property(property="component_values", type="object")
     *         )
     *     )
     * )
     */
    public function getValue(string $code): JsonResponse
    {
        $basket = BasketAsset::where('code', $code)->firstOrFail();
        $value = $this->valueCalculationService->calculateValue($basket);

        return response()->json(
            [
                'basket_code'      => $basket->code,
                'value'            => $value->value,
                'calculated_at'    => $value->calculated_at->toISOString(),
                'component_values' => $value->component_values,
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v2/baskets/{code}/rebalance",
     *     operationId="rebalanceBasket",
     *     tags={"Baskets"},
     *     summary="Trigger basket rebalancing",
     *     security={{"bearerAuth":{}}},
     *
     * @OA\Parameter(
     *         name="code",
     *         in="path",
     *         description="Basket code",
     *         required=true,
     *
     * @OA\Schema(type="string")
     *     ),
     *
     * @OA\Parameter(
     *         name="simulate",
     *         in="query",
     *         description="Simulate rebalancing without executing",
     *         required=false,
     *
     * @OA\Schema(type="boolean",           default=false)
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Rebalancing result",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="status",      type="string"),
     * @OA\Property(property="basket",      type="string"),
     * @OA\Property(property="adjustments", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function rebalance(Request $request, string $code): JsonResponse
    {
        $basket = BasketAsset::where('code', $code)->firstOrFail();

        if ($basket->type !== 'dynamic') {
            return response()->json(
                [
                    'message' => 'Only dynamic baskets can be rebalanced',
                ],
                422
            );
        }

        $simulate = $request->boolean('simulate', false);

        if ($simulate) {
            $result = $this->rebalancingService->simulateRebalancing($basket);
        } else {
            $result = $this->rebalancingService->rebalance($basket);
        }

        return response()->json($result);
    }

    /**
     * @OA\Get(
     *     path="/api/v2/baskets/{code}/history",
     *     operationId="getBasketHistory",
     *     tags={"Baskets"},
     *     summary="Get basket value history",
     *
     * @OA\Parameter(
     *         name="code",
     *         in="path",
     *         description="Basket code",
     *         required=true,
     *
     * @OA\Schema(type="string")
     *     ),
     *
     * @OA\Parameter(
     *         name="days",
     *         in="query",
     *         description="Number of days of history",
     *         required=false,
     *
     * @OA\Schema(type="integer", default=30)
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Basket value history"
     *     )
     * )
     */
    public function getHistory(Request $request, string $code): JsonResponse
    {
        $basket = BasketAsset::where('code', $code)->firstOrFail();
        $days = $request->integer('days', 30);

        $history = $this->valueCalculationService->getHistoricalValues(
            $basket,
            now()->subDays($days),
            now()
        );

        return response()->json(
            [
                'basket_code' => $basket->code,
                'period'      => [
                    'start' => now()->subDays($days)->toISOString(),
                    'end'   => now()->toISOString(),
                ],
                'values' => $history,
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v2/baskets/{code}/performance",
     *     operationId="getBasketPerformanceMetrics",
     *     tags={"Baskets"},
     *     summary="Get basket performance metrics",
     *
     * @OA\Parameter(
     *         name="code",
     *         in="path",
     *         description="Basket code",
     *         required=true,
     *
     * @OA\Schema(type="string")
     *     ),
     *
     * @OA\Parameter(
     *         name="period",
     *         in="query",
     *         description="Performance period",
     *         required=false,
     *
     * @OA\Schema(type="string", enum={"1d", "7d", "30d", "90d", "1y"}, default="30d")
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Basket performance metrics"
     *     )
     * )
     */
    public function getPerformance(Request $request, string $code): JsonResponse
    {
        $basket = BasketAsset::where('code', $code)->firstOrFail();
        $period = $request->input('period', '30d');

        $days = match ($period) {
            '1d'    => 1,
            '7d'    => 7,
            '30d'   => 30,
            '90d'   => 90,
            '1y'    => 365,
            default => 30,
        };

        $performance = $this->valueCalculationService->calculatePerformance(
            $basket,
            now()->subDays($days),
            now()
        );

        return response()->json(
            [
                'basket_code' => $basket->code,
                'period'      => $period,
                'performance' => $performance,
            ]
        );
    }
}
