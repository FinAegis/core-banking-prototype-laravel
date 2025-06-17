<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Stablecoin\Services\CollateralService;
use App\Domain\Stablecoin\Services\StabilityMechanismService;
use App\Http\Controllers\Controller;
use App\Models\Stablecoin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @group Stablecoin Management
 *
 * APIs for managing stablecoins and their configurations.
 */
class StablecoinController extends Controller
{
    public function __construct(
        private readonly CollateralService $collateralService,
        private readonly StabilityMechanismService $stabilityService
    ) {}

    /**
     * List all stablecoins
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "code": "FUSD",
     *       "name": "FinAegis USD",
     *       "symbol": "FUSD",
     *       "peg_asset_code": "USD",
     *       "peg_ratio": "1.00000000",
     *       "target_price": "1.00000000",
     *       "stability_mechanism": "collateralized",
     *       "collateral_ratio": "1.5000",
     *       "min_collateral_ratio": "1.2000",
     *       "liquidation_penalty": "0.1000",
     *       "total_supply": 1000000,
     *       "max_supply": 10000000,
     *       "total_collateral_value": 1500000,
     *       "mint_fee": "0.005000",
     *       "burn_fee": "0.003000",
     *       "precision": 2,
     *       "is_active": true,
     *       "minting_enabled": true,
     *       "burning_enabled": true
     *     }
     *   ]
     * }
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
        
        return response()->json([
            'data' => $stablecoins,
        ]);
    }

    /**
     * Get stablecoin details
     *
     * @urlParam code string required The stablecoin code. Example: FUSD
     *
     * @response 200 {
     *   "data": {
     *     "code": "FUSD",
     *     "name": "FinAegis USD",
     *     "symbol": "FUSD",
     *     "peg_asset_code": "USD",
     *     "stability_mechanism": "collateralized",
     *     "collateral_ratio": "1.5000",
     *     "min_collateral_ratio": "1.2000",
     *     "total_supply": 1000000,
     *     "max_supply": 10000000,
     *     "total_collateral_value": 1500000,
     *     "global_collateralization_ratio": 1.5,
     *     "is_adequately_collateralized": true,
     *     "active_positions_count": 25,
     *     "at_risk_positions_count": 2
     *   }
     * }
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
        
        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Create a new stablecoin
     *
     * @bodyParam code string required Unique stablecoin code. Example: FEUR
     * @bodyParam name string required Stablecoin name. Example: FinAegis Euro
     * @bodyParam symbol string required Trading symbol. Example: FEUR
     * @bodyParam peg_asset_code string required Asset to peg to. Example: EUR
     * @bodyParam peg_ratio number required Peg ratio (usually 1.0). Example: 1.0
     * @bodyParam target_price number required Target price. Example: 1.0
     * @bodyParam stability_mechanism string required Stability mechanism (collateralized, algorithmic, hybrid). Example: collateralized
     * @bodyParam collateral_ratio number required Required collateral ratio. Example: 1.5
     * @bodyParam min_collateral_ratio number required Minimum ratio before liquidation. Example: 1.2
     * @bodyParam liquidation_penalty number required Liquidation penalty (0-1). Example: 0.1
     * @bodyParam max_supply integer optional Maximum supply limit. Example: 10000000
     * @bodyParam mint_fee number required Minting fee (0-1). Example: 0.005
     * @bodyParam burn_fee number required Burning fee (0-1). Example: 0.003
     * @bodyParam precision integer required Decimal precision. Example: 2
     * @bodyParam metadata object optional Additional metadata
     *
     * @response 201 {
     *   "data": {
     *     "code": "FEUR",
     *     "name": "FinAegis Euro",
     *     "symbol": "FEUR",
     *     "peg_asset_code": "EUR",
     *     "stability_mechanism": "collateralized",
     *     "is_active": true,
     *     "minting_enabled": true,
     *     "burning_enabled": true
     *   }
     * }
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:10|unique:stablecoins,code',
            'name' => 'required|string|max:255',
            'symbol' => 'required|string|max:10',
            'peg_asset_code' => 'required|string|exists:assets,code',
            'peg_ratio' => 'required|numeric|min:0',
            'target_price' => 'required|numeric|min:0',
            'stability_mechanism' => 'required|in:collateralized,algorithmic,hybrid',
            'collateral_ratio' => 'required|numeric|min:1',
            'min_collateral_ratio' => 'required|numeric|min:1|lt:collateral_ratio',
            'liquidation_penalty' => 'required|numeric|min:0|max:1',
            'max_supply' => 'nullable|integer|min:1',
            'mint_fee' => 'required|numeric|min:0|max:1',
            'burn_fee' => 'required|numeric|min:0|max:1',
            'precision' => 'required|integer|min:0|max:18',
            'metadata' => 'nullable|array',
        ]);
        
        $validated['is_active'] = true;
        $validated['minting_enabled'] = true;
        $validated['burning_enabled'] = true;
        $validated['total_supply'] = 0;
        $validated['total_collateral_value'] = 0;
        
        $stablecoin = Stablecoin::create($validated);
        
        return response()->json([
            'data' => $stablecoin,
        ], 201);
    }

    /**
     * Update stablecoin configuration
     *
     * @urlParam code string required The stablecoin code. Example: FUSD
     *
     * @bodyParam name string optional Stablecoin name
     * @bodyParam collateral_ratio number optional Required collateral ratio
     * @bodyParam min_collateral_ratio number optional Minimum ratio before liquidation
     * @bodyParam liquidation_penalty number optional Liquidation penalty (0-1)
     * @bodyParam max_supply integer optional Maximum supply limit
     * @bodyParam mint_fee number optional Minting fee (0-1)
     * @bodyParam burn_fee number optional Burning fee (0-1)
     * @bodyParam is_active boolean optional Whether stablecoin is active
     * @bodyParam minting_enabled boolean optional Whether minting is enabled
     * @bodyParam burning_enabled boolean optional Whether burning is enabled
     * @bodyParam metadata object optional Additional metadata
     *
     * @response 200 {
     *   "data": {
     *     "code": "FUSD",
     *     "name": "FinAegis USD Updated",
     *     "collateral_ratio": "1.6000",
     *     "mint_fee": "0.004000"
     *   }
     * }
     */
    public function update(Request $request, string $code): JsonResponse
    {
        $stablecoin = Stablecoin::findOrFail($code);
        
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'collateral_ratio' => 'sometimes|numeric|min:1',
            'min_collateral_ratio' => 'sometimes|numeric|min:1',
            'liquidation_penalty' => 'sometimes|numeric|min:0|max:1',
            'max_supply' => 'sometimes|nullable|integer|min:1',
            'mint_fee' => 'sometimes|numeric|min:0|max:1',
            'burn_fee' => 'sometimes|numeric|min:0|max:1',
            'is_active' => 'sometimes|boolean',
            'minting_enabled' => 'sometimes|boolean',
            'burning_enabled' => 'sometimes|boolean',
            'metadata' => 'sometimes|nullable|array',
        ]);
        
        // Validate that min_collateral_ratio is less than collateral_ratio
        if (isset($validated['min_collateral_ratio']) || isset($validated['collateral_ratio'])) {
            $newMinRatio = $validated['min_collateral_ratio'] ?? $stablecoin->min_collateral_ratio;
            $newCollateralRatio = $validated['collateral_ratio'] ?? $stablecoin->collateral_ratio;
            
            if ($newMinRatio >= $newCollateralRatio) {
                throw ValidationException::withMessages([
                    'min_collateral_ratio' => 'Minimum collateral ratio must be less than collateral ratio',
                ]);
            }
        }
        
        $stablecoin->update($validated);
        
        return response()->json([
            'data' => $stablecoin,
        ]);
    }

    /**
     * Get stablecoin metrics and statistics
     *
     * @urlParam code string required The stablecoin code. Example: FUSD
     *
     * @response 200 {
     *   "data": {
     *     "stablecoin_code": "FUSD",
     *     "total_supply": 1000000,
     *     "total_collateral_value": 1500000,
     *     "global_ratio": 1.5,
     *     "target_ratio": 1.5,
     *     "min_ratio": 1.2,
     *     "active_positions": 25,
     *     "at_risk_positions": 2,
     *     "is_healthy": true,
     *     "collateral_distribution": [
     *       {
     *         "asset_code": "USD",
     *         "total_amount": 800000,
     *         "total_value": 800000,
     *         "position_count": 15,
     *         "percentage": 53.33
     *       }
     *     ]
     *   }
     * }
     */
    public function metrics(string $code): JsonResponse
    {
        $stablecoin = Stablecoin::findOrFail($code);
        $metrics = $this->collateralService->getSystemCollateralizationMetrics()[$code] ?? null;
        
        if (!$metrics) {
            return response()->json([
                'error' => 'No metrics available for this stablecoin',
            ], 404);
        }
        
        return response()->json([
            'data' => $metrics,
        ]);
    }

    /**
     * Get system-wide stablecoin metrics
     *
     * @response 200 {
     *   "data": {
     *     "FUSD": {
     *       "stablecoin_code": "FUSD",
     *       "total_supply": 1000000,
     *       "global_ratio": 1.5,
     *       "is_healthy": true
     *     },
     *     "FEUR": {
     *       "stablecoin_code": "FEUR",
     *       "total_supply": 500000,
     *       "global_ratio": 1.3,
     *       "is_healthy": true
     *     }
     *   }
     * }
     */
    public function systemMetrics(): JsonResponse
    {
        $metrics = $this->collateralService->getSystemCollateralizationMetrics();
        
        return response()->json([
            'data' => $metrics,
        ]);
    }

    /**
     * Execute stability mechanisms for a stablecoin
     *
     * @urlParam code string required The stablecoin code. Example: FUSD
     *
     * @response 200 {
     *   "data": {
     *     "success": true,
     *     "mechanism": "collateralized",
     *     "global_ratio": 1.5,
     *     "target_ratio": 1.5,
     *     "actions_taken": [
     *       {
     *         "type": "risk_monitoring",
     *         "positions_at_risk": 2
     *       }
     *     ]
     *   }
     * }
     */
    public function executeStabilityMechanism(string $code): JsonResponse
    {
        $stablecoin = Stablecoin::findOrFail($code);
        $result = $this->stabilityService->executeStabilityMechanismForStablecoin($stablecoin);
        
        return response()->json([
            'data' => $result,
        ]);
    }

    /**
     * Check system health across all stablecoins
     *
     * @response 200 {
     *   "data": {
     *     "overall_status": "healthy",
     *     "stablecoin_status": [
     *       {
     *         "code": "FUSD",
     *         "is_healthy": true,
     *         "global_ratio": 1.5,
     *         "at_risk_positions": 2,
     *         "status": "healthy"
     *       }
     *     ],
     *     "emergency_actions": []
     *   }
     * }
     */
    public function systemHealth(): JsonResponse
    {
        $health = $this->stabilityService->checkSystemHealth();
        
        return response()->json([
            'data' => $health,
        ]);
    }

    /**
     * Get collateral distribution for a stablecoin
     *
     * @urlParam code string required The stablecoin code. Example: FUSD
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "asset_code": "USD",
     *       "total_amount": 800000,
     *       "total_value": 800000,
     *       "position_count": 15,
     *       "percentage": 53.33
     *     },
     *     {
     *       "asset_code": "EUR",
     *       "total_amount": 400000,
     *       "total_value": 440000,
     *       "position_count": 10,
     *       "percentage": 29.33
     *     }
     *   ]
     * }
     */
    public function collateralDistribution(string $code): JsonResponse
    {
        $stablecoin = Stablecoin::findOrFail($code);
        $distribution = $this->collateralService->getCollateralDistribution($code);
        
        return response()->json([
            'data' => array_values($distribution),
        ]);
    }

    /**
     * Deactivate a stablecoin
     *
     * @urlParam code string required The stablecoin code. Example: FUSD
     *
     * @response 200 {
     *   "message": "Stablecoin deactivated successfully",
     *   "data": {
     *     "code": "FUSD",
     *     "is_active": false,
     *     "minting_enabled": false,
     *     "burning_enabled": false
     *   }
     * }
     */
    public function deactivate(string $code): JsonResponse
    {
        $stablecoin = Stablecoin::findOrFail($code);
        
        $stablecoin->update([
            'is_active' => false,
            'minting_enabled' => false,
            'burning_enabled' => false,
        ]);
        
        return response()->json([
            'message' => 'Stablecoin deactivated successfully',
            'data' => $stablecoin,
        ]);
    }

    /**
     * Reactivate a stablecoin
     *
     * @urlParam code string required The stablecoin code. Example: FUSD
     *
     * @response 200 {
     *   "message": "Stablecoin reactivated successfully",
     *   "data": {
     *     "code": "FUSD",
     *     "is_active": true,
     *     "minting_enabled": true,
     *     "burning_enabled": true
     *   }
     * }
     */
    public function reactivate(string $code): JsonResponse
    {
        $stablecoin = Stablecoin::findOrFail($code);
        
        $stablecoin->update([
            'is_active' => true,
            'minting_enabled' => true,
            'burning_enabled' => true,
        ]);
        
        return response()->json([
            'message' => 'Stablecoin reactivated successfully',
            'data' => $stablecoin,
        ]);
    }
}