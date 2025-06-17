<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Stablecoin\Services\CollateralService;
use App\Domain\Stablecoin\Services\LiquidationService;
use App\Domain\Stablecoin\Services\StablecoinIssuanceService;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Stablecoin;
use App\Models\StablecoinCollateralPosition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @group Stablecoin Operations
 *
 * APIs for stablecoin minting, burning, and collateral management.
 */
class StablecoinOperationsController extends Controller
{
    public function __construct(
        private readonly StablecoinIssuanceService $issuanceService,
        private readonly CollateralService $collateralService,
        private readonly LiquidationService $liquidationService
    ) {}

    /**
     * Mint stablecoins by locking collateral
     *
     * @bodyParam account_uuid string required Account UUID to mint for. Example: 123e4567-e89b-12d3-a456-426614174000
     * @bodyParam stablecoin_code string required Stablecoin to mint. Example: FUSD
     * @bodyParam collateral_asset_code string required Asset to use as collateral. Example: USD
     * @bodyParam collateral_amount integer required Amount of collateral to lock (in smallest unit). Example: 150000
     * @bodyParam mint_amount integer required Amount of stablecoin to mint (in smallest unit). Example: 100000
     *
     * @response 200 {
     *   "message": "Stablecoin minted successfully",
     *   "data": {
     *     "position_uuid": "123e4567-e89b-12d3-a456-426614174000",
     *     "account_uuid": "123e4567-e89b-12d3-a456-426614174000",
     *     "stablecoin_code": "FUSD",
     *     "collateral_asset_code": "USD",
     *     "collateral_amount": 150000,
     *     "debt_amount": 100000,
     *     "collateral_ratio": "1.5000",
     *     "status": "active"
     *   }
     * }
     */
    public function mint(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_uuid' => 'required|uuid|exists:accounts,uuid',
            'stablecoin_code' => 'required|string|exists:stablecoins,code',
            'collateral_asset_code' => 'required|string|exists:assets,code',
            'collateral_amount' => 'required|integer|min:1',
            'mint_amount' => 'required|integer|min:1',
        ]);
        
        $account = Account::where('uuid', $validated['account_uuid'])->firstOrFail();
        
        try {
            $position = $this->issuanceService->mint(
                $account,
                $validated['stablecoin_code'],
                $validated['collateral_asset_code'],
                $validated['collateral_amount'],
                $validated['mint_amount']
            );
            
            return response()->json([
                'message' => 'Stablecoin minted successfully',
                'data' => $position->load(['stablecoin', 'collateralAsset']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Burn stablecoins and release collateral
     *
     * @bodyParam account_uuid string required Account UUID to burn from. Example: 123e4567-e89b-12d3-a456-426614174000
     * @bodyParam stablecoin_code string required Stablecoin to burn. Example: FUSD
     * @bodyParam burn_amount integer required Amount of stablecoin to burn (in smallest unit). Example: 50000
     * @bodyParam collateral_release_amount integer optional Specific amount of collateral to release. If not provided, proportional amount will be released. Example: 75000
     *
     * @response 200 {
     *   "message": "Stablecoin burned successfully",
     *   "data": {
     *     "position_uuid": "123e4567-e89b-12d3-a456-426614174000",
     *     "account_uuid": "123e4567-e89b-12d3-a456-426614174000",
     *     "stablecoin_code": "FUSD",
     *     "collateral_amount": 75000,
     *     "debt_amount": 50000,
     *     "collateral_ratio": "1.5000",
     *     "status": "active"
     *   }
     * }
     */
    public function burn(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_uuid' => 'required|uuid|exists:accounts,uuid',
            'stablecoin_code' => 'required|string|exists:stablecoins,code',
            'burn_amount' => 'required|integer|min:1',
            'collateral_release_amount' => 'nullable|integer|min:0',
        ]);
        
        $account = Account::where('uuid', $validated['account_uuid'])->firstOrFail();
        
        try {
            $position = $this->issuanceService->burn(
                $account,
                $validated['stablecoin_code'],
                $validated['burn_amount'],
                $validated['collateral_release_amount'] ?? null
            );
            
            return response()->json([
                'message' => 'Stablecoin burned successfully',
                'data' => $position->load(['stablecoin', 'collateralAsset']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Add collateral to an existing position
     *
     * @bodyParam account_uuid string required Account UUID. Example: 123e4567-e89b-12d3-a456-426614174000
     * @bodyParam stablecoin_code string required Stablecoin code. Example: FUSD
     * @bodyParam collateral_asset_code string required Collateral asset code. Example: USD
     * @bodyParam collateral_amount integer required Amount of collateral to add (in smallest unit). Example: 50000
     *
     * @response 200 {
     *   "message": "Collateral added successfully",
     *   "data": {
     *     "position_uuid": "123e4567-e89b-12d3-a456-426614174000",
     *     "collateral_amount": 200000,
     *     "debt_amount": 100000,
     *     "collateral_ratio": "2.0000",
     *     "health_improved": true
     *   }
     * }
     */
    public function addCollateral(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_uuid' => 'required|uuid|exists:accounts,uuid',
            'stablecoin_code' => 'required|string|exists:stablecoins,code',
            'collateral_asset_code' => 'required|string|exists:assets,code',
            'collateral_amount' => 'required|integer|min:1',
        ]);
        
        $account = Account::where('uuid', $validated['account_uuid'])->firstOrFail();
        
        try {
            $position = $this->issuanceService->addCollateral(
                $account,
                $validated['stablecoin_code'],
                $validated['collateral_asset_code'],
                $validated['collateral_amount']
            );
            
            return response()->json([
                'message' => 'Collateral added successfully',
                'data' => $position->load(['stablecoin', 'collateralAsset']),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get account's stablecoin positions
     *
     * @urlParam accountUuid string required Account UUID. Example: 123e4567-e89b-12d3-a456-426614174000
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "uuid": "123e4567-e89b-12d3-a456-426614174000",
     *       "stablecoin_code": "FUSD",
     *       "collateral_asset_code": "USD",
     *       "collateral_amount": 150000,
     *       "debt_amount": 100000,
     *       "collateral_ratio": "1.5000",
     *       "liquidation_price": "0.80000000",
     *       "status": "active",
     *       "health_score": 0.75,
     *       "recommendations": []
     *     }
     *   ]
     * }
     */
    public function getAccountPositions(string $accountUuid): JsonResponse
    {
        $account = Account::where('uuid', $accountUuid)->firstOrFail();
        
        $positions = StablecoinCollateralPosition::where('account_uuid', $accountUuid)
            ->with(['stablecoin', 'collateralAsset'])
            ->get();
        
        $enhancedPositions = $positions->map(function ($position) {
            $this->collateralService->updatePositionCollateralRatio($position);
            $healthScore = $this->collateralService->calculatePositionHealthScore($position);
            $recommendations = $this->collateralService->getPositionRecommendations($position);
            
            return array_merge($position->toArray(), [
                'health_score' => $healthScore,
                'recommendations' => $recommendations,
            ]);
        });
        
        return response()->json([
            'data' => $enhancedPositions,
        ]);
    }

    /**
     * Get position details with recommendations
     *
     * @urlParam positionUuid string required Position UUID. Example: 123e4567-e89b-12d3-a456-426614174000
     *
     * @response 200 {
     *   "data": {
     *     "uuid": "123e4567-e89b-12d3-a456-426614174000",
     *     "account_uuid": "123e4567-e89b-12d3-a456-426614174000",
     *     "stablecoin_code": "FUSD",
     *     "collateral_asset_code": "USD",
     *     "collateral_amount": 150000,
     *     "debt_amount": 100000,
     *     "collateral_ratio": "1.5000",
     *     "health_score": 0.75,
     *     "max_mint_amount": 25000,
     *     "liquidation_price": "0.80000000",
     *     "is_at_risk": false,
     *     "recommendations": [
     *       {
     *         "action": "mint_more",
     *         "urgency": "low",
     *         "message": "Position is over-collateralized, you can mint more stablecoins",
     *         "max_mint_amount": 25000
     *       }
     *     ]
     *   }
     * }
     */
    public function getPositionDetails(string $positionUuid): JsonResponse
    {
        $position = StablecoinCollateralPosition::where('uuid', $positionUuid)
            ->with(['stablecoin', 'collateralAsset', 'account'])
            ->firstOrFail();
        
        $this->collateralService->updatePositionCollateralRatio($position);
        
        $healthScore = $this->collateralService->calculatePositionHealthScore($position);
        $recommendations = $this->collateralService->getPositionRecommendations($position);
        $maxMintAmount = $position->calculateMaxMintAmount();
        
        $data = $position->toArray();
        $data['health_score'] = $healthScore;
        $data['max_mint_amount'] = $maxMintAmount;
        $data['is_at_risk'] = $position->isAtRiskOfLiquidation();
        $data['recommendations'] = $recommendations;
        
        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Get liquidation opportunities
     *
     * @queryParam limit integer optional Number of opportunities to return (default: 50). Example: 20
     * @queryParam stablecoin_code string optional Filter by stablecoin code. Example: FUSD
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "position_uuid": "123e4567-e89b-12d3-a456-426614174000",
     *       "account_uuid": "123e4567-e89b-12d3-a456-426614174000",
     *       "stablecoin_code": "FUSD",
     *       "eligible": true,
     *       "reward": 5000,
     *       "penalty": 10000,
     *       "collateral_seized": 100000,
     *       "debt_amount": 90000,
     *       "collateral_asset": "USD",
     *       "current_ratio": "1.1000",
     *       "min_ratio": "1.2000",
     *       "priority_score": 0.85,
     *       "health_score": 0.1
     *     }
     *   ]
     * }
     */
    public function getLiquidationOpportunities(Request $request): JsonResponse
    {
        $limit = $request->integer('limit', 50);
        $stablecoinCode = $request->string('stablecoin_code');
        
        $opportunities = $this->liquidationService->getLiquidationOpportunities($limit);
        
        if ($stablecoinCode) {
            $opportunities = $opportunities->where('stablecoin_code', $stablecoinCode);
        }
        
        return response()->json([
            'data' => $opportunities->values(),
        ]);
    }

    /**
     * Liquidate a specific position
     *
     * @urlParam positionUuid string required Position UUID to liquidate. Example: 123e4567-e89b-12d3-a456-426614174000
     *
     * @response 200 {
     *   "message": "Position liquidated successfully",
     *   "data": {
     *     "position_uuid": "123e4567-e89b-12d3-a456-426614174000",
     *     "liquidated_debt": 90000,
     *     "liquidated_collateral": 100000,
     *     "penalty_amount": 10000,
     *     "liquidator_reward": 5000,
     *     "protocol_fee": 5000,
     *     "returned_to_owner": 90000,
     *     "liquidator_uuid": "456e7890-e89b-12d3-a456-426614174001"
     *   }
     * }
     */
    public function liquidatePosition(string $positionUuid): JsonResponse
    {
        $position = StablecoinCollateralPosition::where('uuid', $positionUuid)->firstOrFail();
        $liquidator = Auth::user()?->account;
        
        try {
            $result = $this->liquidationService->liquidatePosition($position, $liquidator);
            
            return response()->json([
                'message' => 'Position liquidated successfully',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Calculate potential liquidation reward for a position
     *
     * @urlParam positionUuid string required Position UUID. Example: 123e4567-e89b-12d3-a456-426614174000
     *
     * @response 200 {
     *   "data": {
     *     "eligible": true,
     *     "reward": 5000,
     *     "penalty": 10000,
     *     "collateral_seized": 100000,
     *     "debt_amount": 90000,
     *     "collateral_asset": "USD",
     *     "current_ratio": "1.1000",
     *     "min_ratio": "1.2000"
     *   }
     * }
     */
    public function calculateLiquidationReward(string $positionUuid): JsonResponse
    {
        $position = StablecoinCollateralPosition::where('uuid', $positionUuid)
            ->with('stablecoin')
            ->firstOrFail();
        
        $this->collateralService->updatePositionCollateralRatio($position);
        $reward = $this->liquidationService->calculateLiquidationReward($position);
        
        return response()->json([
            'data' => $reward,
        ]);
    }

    /**
     * Simulate mass liquidation scenario
     *
     * @urlParam stablecoinCode string required Stablecoin code. Example: FUSD
     * @bodyParam price_drop_percentage number required Price drop percentage (0-1). Example: 0.2
     *
     * @response 200 {
     *   "data": {
     *     "stablecoin_code": "FUSD",
     *     "price_drop_percentage": 20,
     *     "total_positions": 25,
     *     "positions_liquidated": 8,
     *     "liquidation_impact_percentage": 32,
     *     "total_collateral_seized": 800000,
     *     "total_debt_liquidated": 720000,
     *     "detailed_results": []
     *   }
     * }
     */
    public function simulateMassLiquidation(Request $request, string $stablecoinCode): JsonResponse
    {
        $validated = $request->validate([
            'price_drop_percentage' => 'required|numeric|min:0|max:1',
        ]);
        
        $simulation = $this->liquidationService->simulateMassLiquidation(
            $stablecoinCode,
            $validated['price_drop_percentage']
        );
        
        return response()->json([
            'data' => $simulation,
        ]);
    }

    /**
     * Execute automatic liquidation for all eligible positions
     *
     * @response 200 {
     *   "message": "Automatic liquidation executed",
     *   "data": {
     *     "liquidated_count": 3,
     *     "failed_count": 0,
     *     "total_liquidator_reward": 15000,
     *     "total_protocol_fees": 15000,
     *     "results": []
     *   }
     * }
     */
    public function executeAutoLiquidation(): JsonResponse
    {
        $liquidator = Auth::user()?->account;
        
        try {
            $result = $this->liquidationService->liquidateEligiblePositions($liquidator);
            
            return response()->json([
                'message' => 'Automatic liquidation executed',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get positions at risk of liquidation
     *
     * @queryParam buffer_ratio number optional Risk buffer ratio (default: 0.05). Example: 0.1
     * @queryParam stablecoin_code string optional Filter by stablecoin code. Example: FUSD
     *
     * @response 200 {
     *   "data": [
     *     {
     *       "uuid": "123e4567-e89b-12d3-a456-426614174000",
     *       "account_uuid": "123e4567-e89b-12d3-a456-426614174000",
     *       "stablecoin_code": "FUSD",
     *       "collateral_ratio": "1.2500",
     *       "health_score": 0.25,
     *       "risk_level": "high",
     *       "recommendations": [
     *         {
     *           "action": "add_collateral",
     *           "urgency": "high",
     *           "suggested_amount": 25000
     *         }
     *       ]
     *     }
     *   ]
     * }
     */
    public function getPositionsAtRisk(Request $request): JsonResponse
    {
        $bufferRatio = $request->float('buffer_ratio', 0.05);
        $stablecoinCode = $request->string('stablecoin_code');
        
        $atRiskPositions = $this->collateralService->getPositionsAtRisk($bufferRatio);
        
        if ($stablecoinCode) {
            $atRiskPositions = $atRiskPositions->where('stablecoin_code', $stablecoinCode);
        }
        
        $enhancedPositions = $atRiskPositions->map(function ($position) {
            $healthScore = $this->collateralService->calculatePositionHealthScore($position);
            $recommendations = $this->collateralService->getPositionRecommendations($position);
            
            $riskLevel = 'low';
            if ($healthScore < 0.2) {
                $riskLevel = 'critical';
            } elseif ($healthScore < 0.4) {
                $riskLevel = 'high';
            } elseif ($healthScore < 0.6) {
                $riskLevel = 'medium';
            }
            
            return array_merge($position->toArray(), [
                'health_score' => $healthScore,
                'risk_level' => $riskLevel,
                'recommendations' => $recommendations,
            ]);
        });
        
        return response()->json([
            'data' => $enhancedPositions->values(),
        ]);
    }
}