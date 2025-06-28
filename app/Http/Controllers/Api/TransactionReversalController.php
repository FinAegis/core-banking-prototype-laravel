<?php

namespace App\Http\Controllers\Api;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Workflows\TransactionReversalWorkflow;
use App\Http\Controllers\Controller;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Workflow\WorkflowStub;

/**
 * @OA\Tag(
 *     name="Transaction Reversal",
 *     description="Critical transaction reversal operations for error recovery"
 * )
 */
class TransactionReversalController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/accounts/{uuid}/transactions/reverse",
     *     tags={"Transaction Reversal"},
     *     summary="Reverse a transaction",
     *     description="Reverse a completed transaction with audit trail for error recovery",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="uuid",
     *         in="path",
     *         required=true,
     *         description="Account UUID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount", "asset_code", "transaction_type", "reversal_reason"},
     *             @OA\Property(property="amount", type="number", format="float", minimum=0.01, example=100.50),
     *             @OA\Property(property="asset_code", type="string", example="USD"),
     *             @OA\Property(property="transaction_type", type="string", enum={"debit", "credit"}, example="debit"),
     *             @OA\Property(property="reversal_reason", type="string", example="Unauthorized transaction"),
     *             @OA\Property(property="original_transaction_id", type="string", example="txn_123456789"),
     *             @OA\Property(property="authorized_by", type="string", example="manager@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transaction reversal initiated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Transaction reversal initiated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="reversal_id", type="string", example="rev_987654321"),
     *                 @OA\Property(property="account_uuid", type="string", example="550e8400-e29b-41d4-a716-446655440000"),
     *                 @OA\Property(property="amount", type="number", example=100.50),
     *                 @OA\Property(property="asset_code", type="string", example="USD"),
     *                 @OA\Property(property="transaction_type", type="string", example="debit"),
     *                 @OA\Property(property="reversal_reason", type="string", example="Unauthorized transaction"),
     *                 @OA\Property(property="status", type="string", example="initiated"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - Account does not belong to user"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Account not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function reverseTransaction(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'asset_code' => 'required|string|exists:assets,code',
            'transaction_type' => ['required', 'string', Rule::in(['debit', 'credit'])],
            'reversal_reason' => 'required|string|max:500',
            'original_transaction_id' => 'nullable|string|max:255',
            'authorized_by' => 'nullable|string|max:255',
        ]);

        $account = Account::where('uuid', $uuid)->firstOrFail();
        
        // Ensure account belongs to authenticated user (or admin)
        if ($account->user_uuid !== Auth::user()->uuid && !Auth::user()->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        try {
            $accountUuid = AccountUuid::fromString($account->uuid);
            
            // Convert amount to Money object with proper precision
            $amount = Money::fromFloat($validated['amount'], $validated['asset_code']);
            
            // Start the transaction reversal workflow
            $workflow = WorkflowStub::make(TransactionReversalWorkflow::class);
            $result = $workflow->execute(
                $accountUuid,
                $amount,
                $validated['transaction_type'],
                $validated['reversal_reason'],
                $validated['authorized_by'] ?? Auth::user()->email
            );
            
            // Generate reversal ID for tracking
            $reversalId = 'rev_' . uniqid() . '_' . time();
            
            return response()->json([
                'message' => 'Transaction reversal initiated successfully',
                'data' => [
                    'reversal_id' => $reversalId,
                    'account_uuid' => $account->uuid,
                    'amount' => $validated['amount'],
                    'asset_code' => $validated['asset_code'],
                    'transaction_type' => $validated['transaction_type'],
                    'reversal_reason' => $validated['reversal_reason'],
                    'original_transaction_id' => $validated['original_transaction_id'] ?? null,
                    'authorized_by' => $validated['authorized_by'] ?? Auth::user()->email,
                    'status' => 'initiated',
                    'created_at' => now()->toISOString(),
                ]
            ], 200);
            
        } catch (\Exception $e) {
            logger()->error('Transaction reversal API failed', [
                'account_uuid' => $uuid,
                'amount' => $validated['amount'],
                'asset_code' => $validated['asset_code'],
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
            ]);
            
            return response()->json([
                'message' => 'Transaction reversal failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/accounts/{uuid}/transactions/reversals",
     *     tags={"Transaction Reversal"},
     *     summary="Get transaction reversal history",
     *     description="Get list of transaction reversals for an account",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="uuid",
     *         in="path",
     *         required=true,
     *         description="Account UUID",
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Number of results to return",
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=20)
     *     ),
     *     @OA\Parameter(
     *         name="offset",
     *         in="query",
     *         description="Number of results to skip",
     *         @OA\Schema(type="integer", minimum=0, default=0)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Reversal history retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="reversal_id", type="string", example="rev_987654321"),
     *                     @OA\Property(property="amount", type="number", example=100.50),
     *                     @OA\Property(property="asset_code", type="string", example="USD"),
     *                     @OA\Property(property="transaction_type", type="string", example="debit"),
     *                     @OA\Property(property="reversal_reason", type="string", example="Unauthorized transaction"),
     *                     @OA\Property(property="status", type="string", example="completed"),
     *                     @OA\Property(property="created_at", type="string", format="date-time"),
     *                     @OA\Property(property="completed_at", type="string", format="date-time")
     *                 )
     *             ),
     *             @OA\Property(property="pagination", type="object",
     *                 @OA\Property(property="total", type="integer", example=45),
     *                 @OA\Property(property="limit", type="integer", example=20),
     *                 @OA\Property(property="offset", type="integer", example=0)
     *             )
     *         )
     *     )
     * )
     */
    public function getReversalHistory(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'limit' => 'integer|min:1|max:100',
            'offset' => 'integer|min:0',
        ]);

        $account = Account::where('uuid', $uuid)->firstOrFail();
        
        if ($account->user_uuid !== Auth::user()->uuid && !Auth::user()->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $limit = $validated['limit'] ?? 20;
        $offset = $validated['offset'] ?? 0;

        // TODO: In a real implementation, fetch from reversal history table
        // For now, return mock data structure
        $mockReversals = [
            [
                'reversal_id' => 'rev_' . uniqid(),
                'amount' => 150.00,
                'asset_code' => 'USD',
                'transaction_type' => 'debit',
                'reversal_reason' => 'Unauthorized transaction detected by fraud system',
                'original_transaction_id' => 'txn_123456789',
                'authorized_by' => 'security@finaegis.org',
                'status' => 'completed',
                'created_at' => now()->subDays(2)->toISOString(),
                'completed_at' => now()->subDays(2)->addMinutes(5)->toISOString(),
            ],
            [
                'reversal_id' => 'rev_' . uniqid(),
                'amount' => 75.50,
                'asset_code' => 'EUR',
                'transaction_type' => 'credit',
                'reversal_reason' => 'Duplicate transaction',
                'original_transaction_id' => 'txn_987654321',
                'authorized_by' => Auth::user()->email,
                'status' => 'pending',
                'created_at' => now()->subHours(6)->toISOString(),
                'completed_at' => null,
            ],
        ];

        return response()->json([
            'data' => array_slice($mockReversals, $offset, $limit),
            'pagination' => [
                'total' => count($mockReversals),
                'limit' => $limit,
                'offset' => $offset,
            ]
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/transactions/reversals/{reversalId}/status",
     *     tags={"Transaction Reversal"},
     *     summary="Get reversal status",
     *     description="Check the status of a specific transaction reversal",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="reversalId",
     *         in="path",
     *         required=true,
     *         description="Reversal ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Reversal status retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="reversal_id", type="string", example="rev_987654321"),
     *                 @OA\Property(property="status", type="string", example="completed"),
     *                 @OA\Property(property="progress", type="integer", example=100),
     *                 @OA\Property(property="steps_completed", type="array",
     *                     @OA\Items(type="string", example="validation")
     *                 ),
     *                 @OA\Property(property="error_message", type="string", nullable=true),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="updated_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Reversal not found"
     *     )
     * )
     */
    public function getReversalStatus(string $reversalId): JsonResponse
    {
        // TODO: In a real implementation, fetch from reversal tracking table
        // For now, return mock status data
        $mockStatus = [
            'reversal_id' => $reversalId,
            'status' => 'completed',
            'progress' => 100,
            'steps_completed' => [
                'validation',
                'authorization_check',
                'balance_verification',
                'reversal_execution',
                'audit_logging'
            ],
            'error_message' => null,
            'created_at' => now()->subMinutes(30)->toISOString(),
            'updated_at' => now()->subMinutes(25)->toISOString(),
        ];

        return response()->json([
            'data' => $mockStatus
        ]);
    }
}