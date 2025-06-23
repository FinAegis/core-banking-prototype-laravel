<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Workflows\DepositAccountWorkflow;
use App\Domain\Account\Workflows\WithdrawAccountWorkflow;
use App\Domain\Asset\Workflows\AssetDepositWorkflow;
use App\Domain\Asset\Workflows\AssetWithdrawWorkflow;
use App\Domain\Asset\Models\Asset;
use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Transaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Workflow\WorkflowStub;

class TransactionController extends Controller
{
    /**
     * @OA\Post(
     *     path="/api/accounts/{uuid}/deposit",
     *     operationId="depositToAccount",
     *     tags={"Transactions"},
     *     summary="Deposit money to an account",
     *     description="Deposits money into a specified account",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="uuid",
     *         in="path",
     *         description="Account UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount"},
     *             @OA\Property(property="amount", type="integer", example=10000, minimum=1, description="Amount in cents"),
     *             @OA\Property(property="description", type="string", example="Monthly salary", maxLength=255)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Deposit successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="account_uuid", type="string", format="uuid"),
     *                 @OA\Property(property="new_balance", type="integer", example=60000),
     *                 @OA\Property(property="amount_deposited", type="integer", example=10000),
     *                 @OA\Property(property="transaction_type", type="string", example="deposit")
     *             ),
     *             @OA\Property(property="message", type="string", example="Deposit successful")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Cannot deposit to frozen account",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Account not found",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function deposit(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'asset_code' => 'required|string|exists:assets,code',
            'description' => 'sometimes|string|max:255',
        ]);

        $account = Account::where('uuid', $uuid)->firstOrFail();

        // Check if user owns this account
        if ($account->user_uuid !== auth()->user()->uuid) {
            return response()->json([
                'message' => 'Access denied to this account',
                'error' => 'FORBIDDEN',
            ], 403);
        }

        if ($account->frozen) {
            return response()->json([
                'message' => 'Cannot deposit to frozen account',
                'error' => 'ACCOUNT_FROZEN',
            ], 422);
        }

        $asset = Asset::where('code', $validated['asset_code'])->firstOrFail();
        $amountInMinorUnits = (int) round($validated['amount'] * (10 ** $asset->precision));

        $accountUuid = new AccountUuid($uuid);

        // Determine which workflow to use based on asset type
        if ($validated['asset_code'] === 'USD') {
            // Legacy workflow for USD
            $money = new Money($amountInMinorUnits);
            $workflow = WorkflowStub::make(DepositAccountWorkflow::class);
            $workflow->start($accountUuid, $money);
        } else {
            // Multi-asset workflow for other assets
            $workflow = WorkflowStub::make(AssetDepositWorkflow::class);
            $workflow->start($accountUuid, $validated['asset_code'], $amountInMinorUnits);
        }

        return response()->json([
            'message' => 'Deposit initiated successfully',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/accounts/{uuid}/withdraw",
     *     operationId="withdrawFromAccount",
     *     tags={"Transactions"},
     *     summary="Withdraw money from an account",
     *     description="Withdraws money from a specified account",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="uuid",
     *         in="path",
     *         description="Account UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount"},
     *             @OA\Property(property="amount", type="integer", example=5000, minimum=1, description="Amount in cents"),
     *             @OA\Property(property="description", type="string", example="ATM withdrawal", maxLength=255)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Withdrawal successful",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="account_uuid", type="string", format="uuid"),
     *                 @OA\Property(property="new_balance", type="integer", example=45000),
     *                 @OA\Property(property="amount_withdrawn", type="integer", example=5000),
     *                 @OA\Property(property="transaction_type", type="string", example="withdrawal")
     *             ),
     *             @OA\Property(property="message", type="string", example="Withdrawal successful")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Insufficient balance or frozen account",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Account not found",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function withdraw(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'asset_code' => 'required|string|exists:assets,code',
            'description' => 'sometimes|string|max:255',
        ]);

        $account = Account::where('uuid', $uuid)->firstOrFail();

        // Check if user owns this account
        if ($account->user_uuid !== auth()->user()->uuid) {
            return response()->json([
                'message' => 'Access denied to this account',
                'error' => 'FORBIDDEN',
            ], 403);
        }

        if ($account->frozen) {
            return response()->json([
                'message' => 'Cannot withdraw from frozen account',
                'error' => 'ACCOUNT_FROZEN',
            ], 422);
        }

        $asset = Asset::where('code', $validated['asset_code'])->firstOrFail();
        $amountInMinorUnits = (int) round($validated['amount'] * (10 ** $asset->precision));

        // Check sufficient balance
        $balance = $account->getBalance($validated['asset_code']);
        
        if ($balance < $amountInMinorUnits) {
            return response()->json([
                'message' => 'Insufficient balance',
                'errors' => [
                    'amount' => ['Insufficient balance'],
                ],
            ], 422);
        }

        $accountUuid = new AccountUuid($uuid);

        try {
            // Determine which workflow to use based on asset type
            if ($validated['asset_code'] === 'USD') {
                // Legacy workflow for USD
                $money = new Money($amountInMinorUnits);
                $workflow = WorkflowStub::make(WithdrawAccountWorkflow::class);
                $workflow->start($accountUuid, $money);
            } else {
                // Multi-asset workflow for other assets
                $workflow = WorkflowStub::make(AssetWithdrawWorkflow::class);
                $workflow->start($accountUuid, $validated['asset_code'], $amountInMinorUnits);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Withdrawal failed',
                'error' => 'WITHDRAWAL_FAILED',
            ], 422);
        }

        return response()->json([
            'message' => 'Withdrawal initiated successfully',
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/accounts/{uuid}/transactions",
     *     operationId="getAccountTransactions",
     *     tags={"Transactions"},
     *     summary="Get transaction history for an account",
     *     description="Retrieves paginated transaction history from event store",
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="uuid",
     *         in="path",
     *         description="Account UUID",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Parameter(
     *         name="type",
     *         in="query",
     *         description="Filter by transaction type",
     *         required=false,
     *         @OA\Schema(type="string", enum={"credit", "debit"})
     *     ),
     *     @OA\Parameter(
     *         name="asset_code",
     *         in="query",
     *         description="Filter by asset code",
     *         required=false,
     *         @OA\Schema(type="string", example="USD")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=50)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transaction history retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/Transaction")),
     *             @OA\Property(property="meta", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Account not found",
     *         @OA\JsonContent(ref="#/components/schemas/Error")
     *     )
     * )
     */
    public function history(Request $request, string $uuid): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'sometimes|string|in:credit,debit',
            'asset_code' => 'sometimes|string|max:10',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $account = Account::where('uuid', $uuid)->firstOrFail();
        
