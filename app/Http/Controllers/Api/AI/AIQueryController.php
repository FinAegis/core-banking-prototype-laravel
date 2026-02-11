<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\AI;

use App\Domain\AI\Services\NaturalLanguageProcessorService;
use App\Domain\AI\Services\TransactionQueryAnalyzerService;
use App\Http\Controllers\Controller;
use App\Http\Requests\AI\SpendingAnalysisRequest;
use App\Http\Requests\AI\TransactionQueryRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="AI Query",
 *     description="AI-powered natural language transaction queries and spending analysis"
 * )
 */
class AIQueryController extends Controller
{
    public function __construct(
        private readonly NaturalLanguageProcessorService $nlpService,
        private readonly TransactionQueryAnalyzerService $analyzerService
    ) {
    }

    /**
     * Query transactions using natural language or structured filters.
     *
     * POST /api/ai/query/transactions
     *
     * @OA\Post(
     *     path="/api/ai/query/transactions",
     *     operationId="aiQueryTransactions",
     *     summary="Query transactions using natural language or structured filters",
     *     description="Accepts a natural language query and/or structured filters to search transactions. The NLP engine parses intent and entities from the query, builds filters, and returns matching transactions with a natural language summary.",
     *     tags={"AI Query"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="query", type="string", nullable=true, example="Show me all payments over $500 last month", description="Natural language query (max 500 chars)"),
     *             @OA\Property(property="account_uuid", type="string", format="uuid", nullable=true, example="550e8400-e29b-41d4-a716-446655440000", description="Filter by specific account UUID"),
     *             @OA\Property(property="date_from", type="string", format="date", nullable=true, example="2025-01-01", description="Start date filter"),
     *             @OA\Property(property="date_to", type="string", format="date", nullable=true, example="2025-01-31", description="End date filter (must be >= date_from)"),
     *             @OA\Property(property="amount_min", type="number", nullable=true, example=100.00, description="Minimum amount filter"),
     *             @OA\Property(property="amount_max", type="number", nullable=true, example=5000.00, description="Maximum amount filter (must be >= amount_min)"),
     *             @OA\Property(property="category", type="string", nullable=true, example="transfer", description="Transaction category filter (max 50 chars)"),
     *             @OA\Property(property="asset_code", type="string", nullable=true, example="USD", description="Asset code filter (3-10 uppercase letters)"),
     *             @OA\Property(property="limit", type="integer", nullable=true, example=20, description="Max results (1-100)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transaction query results",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="transactions", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="summary", type="object", description="Aggregated summary of results"),
     *                 @OA\Property(property="total_count", type="integer", example=15),
     *                 @OA\Property(property="nl_summary", type="string", example="Found 15 transactions totaling $7,500 in the last month"),
     *                 @OA\Property(property="query_parsed", type="object", nullable=true,
     *                     @OA\Property(property="intent", type="string", example="search_transactions"),
     *                     @OA\Property(property="confidence", type="number", example=0.95),
     *                     @OA\Property(property="explanation", type="string", example="Searching for payments over $500")
     *                 ),
     *                 @OA\Property(property="filters_used", type="object", description="Filters applied to the query")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="UNAUTHORIZED"),
     *                 @OA\Property(property="message", type="string", example="Unauthenticated.")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="VALIDATION_ERROR"),
     *                 @OA\Property(property="message", type="string", example="The given data was invalid.")
     *             )
     *         )
     *     )
     * )
     */
    public function transactions(TransactionQueryRequest $request): JsonResponse
    {
        $validated = $request->validated();

        Log::info('AI Query: Transaction query', [
            'user_id' => $request->user()?->id,
            'query'   => $validated['query'] ?? null,
        ]);

        $filters = [];
        $parsed = null;

        // Parse natural language query
        if (! empty($validated['query'])) {
            $parsed = $this->nlpService->processQuery($validated['query'], [
                'user_id' => $request->user()?->id,
            ]);
            $filters = $this->analyzerService->buildFiltersFromEntities($parsed['entities'] ?? []);
        }

        // Merge explicit filters (override NL-parsed)
        foreach (['date_from', 'date_to', 'amount_min', 'amount_max', 'category', 'asset_code'] as $key) {
            if (isset($validated[$key])) {
                $filters[$key] = $validated[$key];
            }
        }

        $accountUuid = $validated['account_uuid'] ?? null;
        $queryResult = $this->analyzerService->executeQuery($filters, $accountUuid);

        $nlSummary = $this->analyzerService->generateNaturalLanguageSummary(
            $queryResult,
            $validated['query'] ?? 'transaction query'
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'transactions' => $queryResult['transactions'],
                'summary'      => $queryResult['summary'],
                'total_count'  => $queryResult['total_count'],
                'nl_summary'   => $nlSummary,
                'query_parsed' => $parsed ? [
                    'intent'      => $parsed['intent'] ?? null,
                    'confidence'  => $parsed['confidence'] ?? null,
                    'explanation' => $parsed['explanation'] ?? null,
                ] : null,
                'filters_used' => $queryResult['filters'],
            ],
        ]);
    }

    /**
     * Analyze spending patterns by category, merchant, and time.
     *
     * POST /api/ai/query/spending-analysis
     *
     * @OA\Post(
     *     path="/api/ai/query/spending-analysis",
     *     operationId="aiQuerySpendingAnalysis",
     *     summary="Analyze spending patterns by category, merchant, and time",
     *     description="Uses AI to analyze spending patterns from transaction history. Accepts natural language queries or structured filters to segment spending by category, merchant, and time period.",
     *     tags={"AI Query"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="query", type="string", nullable=true, example="What did I spend on food this quarter?", description="Natural language query (max 500 chars)"),
     *             @OA\Property(property="account_uuid", type="string", format="uuid", nullable=true, example="550e8400-e29b-41d4-a716-446655440000", description="Filter by specific account UUID"),
     *             @OA\Property(property="date_from", type="string", format="date", nullable=true, example="2025-01-01", description="Start date filter"),
     *             @OA\Property(property="date_to", type="string", format="date", nullable=true, example="2025-03-31", description="End date filter (must be >= date_from)"),
     *             @OA\Property(property="category", type="string", nullable=true, example="food", description="Spending category filter (max 50 chars)"),
     *             @OA\Property(property="asset_code", type="string", nullable=true, example="USD", description="Asset code filter (3-10 uppercase letters)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Spending analysis results",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 description="Spending analysis breakdown by category, merchant, and time period"
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="UNAUTHORIZED"),
     *                 @OA\Property(property="message", type="string", example="Unauthenticated.")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="VALIDATION_ERROR"),
     *                 @OA\Property(property="message", type="string", example="The given data was invalid.")
     *             )
     *         )
     *     )
     * )
     */
    public function spendingAnalysis(SpendingAnalysisRequest $request): JsonResponse
    {
        $validated = $request->validated();

        Log::info('AI Query: Spending analysis', [
            'user_id' => $request->user()?->id,
            'query'   => $validated['query'] ?? null,
        ]);

        $filters = [];

        if (! empty($validated['query'])) {
            $parsed = $this->nlpService->processQuery($validated['query'], []);
            $filters = $this->analyzerService->buildFiltersFromEntities($parsed['entities'] ?? []);
        }

        foreach (['date_from', 'date_to', 'category', 'asset_code'] as $key) {
            if (isset($validated[$key])) {
                $filters[$key] = $validated[$key];
            }
        }

        $accountUuid = $validated['account_uuid'] ?? null;
        $analysis = $this->analyzerService->analyzeSpending($filters, $accountUuid);

        return response()->json([
            'success' => true,
            'data'    => $analysis,
        ]);
    }
}
