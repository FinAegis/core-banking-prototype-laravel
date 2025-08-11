<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Stablecoin\Models\Stablecoin;
use App\Domain\Stablecoin\Services\CollateralService;
use App\Domain\Stablecoin\Services\StabilityMechanismService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Stablecoins",
 *     description="Stablecoin management and operations"
 * )
 */
class StablecoinController extends Controller
{
    public function __construct(
        private readonly CollateralService $collateralService,
        private readonly StabilityMechanismService $stabilityService
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/v2/stablecoins",
     *     operationId="listStablecoins",
     *     tags={"Stablecoins"},
     *     summary="List all stablecoins",
     *     description="Retrieve a list of all configured stablecoins with optional filtering",
     *
     * @OA\Parameter(
     *         name="active_only",
     *         in="query",
     *         description="Filter to show only active stablecoins",
     *         required=false,
     *
     * @OA\Schema(type="boolean")
     *     ),
     *
     * @OA\Parameter(
     *         name="minting_enabled",
     *         in="query",
     *         description="Filter to show only stablecoins with minting enabled",
     *         required=false,
     *
     * @OA\Schema(type="boolean")
     *     ),
     *
     * @OA\Parameter(
     *         name="burning_enabled",
     *         in="query",
     *         description="Filter to show only stablecoins with burning enabled",
     *         required=false,
     *
     * @OA\Schema(type="boolean")
     *     ),
     *
     * @OA\Parameter(
     *         name="stability_mechanism",
     *         in="query",
     *         description="Filter by stability mechanism type",
     *         required=false,
     *
     * @OA\Schema(type="string",     enum={"collateralized", "algorithmic", "hybrid"})
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="data", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $query = Stablecoin::query();

        if ($request->boolean('active_only')) {
            $query->active();
        }

        if ($request->boolean('minting_enabled')) {
            $query->mintingEnabled();
        }

        if ($request->boolean('burning_enabled')) {
            $query->burningEnabled();
        }

        if ($request->has('stability_mechanism')) {
            $query->where('stability_mechanism', $request->string('stability_mechanism'));
        }

        $stablecoins = $query->get();

        return response()->json(
            [
                'data' => $stablecoins,
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v2/stablecoins/{code}",
     *     operationId="getStablecoin",
     *     tags={"Stablecoins"},
     *     summary="Get stablecoin details",
     *     description="Retrieve detailed information about a specific stablecoin including collateralization metrics",
     *
     * @OA\Parameter(
     *         name="code",
     *         in="path",
     *         description="The stablecoin code",
     *         required=true,
     *
     * @OA\Schema(type="string")
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="data",                     type="object")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=404,
     *         description="Stablecoin not found",
     *
     * @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function show(string $code): JsonResponse
    {
        $stablecoin = Stablecoin::findOrFail($code);

        $metrics = $this->collateralService->getSystemCollateralizationMetrics()[$code] ?? null;

        $data = $stablecoin->toArray();
        $data['global_collateralization_ratio'] = $stablecoin->calculateGlobalCollateralizationRatio();
        $data['is_adequately_collateralized'] = $stablecoin->isAdequatelyCollateralized();

        if ($metrics) {
            $data['active_positions_count'] = $metrics['active_positions'];
            $data['at_risk_positions_count'] = $metrics['at_risk_positions'];
        }

        return response()->json(
            [
                'data' => $data,
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v2/stablecoins",
     *     operationId="createStablecoin",
     *     tags={"Stablecoins"},
     *     summary="Create a new stablecoin",
     *     description="Create a new stablecoin configuration",
     *     security={{"sanctum": {}}},
     *
     * @OA\RequestBody(
     *         required=true,
     *
     * @OA\JsonContent(type="object")
     *     ),
     *
     * @OA\Response(
     *         response=201,
     *         description="Stablecoin created successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="data",                                       type="object")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     * @OA\JsonContent(type="object")
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'code'                 => 'required|string|max:10|unique:stablecoins,code',
                'name'                 => 'required|string|max:255',
                'symbol'               => 'required|string|max:10',
                'peg_asset_code'       => 'required|string|exists:assets,code',
                'peg_ratio'            => 'required|numeric|min:0',
                'target_price'         => 'required|numeric|min:0',
                'stability_mechanism'  => 'required|in:collateralized,algorithmic,hybrid',
                'collateral_ratio'     => 'required|numeric|min:1',
                'min_collateral_ratio' => 'required|numeric|min:1|lt:collateral_ratio',
                'liquidation_penalty'  => 'required|numeric|min:0|max:1',
                'max_supply'           => 'nullable|integer|min:1',
                'mint_fee'             => 'required|numeric|min:0|max:1',
                'burn_fee'             => 'required|numeric|min:0|max:1',
                'precision'            => 'required|integer|min:0|max:18',
                'metadata'             => 'nullable|array',
            ]
        );

        $validated['is_active'] = true;
        $validated['minting_enabled'] = true;
        $validated['burning_enabled'] = true;
        $validated['total_supply'] = 0;
        $validated['total_collateral_value'] = 0;

        $stablecoin = Stablecoin::create($validated);

        return response()->json(
            [
                'data' => $stablecoin,
            ],
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v2/stablecoins/{code}",
     *     operationId="updateStablecoin",
     *     tags={"Stablecoins"},
     *     summary="Update stablecoin configuration",
     *     description="Update an existing stablecoin's configuration parameters",
     *     security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     *         name="code",
     *         in="path",
     *         description="The stablecoin code",
     *         required=true,
     *
     * @OA\Schema(type="string")
     *     ),
     *
     * @OA\RequestBody(
     *         required=true,
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="name",                 type="string", example="FinAegis USD Updated"),
     * @OA\Property(property="collateral_ratio",     type="number", example=1.6),
     * @OA\Property(property="min_collateral_ratio", type="number", example=1.3),
     * @OA\Property(property="liquidation_penalty",  type="number", example=0.1),
     * @OA\Property(property="max_supply",           type="integer", example=10000000),
     * @OA\Property(property="mint_fee",             type="number", example=0.004),
     * @OA\Property(property="burn_fee",             type="number", example=0.003),
     * @OA\Property(property="is_active",            type="boolean", example=true),
     * @OA\Property(property="minting_enabled",      type="boolean", example=true),
     * @OA\Property(property="burning_enabled",      type="boolean", example=true),
     * @OA\Property(property="metadata",             type="object")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Stablecoin updated successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="data",                 type="object")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=404,
     *         description="Stablecoin not found"
     *     ),
     * @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function update(Request $request, string $code): JsonResponse
    {
        $stablecoin = Stablecoin::findOrFail($code);

        $validated = $request->validate(
            [
                'name'                 => 'sometimes|string|max:255',
                'collateral_ratio'     => 'sometimes|numeric|min:1',
                'min_collateral_ratio' => 'sometimes|numeric|min:1',
                'liquidation_penalty'  => 'sometimes|numeric|min:0|max:1',
                'max_supply'           => 'sometimes|nullable|integer|min:1',
                'mint_fee'             => 'sometimes|numeric|min:0|max:1',
                'burn_fee'             => 'sometimes|numeric|min:0|max:1',
                'is_active'            => 'sometimes|boolean',
                'minting_enabled'      => 'sometimes|boolean',
                'burning_enabled'      => 'sometimes|boolean',
                'metadata'             => 'sometimes|nullable|array',
            ]
        );

        // Validate that min_collateral_ratio is less than collateral_ratio
        if (isset($validated['min_collateral_ratio']) || isset($validated['collateral_ratio'])) {
            $newMinRatio = $validated['min_collateral_ratio'] ?? $stablecoin->min_collateral_ratio;
            $newCollateralRatio = $validated['collateral_ratio'] ?? $stablecoin->collateral_ratio;

            if ($newMinRatio >= $newCollateralRatio) {
                throw ValidationException::withMessages(
                    [
                        'min_collateral_ratio' => 'Minimum collateral ratio must be less than collateral ratio',
                    ]
                );
            }
        }

        $stablecoin->update($validated);

        return response()->json(
            [
                'data' => $stablecoin,
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v2/stablecoins/{code}/metrics",
     *     operationId="getStablecoinMetrics",
     *     tags={"Stablecoins"},
     *     summary="Get stablecoin metrics and statistics",
     *     description="Retrieve detailed metrics and statistics for a specific stablecoin",
     *
     * @OA\Parameter(
     *         name="code",
     *         in="path",
     *         description="The stablecoin code",
     *         required=true,
     *
     * @OA\Schema(type="string")
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="data",                    type="object",
     * @OA\Property(property="stablecoin_code",         type="string", example="FUSD"),
     * @OA\Property(property="total_supply",            type="integer", example=1000000),
     * @OA\Property(property="total_collateral_value",  type="integer", example=1500000),
     * @OA\Property(property="global_ratio",            type="number", example=1.5),
     * @OA\Property(property="target_ratio",            type="number", example=1.5),
     * @OA\Property(property="min_ratio",               type="number", example=1.2),
     * @OA\Property(property="active_positions",        type="integer", example=25),
     * @OA\Property(property="at_risk_positions",       type="integer", example=2),
     * @OA\Property(property="is_healthy",              type="boolean", example=true),
     * @OA\Property(property="collateral_distribution", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=404,
     *         description="Stablecoin not found or no metrics available"
     *     )
     * )
     */
    public function metrics(string $code): JsonResponse
    {
        $stablecoin = Stablecoin::findOrFail($code);
        $metrics = $this->collateralService->getSystemCollateralizationMetrics()[$code] ?? null;

        if (! $metrics) {
            return response()->json(
                [
                    'error' => 'No metrics available for this stablecoin',
                ],
                404
            );
        }

        return response()->json(
            [
                'data' => $metrics,
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v2/stablecoins/metrics",
     *     operationId="getSystemStablecoinMetrics",
     *     tags={"Stablecoins"},
     *     summary="Get system-wide stablecoin metrics",
     *     description="Retrieve metrics for all stablecoins in the system",
     *
     * @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="data", type="object", additionalProperties={
     *                 "type": "object",
     *                 "properties": {
     *                     "stablecoin_code": {"type": "string"},
     *                     "total_supply": {"type": "integer"},
     *                     "global_ratio": {"type": "number"},
     *                     "is_healthy": {"type": "boolean"}
     *                 }
     *             })
     *         )
     *     )
     * )
     */
    public function systemMetrics(): JsonResponse
    {
        $metrics = $this->collateralService->getSystemCollateralizationMetrics();

        return response()->json(
            [
                'data' => $metrics,
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v2/stablecoins/{code}/stability",
     *     operationId="executeStabilityMechanism",
     *     tags={"Stablecoins"},
     *     summary="Execute stability mechanisms for a stablecoin",
     *     description="Trigger stability mechanism execution for a specific stablecoin",
     *     security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     *         name="code",
     *         in="path",
     *         description="The stablecoin code",
     *         required=true,
     *
     * @OA\Schema(type="string")
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="data",          type="object",
     * @OA\Property(property="success",       type="boolean", example=true),
     * @OA\Property(property="mechanism",     type="string", example="collateralized"),
     * @OA\Property(property="global_ratio",  type="number", example=1.5),
     * @OA\Property(property="target_ratio",  type="number", example=1.5),
     * @OA\Property(property="actions_taken", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=404,
     *         description="Stablecoin not found"
     *     )
     * )
     */
    public function executeStabilityMechanism(string $code): JsonResponse
    {
        $stablecoin = Stablecoin::findOrFail($code);
        $result = $this->stabilityService->executeStabilityMechanismForStablecoin($stablecoin);

        return response()->json(
            [
                'data' => $result,
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v2/stablecoins/health",
     *     operationId="getStablecoinSystemHealth",
     *     tags={"Stablecoins"},
     *     summary="Check system health across all stablecoins",
     *     description="Retrieve system-wide health status for all stablecoins",
     *
     * @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="data",              type="object",
     * @OA\Property(property="overall_status",    type="string", example="healthy"),
     * @OA\Property(property="stablecoin_status", type="array", @OA\Items(type="object")),
     * @OA\Property(property="emergency_actions", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function systemHealth(): JsonResponse
    {
        $health = $this->stabilityService->checkSystemHealth();

        return response()->json(
            [
                'data' => $health,
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v2/stablecoins/{code}/collateral",
     *     operationId="getStablecoinCollateralDistribution",
     *     tags={"Stablecoins"},
     *     summary="Get collateral distribution for a stablecoin",
     *     description="Retrieve the distribution of collateral assets backing a stablecoin",
     *
     * @OA\Parameter(
     *         name="code",
     *         in="path",
     *         description="The stablecoin code",
     *         required=true,
     *
     * @OA\Schema(type="string")
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="data",           type="array", @OA\Items(
     * @OA\Property(property="asset_code",     type="string", example="USD"),
     * @OA\Property(property="total_amount",   type="integer", example=800000),
     * @OA\Property(property="total_value",    type="integer", example=800000),
     * @OA\Property(property="position_count", type="integer", example=15),
     * @OA\Property(property="percentage",     type="number", example=53.33)
     *             ))
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=404,
     *         description="Stablecoin not found"
     *     )
     * )
     */
    public function collateralDistribution(string $code): JsonResponse
    {
        $stablecoin = Stablecoin::findOrFail($code);
        $distribution = $this->collateralService->getCollateralDistribution($code);

        return response()->json(
            [
                'data' => array_values($distribution),
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v2/stablecoins/{code}/deactivate",
     *     operationId="deactivateStablecoin",
     *     tags={"Stablecoins"},
     *     summary="Deactivate a stablecoin",
     *     description="Deactivate a stablecoin, disabling minting and burning",
     *     security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     *         name="code",
     *         in="path",
     *         description="The stablecoin code",
     *         required=true,
     *
     * @OA\Schema(type="string")
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Stablecoin deactivated successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="message", type="string", example="Stablecoin deactivated successfully"),
     * @OA\Property(property="data",    type="object")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=404,
     *         description="Stablecoin not found"
     *     )
     * )
     */
    public function deactivate(string $code): JsonResponse
    {
        $stablecoin = Stablecoin::findOrFail($code);

        $stablecoin->update(
            [
                'is_active'       => false,
                'minting_enabled' => false,
                'burning_enabled' => false,
            ]
        );

        return response()->json(
            [
                'message' => 'Stablecoin deactivated successfully',
                'data'    => $stablecoin,
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v2/stablecoins/{code}/reactivate",
     *     operationId="reactivateStablecoin",
     *     tags={"Stablecoins"},
     *     summary="Reactivate a stablecoin",
     *     description="Reactivate a previously deactivated stablecoin",
     *     security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     *         name="code",
     *         in="path",
     *         description="The stablecoin code",
     *         required=true,
     *
     * @OA\Schema(type="string")
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Stablecoin reactivated successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="message", type="string", example="Stablecoin reactivated successfully"),
     * @OA\Property(property="data",    type="object")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=404,
     *         description="Stablecoin not found"
     *     )
     * )
     */
    public function reactivate(string $code): JsonResponse
    {
        $stablecoin = Stablecoin::findOrFail($code);

        $stablecoin->update(
            [
                'is_active'       => true,
                'minting_enabled' => true,
                'burning_enabled' => true,
            ]
        );

        return response()->json(
            [
                'message' => 'Stablecoin reactivated successfully',
                'data'    => $stablecoin,
            ]
        );
    }
}
