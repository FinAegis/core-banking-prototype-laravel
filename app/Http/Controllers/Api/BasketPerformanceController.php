<?php

namespace App\Http\Controllers\Api;

use App\Domain\Basket\Services\BasketPerformanceService;
use App\Http\Controllers\Controller;
use App\Http\Resources\BasketPerformanceResource;
use App\Http\Resources\ComponentPerformanceResource;
use App\Models\BasketAsset;
use App\Models\BasketPerformance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * @OA\Tag(
 *     name="Basket Performance",
 *     description="Basket asset performance tracking and analytics"
 * )
 */
class BasketPerformanceController extends Controller
{
    public function __construct(
        private readonly BasketPerformanceService $performanceService
    ) {}

    /**
     * @OA\Get(
     *     path="/api/baskets/{code}/performance",
     *     operationId="getBasketPerformance",
     *     tags={"Basket Performance"},
     *     summary="Get performance metrics for a basket",
     *     @OA\Parameter(
     *         name="code",
     *         in="path",
     *         required=true,
     *         description="Basket asset code",
     *         @OA\Schema(type="string", example="GCU")
     *     ),
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         required=false,
     *         description="Performance period",
     *         @OA\Schema(
     *             type="string",
     *             enum={"hour", "day", "week", "month", "quarter", "year", "all_time"},
     *             default="month"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Performance data",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="basket_code", type="string"),
     *                 @OA\Property(property="period_type", type="string"),
     *                 @OA\Property(property="return_percentage", type="number"),
     *                 @OA\Property(property="volatility", type="number"),
     *                 @OA\Property(property="sharpe_ratio", type="number"),
     *                 @OA\Property(property="max_drawdown", type="number")
     *             )
     *         )
     *     ),
     *     security={{"sanctum": {}}}
     * )
     */
    public function show(Request $request, string $code): JsonResponse
    {
        $request->validate([
            'period' => ['sometimes', Rule::in(['hour', 'day', 'week', 'month', 'quarter', 'year', 'all_time'])],
        ]);

        $basket = BasketAsset::where('code', $code)->firstOrFail();
        $period = $request->get('period', 'month');

        $performance = $basket->performances()
            ->where('period_type', $period)
            ->orderBy('period_end', 'desc')
            ->first();

        if (!$performance) {
            // Try to calculate it
            $now = now();
            [$periodStart, $periodEnd] = match($period) {
                'hour' => [$now->copy()->subHour(), $now],
                'day' => [$now->copy()->subDay(), $now],
                'week' => [$now->copy()->subWeek(), $now],
                'month' => [$now->copy()->subMonth(), $now],
                'quarter' => [$now->copy()->subQuarter(), $now],
                'year' => [$now->copy()->subYear(), $now],
                'all_time' => [
                    $basket->values()->orderBy('calculated_at')->first()?->calculated_at ?? $now->copy()->subYear(),
                    $now
                ],
            };

            $performance = $this->performanceService->calculatePerformance(
                $basket,
                $period,
                $periodStart,
                $periodEnd
            );
        }

        if (!$performance) {
            return response()->json([
                'data' => null,
                'message' => 'Insufficient data to calculate performance',
            ], 404);
        }

        return response()->json([
            'data' => new BasketPerformanceResource($performance),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/baskets/{code}/performance/history",
     *     operationId="getBasketPerformanceHistory",
     *     tags={"Basket Performance"},
     *     summary="Get historical performance data for a basket",
     *     @OA\Parameter(
     *         name="code",
     *         in="path",
     *         required=true,
     *         description="Basket asset code",
     *         @OA\Schema(type="string", example="GCU")
     *     ),
     *     @OA\Parameter(
     *         name="period_type",
     *         in="query",
     *         required=false,
     *         description="Filter by period type",
     *         @OA\Schema(type="string", enum={"hour", "day", "week", "month", "quarter", "year"})
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Number of records to return",
     *         @OA\Schema(type="integer", default=30)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Historical performance data",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(ref="#/components/schemas/BasketPerformance")
     *             )
     *         )
     *     ),
     *     security={{"sanctum": {}}}
     * )
     */
    public function history(Request $request, string $code): AnonymousResourceCollection
    {
        $request->validate([
            'period_type' => ['sometimes', Rule::in(['hour', 'day', 'week', 'month', 'quarter', 'year'])],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:365'],
        ]);

        $basket = BasketAsset::where('code', $code)->firstOrFail();
        
        $query = $basket->performances()
            ->with('componentPerformances')
            ->complete();

        if ($periodType = $request->get('period_type')) {
            $query->where('period_type', $periodType);
        }

        $performances = $query
            ->orderBy('period_end', 'desc')
            ->limit($request->get('limit', 30))
            ->get();

        return BasketPerformanceResource::collection($performances);
    }

