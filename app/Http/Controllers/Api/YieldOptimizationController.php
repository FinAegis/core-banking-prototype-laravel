<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Treasury\Services\PortfolioManagementService;
use App\Domain\Treasury\Services\YieldOptimizationService;
use App\Domain\Treasury\ValueObjects\RiskProfile;
use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class YieldOptimizationController extends Controller
{
    public function __construct(
        private readonly YieldOptimizationService $yieldOptimizationService,
        private readonly PortfolioManagementService $portfolioManagementService,
    ) {
    }

    /**
     * Optimize portfolio for yield.
     *
     * @OA\Post(
     *     path="/api/v2/treasury/optimize",
     *     summary="Optimize portfolio for yield",
     *     tags={"Treasury"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"account_id", "total_amount", "target_yield", "risk_level"},
     *             @OA\Property(property="account_id", type="string", format="uuid"),
     *             @OA\Property(property="total_amount", type="number", format="float", minimum=10000),
     *             @OA\Property(property="target_yield", type="number", format="float", minimum=0, maximum=20),
     *             @OA\Property(property="risk_level", type="string", enum={"low", "medium", "high", "very_high"}),
     *             @OA\Property(property="constraints", type="object",
     *                 @OA\Property(property="min_liquidity", type="number", format="float"),
     *                 @OA\Property(property="max_concentration", type="number", format="float")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Portfolio optimization result",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function optimizePortfolio(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_id'                    => 'required|string|uuid',
            'total_amount'                  => 'required|numeric|min:10000',
            'target_yield'                  => 'required|numeric|min:0|max:20',
            'risk_level'                    => 'required|string|in:low,medium,high,very_high',
            'constraints'                   => 'nullable|array',
            'constraints.min_liquidity'     => 'nullable|numeric|min:0|max:1',
            'constraints.max_concentration' => 'nullable|numeric|min:0|max:1',
        ]);

        try {
            $riskProfile = RiskProfile::fromScore(
                $this->mapRiskLevelToScore($validated['risk_level'])
            );

            $result = $this->yieldOptimizationService->optimizePortfolio(
                accountId: $validated['account_id'],
                totalAmount: (float) $validated['total_amount'],
                targetYield: (float) $validated['target_yield'],
                riskProfile: $riskProfile,
                constraints: $validated['constraints'] ?? []
            );

            return response()->json([
                'message' => 'Portfolio optimization completed',
                'data'    => $result,
            ]);
        } catch (Exception $e) {
            Log::error('Portfolio optimization failed', [
                'error'      => $e->getMessage(),
                'account_id' => $validated['account_id'],
            ]);

            return response()->json([
                'message' => 'Portfolio optimization failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get portfolio details.
     *
     * @OA\Get(
     *     path="/api/v2/treasury/{treasuryId}/portfolio",
     *     summary="Get portfolio details",
     *     tags={"Treasury"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="treasuryId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Portfolio details",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Portfolio not found")
     * )
     */
    public function getPortfolio(Request $request, string $treasuryId): JsonResponse
    {
        try {
            $portfolio = $this->portfolioManagementService->getPortfolio($treasuryId);

            return response()->json([
                'message' => 'Portfolio retrieved successfully',
                'data'    => $portfolio,
            ]);
        } catch (\RuntimeException $e) {
            Log::warning('Portfolio not found', [
                'treasury_id' => $treasuryId,
                'error'       => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Portfolio not found',
                'data'    => [
                    'treasury_id' => $treasuryId,
                ],
            ], 404);
        } catch (Exception $e) {
            Log::error('Portfolio retrieval failed', [
                'error'       => $e->getMessage(),
                'treasury_id' => $treasuryId,
            ]);

            return response()->json([
                'message' => 'Portfolio retrieval failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get portfolio summary for a treasury.
     *
     * @OA\Get(
     *     path="/api/v2/treasury/{treasuryId}/summary",
     *     summary="Get portfolio summary",
     *     tags={"Treasury"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="treasuryId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Portfolio summary",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     )
     * )
     */
    public function getPortfolioSummary(Request $request, string $treasuryId): JsonResponse
    {
        try {
            $summary = $this->portfolioManagementService->getPortfolioSummary($treasuryId);

            return response()->json([
                'message' => 'Portfolio summary retrieved',
                'data'    => $summary,
            ]);
        } catch (Exception $e) {
            Log::error('Portfolio summary retrieval failed', [
                'error'       => $e->getMessage(),
                'treasury_id' => $treasuryId,
            ]);

            return response()->json([
                'message' => 'Portfolio summary retrieval failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if portfolio needs rebalancing.
     *
     * @OA\Get(
     *     path="/api/v2/treasury/{treasuryId}/rebalance-check",
     *     summary="Check if portfolio needs rebalancing",
     *     tags={"Treasury"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="treasuryId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Rebalancing check result",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="needs_rebalancing", type="boolean"),
     *                 @OA\Property(property="treasury_id", type="string")
     *             )
     *         )
     *     )
     * )
     */
    public function checkRebalancing(Request $request, string $treasuryId): JsonResponse
    {
        try {
            $portfolio = $this->portfolioManagementService->getPortfolio($treasuryId);
            $needsRebalancing = $this->portfolioManagementService->needsRebalancing($portfolio);

            return response()->json([
                'message' => 'Rebalancing check completed',
                'data'    => [
                    'needs_rebalancing' => $needsRebalancing,
                    'treasury_id'       => $treasuryId,
                ],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'message' => 'Portfolio not found',
                'data'    => [
                    'treasury_id' => $treasuryId,
                ],
            ], 404);
        } catch (Exception $e) {
            Log::error('Rebalancing check failed', [
                'error'       => $e->getMessage(),
                'treasury_id' => $treasuryId,
            ]);

            return response()->json([
                'message' => 'Rebalancing check failed',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Map risk level string to score.
     */
    private function mapRiskLevelToScore(string $level): float
    {
        return match ($level) {
            'low'       => 20.0,
            'medium'    => 45.0,
            'high'      => 70.0,
            'very_high' => 90.0,
            default     => 45.0,
        };
    }
}
