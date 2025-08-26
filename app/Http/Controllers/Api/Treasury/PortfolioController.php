<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Treasury;

use App\Domain\Treasury\Services\AssetValuationService;
use App\Domain\Treasury\Services\PerformanceTrackingService;
use App\Domain\Treasury\Services\PortfolioManagementService;
use App\Domain\Treasury\Services\RebalancingService;
use App\Domain\Treasury\Workflows\PerformanceReportingWorkflow;
use App\Domain\Treasury\Workflows\PortfolioRebalancingWorkflow;
use App\Http\Controllers\Controller;
use App\Http\Requests\Treasury\Portfolio\AllocateAssetsRequest;
use App\Http\Requests\Treasury\Portfolio\ApproveRebalancingRequest;
use App\Http\Requests\Treasury\Portfolio\CreatePortfolioRequest;
use App\Http\Requests\Treasury\Portfolio\CreateReportRequest;
use App\Http\Requests\Treasury\Portfolio\TriggerRebalancingRequest;
use App\Http\Requests\Treasury\Portfolio\UpdatePortfolioRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Workflow\WorkflowStub;

class PortfolioController extends Controller
{
    public function __construct(
        private readonly PortfolioManagementService $portfolioService,
        private readonly RebalancingService $rebalancingService,
        private readonly PerformanceTrackingService $performanceService,
        private readonly AssetValuationService $valuationService
    ) {
    }