        // Build event classes to query based on filters
        $eventClasses = [
            'App\Domain\Account\Events\MoneyAdded',
            'App\Domain\Account\Events\MoneySubtracted',
            'App\Domain\Account\Events\MoneyTransferred',
            'App\Domain\Account\Events\AssetBalanceAdded',
            'App\Domain\Account\Events\AssetBalanceSubtracted', 
            'App\Domain\Account\Events\AssetTransferred',
        ];

        $query = \DB::table('stored_events')
            ->where('aggregate_uuid', $uuid)
            ->whereIn('event_class', $eventClasses)
            ->orderBy('created_at', 'desc');

        $events = $query->paginate($validated['per_page'] ?? 50);

        // Transform events to transaction format
        $transactions = collect($events->items())->map(function ($event) {
            $properties = json_decode($event->event_properties, true);
            $eventClass = class_basename($event->event_class);
            
            // Default values
            $transaction = [
                'id' => $event->id,
                'account_uuid' => $event->aggregate_uuid,
                'type' => $this->getTransactionType($eventClass),
                'amount' => 0,
                'asset_code' => 'USD',
                'description' => $this->getTransactionDescription($eventClass),
                'hash' => $properties['hash']['hash'] ?? null,
                'created_at' => $event->created_at,
                'metadata' => [],
            ];

            // Extract amount and asset based on event type
            switch ($eventClass) {
                case 'MoneyAdded':
                case 'MoneySubtracted':
                    $transaction['amount'] = $properties['money']['amount'] ?? 0;
                    $transaction['asset_code'] = 'USD'; // Legacy events are USD
                    break;
                    
                case 'AssetBalanceAdded':
                case 'AssetBalanceSubtracted':
                    $transaction['amount'] = $properties['amount'] ?? 0;
                    $transaction['asset_code'] = $properties['assetCode'] ?? 'USD';
                    break;
                    
                case 'MoneyTransferred':
                case 'AssetTransferred':
                    $transaction['amount'] = $properties['money']['amount'] ?? $properties['fromAmount'] ?? 0;
                    $transaction['asset_code'] = $properties['fromAsset'] ?? 'USD';
                    $transaction['metadata'] = [
                        'to_account' => $properties['toAccount']['uuid'] ?? null,
                        'from_account' => $properties['fromAccount']['uuid'] ?? null,
                    ];
                    break;
            }

            return $transaction;
        })->filter(function ($transaction) use ($validated) {
            // Apply filters
            if (isset($validated['type']) && $transaction['type'] !== $validated['type']) {
                return false;
            }
            
            if (isset($validated['asset_code']) && $transaction['asset_code'] !== $validated['asset_code']) {
                return false;
            }
            
            return true;
        })->values();

        return response()->json([
            'data' => $transactions,
            'meta' => [
                'current_page' => $events->currentPage(),
                'last_page' => $events->lastPage(),
                'per_page' => $events->perPage(),
                'total' => $events->total(),
                'account_uuid' => $uuid,
            ],
        ]);
    }

    /**
     * Get transaction type from event class
     */
    private function getTransactionType(string $eventClass): string
    {
        return match ($eventClass) {
            'MoneyAdded', 'AssetBalanceAdded' => 'credit',
            'MoneySubtracted', 'AssetBalanceSubtracted' => 'debit',
            'MoneyTransferred', 'AssetTransferred' => 'transfer',
            default => 'unknown',
        };
    }

    /**
     * Get transaction description from event class
     */
    private function getTransactionDescription(string $eventClass): string
    {
        return match ($eventClass) {
            'MoneyAdded', 'AssetBalanceAdded' => 'Deposit',
            'MoneySubtracted', 'AssetBalanceSubtracted' => 'Withdrawal',
            'MoneyTransferred', 'AssetTransferred' => 'Transfer',
            default => 'Transaction',
        };
    }
}