    /**
     * @OA\Get(
     *     path="/api/baskets/{code}/performance/summary",
     *     operationId="getBasketPerformanceSummary",
     *     tags={"Basket Performance"},
     *     summary="Get performance summary across multiple periods",
     *     @OA\Parameter(
     *         name="code",
     *         in="path",
     *         required=true,
     *         description="Basket asset code",
     *         @OA\Schema(type="string", example="GCU")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Performance summary",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="basket_code", type="string"),
     *                 @OA\Property(property="basket_name", type="string"),
     *                 @OA\Property(property="current_value", type="number"),
     *                 @OA\Property(property="performances", type="object",
     *                     @OA\Property(property="day", type="object"),
     *                     @OA\Property(property="week", type="object"),
     *                     @OA\Property(property="month", type="object"),
     *                     @OA\Property(property="year", type="object")
     *                 )
     *             )
     *         )
     *     ),
     *     security={{"sanctum": {}}}
     * )
     */
    public function summary(string $code): JsonResponse
    {
        $basket = BasketAsset::where('code', $code)->firstOrFail();
        $summary = $this->performanceService->getPerformanceSummary($basket);

        return response()->json([
            'data' => $summary,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/baskets/{code}/performance/components",
     *     operationId="getBasketComponentPerformance",
     *     tags={"Basket Performance"},
     *     summary="Get component-level performance breakdown",
     *     @OA\Parameter(
     *         name="code",
     *         in="path",
     *         required=true,
     *         description="Basket asset code",
     *         @OA\Schema(type="string", example="GCU")
     *     ),
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         required=false,
     *         description="Performance period",
     *         @OA\Schema(
     *             type="string",
     *             enum={"hour", "day", "week", "month", "quarter", "year"},
     *             default="month"
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Component performance data",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(ref="#/components/schemas/ComponentPerformance")
     *             )
     *         )
     *     ),
     *     security={{"sanctum": {}}}
     * )
     */
    public function components(Request $request, string $code): AnonymousResourceCollection
    {
        $request->validate([
            'period' => ['sometimes', Rule::in(['hour', 'day', 'week', 'month', 'quarter', 'year'])],
        ]);

        $basket = BasketAsset::where('code', $code)->firstOrFail();
        $period = $request->get('period', 'month');

        $performance = $basket->performances()
            ->where('period_type', $period)
            ->orderBy('period_end', 'desc')
            ->first();

        if (!$performance) {
            return ComponentPerformanceResource::collection(collect());
        }

        $components = $performance->componentPerformances()
            ->orderBy('contribution_percentage', 'desc')
            ->get();

        return ComponentPerformanceResource::collection($components);
    }