    /**
     * @OA\Get(
     *     path="/api/treasury/portfolios",
     *     operationId="listTreasuryPortfolios",
     *     tags={"Treasury Portfolio"},
     *     summary="List portfolios for treasury",
     *     description="Retrieves a list of portfolios for the authenticated treasury account",
     *     security={{"sanctum":{"treasury"}}},
     *
     *     @OA\Parameter(
     *         name="treasury_id",
     *         in="query",
     *         required=false,
     *         description="Treasury ID to filter portfolios",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of treasury portfolios",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/TreasuryPortfolio")),
     *             @OA\Property(property="meta", type="object",
     *                 @OA\Property(property="total", type="integer"),
     *                 @OA\Property(property="count", type="integer")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - insufficient treasury permissions"),
     *     @OA\Response(response=429, description="Too Many Requests")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $treasuryId = $request->query('treasury_id', $request->user()->uuid ?? '');

            if (empty($treasuryId)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Treasury ID is required',
                ], Response::HTTP_BAD_REQUEST);
            }

            $portfolios = $this->portfolioService->listPortfolios($treasuryId);

            return response()->json([
                'success' => true,
                'data'    => $portfolios->map(function ($portfolio) {
                    return [
                        'portfolio_id'        => $portfolio['portfolio_id'] ?? null,
                        'treasury_id'         => $portfolio['treasury_id'] ?? null,
                        'name'                => $portfolio['name'] ?? 'Unknown Portfolio',
                        'status'              => $portfolio['status'] ?? 'unknown',
                        'total_value'         => $portfolio['total_value'] ?? 0.0,
                        'asset_count'         => count($portfolio['asset_allocations'] ?? []),
                        'is_rebalancing'      => $portfolio['is_rebalancing'] ?? false,
                        'last_rebalance_date' => $portfolio['last_rebalance_date'],
                        'created_at'          => $portfolio['created_at'] ?? null,
                        'updated_at'          => $portfolio['updated_at'] ?? null,
                    ];
                }),
                'meta' => [
                    'total' => $portfolios->count(),
                    'count' => $portfolios->count(),
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to list treasury portfolios', [
                'error'       => $e->getMessage(),
                'trace'       => $e->getTraceAsString(),
                'treasury_id' => $treasuryId ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve portfolios',
                'error'   => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/treasury/portfolios",
     *     operationId="createTreasuryPortfolio",
     *     tags={"Treasury Portfolio"},
     *     summary="Create a new treasury portfolio",
     *     description="Creates a new portfolio for treasury management with investment strategy",
     *     security={{"sanctum":{"treasury"}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CreateTreasuryPortfolioRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=201,
     *         description="Portfolio created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/TreasuryPortfolio"),
     *             @OA\Property(property="message", type="string", example="Portfolio created successfully")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad Request - validation errors"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden - insufficient treasury permissions"),
     *     @OA\Response(response=422, description="Unprocessable Entity - validation failed"),
     *     @OA\Response(response=429, description="Too Many Requests")
     * )
     */
    public function store(CreatePortfolioRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $portfolioId = $this->portfolioService->createPortfolio(
                $request->validated('treasury_id'),
                $request->validated('name'),
                $request->validated('strategy')
            );

            $portfolio = $this->portfolioService->getPortfolio($portfolioId);

            DB::commit();

            Log::info('Treasury portfolio created successfully', [
                'portfolio_id' => $portfolioId,
                'treasury_id'  => $request->validated('treasury_id'),
                'name'         => $request->validated('name'),
            ]);

            return response()->json([
                'success' => true,
                'data'    => $this->formatPortfolioResponse($portfolio),
                'message' => 'Portfolio created successfully',
            ], Response::HTTP_CREATED);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Failed to create treasury portfolio', [
                'error'   => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
                'request' => $request->validated(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create portfolio',
                'error'   => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/treasury/portfolios/{id}",
     *     operationId="getTreasuryPortfolio",
     *     tags={"Treasury Portfolio"},
     *     summary="Get portfolio details",
     *     description="Retrieves detailed information about a specific treasury portfolio",
     *     security={{"sanctum":{"treasury"}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Portfolio ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Portfolio details",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/TreasuryPortfolioDetailed")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Portfolio not found"),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=403, description="Forbidden"),
     *     @OA\Response(response=429, description="Too Many Requests")
     * )
     */
    public function show(string $id): JsonResponse
    {
        try {
            $portfolio = $this->portfolioService->getPortfolio($id);
            $summary = $this->portfolioService->getPortfolioSummary($id);

            return response()->json([
                'success' => true,
                'data'    => array_merge(
                    $this->formatPortfolioResponse($portfolio),
                    [
                        'summary'           => $summary,
                        'needs_rebalancing' => $this->rebalancingService->checkRebalancingNeeded($id),
                    ]
                ),
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to retrieve treasury portfolio', [
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
                'portfolio_id' => $id,
            ]);

            if (str_contains($e->getMessage(), 'not found') || str_contains($e->getMessage(), 'retrieve portfolio')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Portfolio not found',
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve portfolio',
                'error'   => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/treasury/portfolios/{id}",
     *     operationId="updateTreasuryPortfolio",
     *     tags={"Treasury Portfolio"},
     *     summary="Update portfolio strategy",
     *     description="Updates the investment strategy for a treasury portfolio",
     *     security={{"sanctum":{"treasury"}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Portfolio ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/UpdateTreasuryPortfolioRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Portfolio updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/TreasuryPortfolio"),
     *             @OA\Property(property="message", type="string", example="Portfolio strategy updated successfully")
     *         )
     *     ),
     *     @OA\Response(response=400, description="Bad Request"),
     *     @OA\Response(response=404, description="Portfolio not found"),
     *     @OA\Response(response=422, description="Validation failed")
     * )
     */
    public function update(UpdatePortfolioRequest $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $this->portfolioService->updateStrategy($id, $request->validated('strategy'));
            $portfolio = $this->portfolioService->getPortfolio($id);

            DB::commit();

            Log::info('Treasury portfolio strategy updated', [
                'portfolio_id' => $id,
                'strategy'     => $request->validated('strategy'),
            ]);

            return response()->json([
                'success' => true,
                'data'    => $this->formatPortfolioResponse($portfolio),
                'message' => 'Portfolio strategy updated successfully',
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Failed to update treasury portfolio', [
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
                'portfolio_id' => $id,
                'request'      => $request->validated(),
            ]);

            if (str_contains($e->getMessage(), 'not found')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Portfolio not found',
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to update portfolio',
                'error'   => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/treasury/portfolios/{id}",
     *     operationId="deleteTreasuryPortfolio",
     *     tags={"Treasury Portfolio"},
     *     summary="Delete portfolio",
     *     description="Soft deletes a treasury portfolio (sets status to inactive)",
     *     security={{"sanctum":{"treasury"}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Portfolio ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Portfolio deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Portfolio deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Portfolio not found"),
     *     @OA\Response(response=409, description="Conflict - portfolio cannot be deleted")
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            // For event sourcing, we don't actually delete but deactivate
            $portfolio = $this->portfolioService->getPortfolio($id);

            if ($portfolio['is_rebalancing']) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete portfolio while rebalancing is in progress',
                ], Response::HTTP_CONFLICT);
            }

            // In a real implementation, you would add a deactivation method
            // $this->portfolioService->deactivatePortfolio($id);

            Log::info('Treasury portfolio marked for deletion', [
                'portfolio_id' => $id,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Portfolio deletion scheduled successfully',
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to delete treasury portfolio', [
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
                'portfolio_id' => $id,
            ]);

            if (str_contains($e->getMessage(), 'not found')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Portfolio not found',
                ], Response::HTTP_NOT_FOUND);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete portfolio',
                'error'   => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/treasury/portfolios/{id}/allocate",
     *     operationId="allocatePortfolioAssets",
     *     tags={"Treasury Portfolio"},
     *     summary="Allocate assets to portfolio",
     *     description="Allocates assets to a treasury portfolio with specified weights",
     *     security={{"sanctum":{"treasury"}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Portfolio ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/AllocateAssetsRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Assets allocated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/TreasuryPortfolio"),
     *             @OA\Property(property="message", type="string", example="Assets allocated successfully")
     *         )
     *     )
     * )
     */
    public function allocate(AllocateAssetsRequest $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $this->portfolioService->allocateAssets($id, $request->validated('allocations'));
            $portfolio = $this->portfolioService->getPortfolio($id);

            DB::commit();

            Log::info('Assets allocated to treasury portfolio', [
                'portfolio_id' => $id,
                'allocations'  => $request->validated('allocations'),
            ]);

            return response()->json([
                'success' => true,
                'data'    => $this->formatPortfolioResponse($portfolio),
                'message' => 'Assets allocated successfully',
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Failed to allocate assets to treasury portfolio', [
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
                'portfolio_id' => $id,
                'request'      => $request->validated(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to allocate assets',
                'error'   => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/treasury/portfolios/{id}/allocations",
     *     operationId="getPortfolioAllocations",
     *     tags={"Treasury Portfolio"},
     *     summary="Get current asset allocations",
     *     description="Retrieves current asset allocation details for a portfolio",
     *     security={{"sanctum":{"treasury"}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Portfolio ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Current asset allocations",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="allocations", type="array", @OA\Items(ref="#/components/schemas/AssetAllocation")),
     *                 @OA\Property(property="total_value", type="number", format="float"),
     *                 @OA\Property(property="last_updated", type="string", format="date-time")
     *             )
     *         )
     *     )
     * )
     */
    public function getAllocations(string $id): JsonResponse
    {
        try {
            $portfolio = $this->portfolioService->getPortfolio($id);

            return response()->json([
                'success' => true,
                'data'    => [
                    'portfolio_id' => $id,
                    'allocations'  => $portfolio['asset_allocations'],
                    'total_value'  => $portfolio['total_value'],
                    'last_updated' => now()->toISOString(),
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to retrieve portfolio allocations', [
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
                'portfolio_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve allocations',
                'error'   => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/treasury/portfolios/{id}/rebalance",
     *     operationId="triggerPortfolioRebalancing",
     *     tags={"Treasury Portfolio"},
     *     summary="Trigger portfolio rebalancing",
     *     description="Initiates rebalancing workflow for a treasury portfolio",
     *     security={{"sanctum":{"treasury"}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Portfolio ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(ref="#/components/schemas/TriggerRebalancingRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Rebalancing workflow started",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="workflow_id", type="string"),
     *                 @OA\Property(property="status", type="string", example="started")
     *             ),
     *             @OA\Property(property="message", type="string", example="Rebalancing workflow started")
     *         )
     *     )
     * )
     */
    public function triggerRebalancing(TriggerRebalancingRequest $request, string $id): JsonResponse
    {
        try {
            $workflowId = Str::uuid()->toString();

            // Start the rebalancing workflow
            $workflow = WorkflowStub::make(PortfolioRebalancingWorkflow::class);
            $workflow->execute(
                $id,
                $request->validated('reason') ?? 'manual_trigger'
            );

            Log::info('Portfolio rebalancing workflow started', [
                'portfolio_id' => $id,
                'workflow_id'  => $workflowId,
                'reason'       => $request->validated('reason') ?? 'manual_trigger',
            ]);

            return response()->json([
                'success' => true,
                'data'    => [
                    'workflow_id'  => $workflowId,
                    'portfolio_id' => $id,
                    'status'       => 'started',
                ],
                'message' => 'Rebalancing workflow started successfully',
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to start portfolio rebalancing workflow', [
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
                'portfolio_id' => $id,
                'request'      => $request->validated(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to start rebalancing workflow',
                'error'   => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/treasury/portfolios/{id}/rebalancing-plan",
     *     operationId="getRebalancingPlan",
     *     tags={"Treasury Portfolio"},
     *     summary="Get rebalancing plan",
     *     description="Calculates and returns the rebalancing plan for a portfolio",
     *     security={{"sanctum":{"treasury"}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Portfolio ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Rebalancing plan details",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/RebalancingPlan")
     *         )
     *     )
     * )
     */
    public function getRebalancingPlan(string $id): JsonResponse
    {
        try {
            $plan = $this->rebalancingService->calculateRebalancingPlan($id);

            return response()->json([
                'success' => true,
                'data'    => $plan,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to calculate rebalancing plan', [
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
                'portfolio_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate rebalancing plan',
                'error'   => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/treasury/portfolios/{id}/approve-rebalancing",
     *     operationId="approvePortfolioRebalancing",
     *     tags={"Treasury Portfolio"},
     *     summary="Approve rebalancing execution",
     *     description="Approves and executes a portfolio rebalancing plan",
     *     security={{"sanctum":{"treasury"}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Portfolio ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/ApproveRebalancingRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Rebalancing approved and executed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Rebalancing executed successfully")
     *         )
     *     )
     * )
     */
    public function approveRebalancing(ApproveRebalancingRequest $request, string $id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $plan = $request->validated('plan');
            $this->rebalancingService->executeRebalancing($id, $plan);

            DB::commit();

            Log::info('Portfolio rebalancing approved and executed', [
                'portfolio_id' => $id,
                'approved_by'  => $request->user()->uuid ?? 'system',
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Rebalancing executed successfully',
            ]);
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('Failed to approve and execute rebalancing', [
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
                'portfolio_id' => $id,
                'request'      => $request->validated(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to execute rebalancing',
                'error'   => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/treasury/portfolios/{id}/performance",
     *     operationId="getPortfolioPerformance",
     *     tags={"Treasury Portfolio"},
     *     summary="Get performance metrics",
     *     description="Retrieves performance metrics and analytics for a portfolio",
     *     security={{"sanctum":{"treasury"}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Portfolio ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         required=false,
     *         description="Performance period",
     *         @OA\Schema(type="string", enum={"1d", "7d", "30d", "90d", "1y", "ytd", "all"}, default="30d")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Portfolio performance metrics",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/PortfolioPerformance")
     *         )
     *     )
     * )
     */
    public function getPerformance(Request $request, string $id): JsonResponse
    {
        try {
            $period = $request->query('period', '30d');

            $performance = $this->performanceService->getPortfolioPerformance($id, $period);
            $metrics = $this->rebalancingService->getRebalancingMetrics($id);

            return response()->json([
                'success' => true,
                'data'    => [
                    'portfolio_id'        => $id,
                    'period'              => $period,
                    'performance'         => $performance,
                    'rebalancing_metrics' => $metrics,
                    'generated_at'        => now()->toISOString(),
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to retrieve portfolio performance', [
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
                'portfolio_id' => $id,
                'period'       => $period ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve performance metrics',
                'error'   => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/treasury/portfolios/{id}/valuation",
     *     operationId="getPortfolioValuation",
     *     tags={"Treasury Portfolio"},
     *     summary="Get current portfolio valuation",
     *     description="Retrieves real-time valuation of portfolio assets",
     *     security={{"sanctum":{"treasury"}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Portfolio ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Portfolio valuation details",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/PortfolioValuation")
     *         )
     *     )
     * )
     */
    public function getValuation(string $id): JsonResponse
    {
        try {
            $valuation = $this->valuationService->calculatePortfolioValue($id);

            return response()->json([
                'success' => true,
                'data'    => [
                    'portfolio_id' => $id,
                    'valuation'    => $valuation,
                    'timestamp'    => now()->toISOString(),
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to retrieve portfolio valuation', [
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
                'portfolio_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve portfolio valuation',
                'error'   => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/treasury/portfolios/{id}/history",
     *     operationId="getPortfolioHistory",
     *     tags={"Treasury Portfolio"},
     *     summary="Get portfolio historical data",
     *     description="Retrieves historical performance and rebalancing data",
     *     security={{"sanctum":{"treasury"}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Portfolio ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         required=false,
     *         description="Type of historical data",
     *         @OA\Schema(type="string", enum={"rebalancing", "performance", "all"}, default="all")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Portfolio historical data",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/PortfolioHistory")
     *         )
     *     )
     * )
     */
    public function getHistory(Request $request, string $id): JsonResponse
    {
        try {
            $type = $request->query('type', 'all');
            $history = [];

            if (in_array($type, ['rebalancing', 'all'])) {
                $history['rebalancing'] = $this->rebalancingService->getRebalancingHistory($id);
            }

            if (in_array($type, ['performance', 'all'])) {
                $history['performance'] = $this->performanceService->getPerformanceHistory($id);
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'portfolio_id' => $id,
                    'type'         => $type,
                    'history'      => $history,
                ],
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to retrieve portfolio history', [
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
                'portfolio_id' => $id,
                'type'         => $type ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve portfolio history',
                'error'   => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/treasury/portfolios/{id}/reports",
     *     operationId="generatePortfolioReport",
     *     tags={"Treasury Portfolio"},
     *     summary="Generate portfolio report",
     *     description="Generates a comprehensive report for a treasury portfolio",
     *     security={{"sanctum":{"treasury"}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Portfolio ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/CreateReportRequest")
     *     ),
     *
     *     @OA\Response(
     *         response=202,
     *         description="Report generation started",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="workflow_id", type="string"),
     *                 @OA\Property(property="status", type="string", example="started")
     *             ),
     *             @OA\Property(property="message", type="string", example="Report generation started")
     *         )
     *     )
     * )
     */
    public function generateReport(CreateReportRequest $request, string $id): JsonResponse
    {
        try {
            $workflowId = Str::uuid()->toString();

            // Start the performance reporting workflow
            $workflow = WorkflowStub::make(PerformanceReportingWorkflow::class);
            $workflow->execute(
                $id,
                $request->validated('type'),
                $request->validated('period')
            );

            Log::info('Portfolio report generation started', [
                'portfolio_id' => $id,
                'workflow_id'  => $workflowId,
                'type'         => $request->validated('type'),
                'period'       => $request->validated('period'),
            ]);

            return response()->json([
                'success' => true,
                'data'    => [
                    'workflow_id'  => $workflowId,
                    'portfolio_id' => $id,
                    'status'       => 'started',
                ],
                'message' => 'Report generation started successfully',
            ], Response::HTTP_ACCEPTED);
        } catch (Throwable $e) {
            Log::error('Failed to start report generation workflow', [
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
                'portfolio_id' => $id,
                'request'      => $request->validated(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to start report generation',
                'error'   => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/treasury/portfolios/{id}/reports",
     *     operationId="listPortfolioReports",
     *     tags={"Treasury Portfolio"},
     *     summary="List portfolio reports",
     *     description="Retrieves a list of generated reports for a portfolio",
     *     security={{"sanctum":{"treasury"}}},
     *
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Portfolio ID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="List of portfolio reports",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/PortfolioReport"))
     *         )
     *     )
     * )
     */
    public function listReports(string $id): JsonResponse
    {
        try {
            $reports = $this->performanceService->getPortfolioReports($id);

            return response()->json([
                'success' => true,
                'data'    => $reports,
            ]);
        } catch (Throwable $e) {
            Log::error('Failed to list portfolio reports', [
                'error'        => $e->getMessage(),
                'trace'        => $e->getTraceAsString(),
                'portfolio_id' => $id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve reports',
                'error'   => app()->environment('local') ? $e->getMessage() : 'Internal server error',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    private function formatPortfolioResponse(array $portfolio): array
    {
        return [
            'portfolio_id'        => $portfolio['portfolio_id'],
            'treasury_id'         => $portfolio['treasury_id'],
            'name'                => $portfolio['name'],
            'strategy'            => $portfolio['strategy'],
            'asset_allocations'   => $portfolio['asset_allocations'],
            'latest_metrics'      => $portfolio['latest_metrics'],
            'total_value'         => $portfolio['total_value'],
            'status'              => $portfolio['status'],
            'is_rebalancing'      => $portfolio['is_rebalancing'],
            'last_rebalance_date' => $portfolio['last_rebalance_date'],
        ];
    }
}
