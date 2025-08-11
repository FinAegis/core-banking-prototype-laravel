<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="MCP Tools",
 *     description="Model Context Protocol (MCP) tools management for AI agents"
 * )
 */
class MCPToolsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/ai/mcp/tools",
     *     operationId="listMcpTools",
     *     tags={"MCP Tools"},
     *     summary="List available MCP tools",
     *     description="Get a list of all available MCP tools that can be used by AI agents",
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of available MCP tools",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="name", type="string", example="get_account_balance"),
     *                     @OA\Property(property="description", type="string", example="Retrieve the current balance for a customer account"),
     *                     @OA\Property(property="category", type="string", example="account_management"),
     *                     @OA\Property(property="parameters", type="object"),
     *                     @OA\Property(property="requires_auth", type="boolean", example=true),
     *                     @OA\Property(property="rate_limit", type="integer", example=100)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function listTools(): JsonResponse
    {
        $tools = [
            [
                'name'        => 'get_account_balance',
                'description' => 'Retrieve the current balance for a customer account',
                'category'    => 'account_management',
                'parameters'  => [
                    'account_id'      => ['type' => 'string', 'required' => true],
                    'include_pending' => ['type' => 'boolean', 'required' => false],
                    'currency'        => ['type' => 'string', 'required' => false],
                ],
                'requires_auth' => true,
                'rate_limit'    => 100,
            ],
            [
                'name'        => 'authorize_transfer',
                'description' => 'Authorize and initiate a money transfer between accounts',
                'category'    => 'transactions',
                'parameters'  => [
                    'from_account' => ['type' => 'string', 'required' => true],
                    'to_account'   => ['type' => 'string', 'required' => true],
                    'amount'       => ['type' => 'number', 'required' => true],
                    'currency'     => ['type' => 'string', 'required' => true],
                    'reference'    => ['type' => 'string', 'required' => false],
                ],
                'requires_auth' => true,
                'requires_2fa'  => true,
                'rate_limit'    => 20,
            ],
            [
                'name'        => 'check_fraud_risk',
                'description' => 'Analyze transaction or activity for fraud risk',
                'category'    => 'security',
                'parameters'  => [
                    'transaction_data' => ['type' => 'object', 'required' => true],
                    'customer_id'      => ['type' => 'string', 'required' => true],
                    'check_type'       => ['type' => 'string', 'enum' => ['transaction', 'login', 'account_change'], 'required' => true],
                ],
                'ml_enabled' => true,
                'real_time'  => true,
                'rate_limit' => 50,
            ],
            [
                'name'        => 'get_transaction_history',
                'description' => 'Retrieve transaction history with filtering and pagination',
                'category'    => 'account_management',
                'parameters'  => [
                    'account_id' => ['type' => 'string', 'required' => true],
                    'start_date' => ['type' => 'string', 'format' => 'date-time', 'required' => false],
                    'end_date'   => ['type' => 'string', 'format' => 'date-time', 'required' => false],
                    'limit'      => ['type' => 'integer', 'required' => false],
                    'offset'     => ['type' => 'integer', 'required' => false],
                ],
                'pagination'  => true,
                'max_results' => 1000,
                'rate_limit'  => 100,
            ],
            [
                'name'        => 'analyze_spending_patterns',
                'description' => 'AI-powered analysis of customer spending patterns',
                'category'    => 'insights',
                'parameters'  => [
                    'customer_id' => ['type' => 'string', 'required' => true],
                    'period'      => ['type' => 'string', 'enum' => ['daily', 'weekly', 'monthly', 'yearly'], 'required' => false],
                    'categories'  => ['type' => 'array', 'required' => false],
                ],
                'ml_model'   => 'spending-analyzer-v2',
                'rate_limit' => 50,
            ],
            [
                'name'        => 'create_budget_plan',
                'description' => 'Generate personalized budget recommendations',
                'category'    => 'financial_planning',
                'parameters'  => [
                    'customer_id'    => ['type' => 'string', 'required' => true],
                    'income'         => ['type' => 'number', 'required' => true],
                    'goals'          => ['type' => 'array', 'required' => false],
                    'risk_tolerance' => ['type' => 'string', 'enum' => ['low', 'medium', 'high'], 'required' => false],
                ],
                'personalization' => true,
                'rate_limit'      => 30,
            ],
        ];

        return response()->json(['data' => $tools]);
    }

    /**
     * @OA\Post(
     *     path="/api/ai/mcp/tools/{tool}/execute",
     *     operationId="executeMcpTool",
     *     tags={"MCP Tools"},
     *     summary="Execute an MCP tool",
     *     description="Execute a specific MCP tool with the provided parameters",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="tool",
     *         in="path",
     *         required=true,
     *         description="The name of the tool to execute",
     *         @OA\Schema(type="string", example="get_account_balance")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="parameters", type="object", example={"account_id": "acct_123", "include_pending": true})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tool execution result",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="tool", type="string", example="get_account_balance"),
     *             @OA\Property(property="result", type="object"),
     *             @OA\Property(property="execution_time_ms", type="integer", example=145),
     *             @OA\Property(property="metadata", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid parameters or tool not found"
     *     ),
     *     @OA\Response(
     *         response=429,
     *         description="Rate limit exceeded"
     *     )
     * )
     */
    public function executeTool(Request $request, string $tool): JsonResponse
    {
        $validated = $request->validate([
            'parameters' => 'required|array',
        ]);

        // Demo implementation
        $startTime = microtime(true);

        $result = match ($tool) {
            'get_account_balance'       => $this->executeGetAccountBalance($validated['parameters']),
            'authorize_transfer'        => $this->executeAuthorizeTransfer($validated['parameters']),
            'check_fraud_risk'          => $this->executeCheckFraudRisk($validated['parameters']),
            'get_transaction_history'   => $this->executeGetTransactionHistory($validated['parameters']),
            'analyze_spending_patterns' => $this->executeAnalyzeSpendingPatterns($validated['parameters']),
            'create_budget_plan'        => $this->executeCreateBudgetPlan($validated['parameters']),
            default                     => null
        };

        if ($result === null) {
            return response()->json([
                'success' => false,
                'error'   => 'Tool not found',
                'tool'    => $tool,
            ], 404);
        }

        $executionTime = (microtime(true) - $startTime) * 1000;

        return response()->json([
            'success'           => true,
            'tool'              => $tool,
            'result'            => $result,
            'execution_time_ms' => round($executionTime),
            'metadata'          => [
                'user_id'   => Auth::id(),
                'timestamp' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/ai/mcp/tools/{tool}",
     *     operationId="getMcpToolDetails",
     *     tags={"MCP Tools"},
     *     summary="Get MCP tool details",
     *     description="Get detailed information about a specific MCP tool",
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="tool",
     *         in="path",
     *         required=true,
     *         description="The name of the tool",
     *         @OA\Schema(type="string", example="get_account_balance")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tool details",
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="category", type="string"),
     *             @OA\Property(property="parameters", type="object"),
     *             @OA\Property(property="examples", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="rate_limit", type="integer"),
     *             @OA\Property(property="requires_auth", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tool not found"
     *     )
     * )
     */
    public function getToolDetails(string $tool): JsonResponse
    {
        $tools = [
            'get_account_balance' => [
                'name'        => 'get_account_balance',
                'description' => 'Retrieve the current balance for a customer account with optional pending transactions',
                'category'    => 'account_management',
                'parameters'  => [
                    'account_id' => [
                        'type'        => 'string',
                        'required'    => true,
                        'description' => 'The unique identifier of the account',
                    ],
                    'include_pending' => [
                        'type'        => 'boolean',
                        'required'    => false,
                        'default'     => false,
                        'description' => 'Include pending transactions in the balance calculation',
                    ],
                    'currency' => [
                        'type'        => 'string',
                        'required'    => false,
                        'default'     => 'USD',
                        'description' => 'Currency code for balance display',
                    ],
                ],
                'examples' => [
                    [
                        'description' => 'Basic balance check',
                        'parameters'  => ['account_id' => 'acct_123'],
                    ],
                    [
                        'description' => 'Balance with pending transactions',
                        'parameters'  => ['account_id' => 'acct_123', 'include_pending' => true],
                    ],
                ],
                'rate_limit'    => 100,
                'requires_auth' => true,
                'cache_ttl'     => 60,
            ],
        ];

        if (! isset($tools[$tool])) {
            return response()->json(['error' => 'Tool not found'], 404);
        }

        return response()->json($tools[$tool]);
    }

    /**
     * @OA\Post(
     *     path="/api/ai/mcp/register",
     *     operationId="registerMcpTool",
     *     tags={"MCP Tools"},
     *     summary="Register a new MCP tool",
     *     description="Register a custom MCP tool for use by AI agents",
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "description", "endpoint", "parameters"},
     *             @OA\Property(property="name", type="string", example="custom_tool"),
     *             @OA\Property(property="description", type="string", example="A custom tool for specific operations"),
     *             @OA\Property(property="endpoint", type="string", format="url", example="https://api.example.com/tool"),
     *             @OA\Property(property="parameters", type="object"),
     *             @OA\Property(property="category", type="string", example="custom"),
     *             @OA\Property(property="requires_auth", type="boolean", example=true),
     *             @OA\Property(property="rate_limit", type="integer", example=50)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tool registered successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="tool_id", type="string", example="tool_abc123"),
     *             @OA\Property(property="message", type="string", example="Tool registered successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid tool configuration"
     *     )
     * )
     */
    public function registerTool(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'          => 'required|string|unique:mcp_tools,name',
            'description'   => 'required|string',
            'endpoint'      => 'required|url',
            'parameters'    => 'required|array',
            'category'      => 'string',
            'requires_auth' => 'boolean',
            'rate_limit'    => 'integer|min:1|max:1000',
        ]);

        // Demo implementation
        return response()->json([
            'success' => true,
            'tool_id' => 'tool_' . uniqid(),
            'message' => 'Tool registered successfully',
        ], 201);
    }

    // Demo execution methods
    private function executeGetAccountBalance(array $params): array
    {
        return [
            'account_id'        => $params['account_id'] ?? 'acct_default',
            'available_balance' => 12456.78,
            'current_balance'   => 12556.78,
            'pending_balance'   => 100.00,
            'currency'          => $params['currency'] ?? 'USD',
            'as_of'             => now()->toIso8601String(),
        ];
    }

    private function executeAuthorizeTransfer(array $params): array
    {
        return [
            'success'              => true,
            'transfer_id'          => 'txfr_' . uniqid(),
            'status'               => 'pending',
            'estimated_completion' => now()->addMinutes(5)->toIso8601String(),
            'fee'                  => 0.50,
            'exchange_rate'        => 1.0,
        ];
    }

    private function executeCheckFraudRisk(array $params): array
    {
        return [
            'risk_score'         => 0.23,
            'risk_level'         => 'low',
            'flags'              => [],
            'recommended_action' => 'proceed',
            'ml_confidence'      => 0.89,
        ];
    }

    private function executeGetTransactionHistory(array $params): array
    {
        return [
            'account_id'   => $params['account_id'],
            'transactions' => [
                [
                    'id'          => 'tx_001',
                    'type'        => 'deposit',
                    'amount'      => 500.00,
                    'date'        => now()->subDays(1)->toIso8601String(),
                    'description' => 'Salary deposit',
                ],
                [
                    'id'          => 'tx_002',
                    'type'        => 'withdrawal',
                    'amount'      => 100.00,
                    'date'        => now()->subDays(2)->toIso8601String(),
                    'description' => 'ATM withdrawal',
                ],
            ],
            'total_count' => 2,
            'has_more'    => false,
        ];
    }

    private function executeAnalyzeSpendingPatterns(array $params): array
    {
        return [
            'customer_id'    => $params['customer_id'],
            'period'         => $params['period'] ?? 'monthly',
            'total_spending' => 3456.78,
            'categories'     => [
                'groceries'     => 450.00,
                'entertainment' => 200.00,
                'utilities'     => 300.00,
                'transport'     => 150.00,
            ],
            'insights' => [
                'highest_category'    => 'groceries',
                'trend'               => 'decreasing',
                'savings_opportunity' => 200.00,
            ],
        ];
    }

    private function executeCreateBudgetPlan(array $params): array
    {
        return [
            'customer_id'        => $params['customer_id'],
            'monthly_income'     => $params['income'],
            'recommended_budget' => [
                'essentials'    => $params['income'] * 0.5,
                'savings'       => $params['income'] * 0.2,
                'investments'   => $params['income'] * 0.1,
                'discretionary' => $params['income'] * 0.2,
            ],
            'tips' => [
                'Automate your savings',
                'Review subscriptions monthly',
                'Use the 50/30/20 rule',
            ],
        ];
    }
}