    /**
     * @OA\Get(
     *     path="/api/baskets/{code}/performance/top-performers",
     *     operationId="getBasketTopPerformers",
     *     tags={"Basket Performance"},
     *     summary="Get top performing components",
     *     @OA\Parameter(
     *         name="code",
     *         in="path",
     *         required=true,
     *         description="Basket asset code",
     *         @OA\Schema(type="string", example="GCU")
     *     ),
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         required=false,
     *         description="Performance period",
     *         @OA\Schema(type="string", default="month")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Number of components to return",
     *         @OA\Schema(type="integer", default=5)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Top performing components"
     *     ),
     *     security={{"sanctum": {}}}
     * )
     */
    public function topPerformers(Request $request, string $code): AnonymousResourceCollection
    {
        $request->validate([
            'period' => ['sometimes', 'string'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);

        $basket = BasketAsset::where('code', $code)->firstOrFail();
        $performers = $this->performanceService->getTopPerformers(
            $basket,
            $request->get('period', 'month'),
            $request->get('limit', 5)
        );

        return ComponentPerformanceResource::collection($performers);
    }

    /**
     * @OA\Get(
     *     path="/api/baskets/{code}/performance/worst-performers",
     *     operationId="getBasketWorstPerformers",
     *     tags={"Basket Performance"},
     *     summary="Get worst performing components",
     *     @OA\Parameter(
     *         name="code",
     *         in="path",
     *         required=true,
     *         description="Basket asset code",
     *         @OA\Schema(type="string", example="GCU")
     *     ),
     *     @OA\Parameter(
     *         name="period",
     *         in="query",
     *         required=false,
     *         description="Performance period",
     *         @OA\Schema(type="string", default="month")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         required=false,
     *         description="Number of components to return",
     *         @OA\Schema(type="integer", default=5)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Worst performing components"
     *     ),
     *     security={{"sanctum": {}}}
     * )
     */
    public function worstPerformers(Request $request, string $code): AnonymousResourceCollection
    {
        $request->validate([
            'period' => ['sometimes', 'string'],
            'limit' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ]);

        $basket = BasketAsset::where('code', $code)->firstOrFail();
        $performers = $this->performanceService->getWorstPerformers(
            $basket,
            $request->get('period', 'month'),
            $request->get('limit', 5)
        );

        return ComponentPerformanceResource::collection($performers);
    }

    /**
     * @OA\Post(
     *     path="/api/baskets/{code}/performance/calculate",
     *     operationId="calculateBasketPerformance",
     *     tags={"Basket Performance"},
     *     summary="Trigger performance calculation for a basket",
     *     @OA\Parameter(
     *         name="code",
     *         in="path",
     *         required=true,
     *         description="Basket asset code",
     *         @OA\Schema(type="string", example="GCU")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="period", type="string", enum={"all", "hour", "day", "week", "month", "quarter", "year"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Calculation triggered",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string"),
     *             @OA\Property(property="calculated_periods", type="array", @OA\Items(type="string"))
     *         )
     *     ),
     *     security={{"sanctum": {}}}
     * )
     */
    public function calculate(Request $request, string $code): JsonResponse
    {
        $request->validate([
            'period' => ['sometimes', Rule::in(['all', 'hour', 'day', 'week', 'month', 'quarter', 'year'])],
        ]);

        $basket = BasketAsset::where('code', $code)->firstOrFail();
        $period = $request->get('period', 'all');

        if ($period === 'all') {
            $performances = $this->performanceService->calculateAllPeriods($basket);
            $calculatedPeriods = $performances->pluck('period_type')->toArray();
        } else {
            $now = now();
            [$periodStart, $periodEnd] = match($period) {
                'hour' => [$now->copy()->subHour(), $now],
                'day' => [$now->copy()->subDay(), $now],
                'week' => [$now->copy()->subWeek(), $now],
                'month' => [$now->copy()->subMonth(), $now],
                'quarter' => [$now->copy()->subQuarter(), $now],
                'year' => [$now->copy()->subYear(), $now],
            };

            $performance = $this->performanceService->calculatePerformance(
                $basket,
                $period,
                $periodStart,
                $periodEnd
            );

            $calculatedPeriods = $performance ? [$period] : [];
        }

        return response()->json([
            'message' => 'Performance calculation completed',
            'calculated_periods' => $calculatedPeriods,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/baskets/{code}/performance/compare",
     *     operationId="compareBasketPerformance",
     *     tags={"Basket Performance"},
     *     summary="Compare basket performance against benchmarks",
     *     @OA\Parameter(
     *         name="code",
     *         in="path",
     *         required=true,
     *         description="Basket asset code",
     *         @OA\Schema(type="string", example="GCU")
     *     ),
     *     @OA\Parameter(
     *         name="benchmarks",
     *         in="query",
     *         required=true,
     *         description="Comma-separated list of benchmark basket codes",
     *         @OA\Schema(type="string", example="USD_STABLE,EUR_STABLE")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Performance comparison data"
     *     ),
     *     security={{"sanctum": {}}}
     * )
     */
    public function compare(Request $request, string $code): JsonResponse
    {
        $request->validate([
            'benchmarks' => ['required', 'string'],
        ]);

        $basket = BasketAsset::where('code', $code)->firstOrFail();
        $benchmarkCodes = array_map('trim', explode(',', $request->get('benchmarks')));

        $comparison = $this->performanceService->compareToBenchmarks($basket, $benchmarkCodes);

        return response()->json([
            'data' => $comparison,
        ]);
    }
}