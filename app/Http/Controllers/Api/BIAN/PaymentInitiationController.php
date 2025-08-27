<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\BIAN;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Asset\Workflows\AssetTransferWorkflow;
use App\Http\Controllers\Controller;
use DB;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Workflow\WorkflowStub;

/**
 * BIAN-compliant Payment Initiation Service Domain Controller.
 *
 * Service Domain: Payment Initiation
 * Functional Pattern: Transact
 * Asset Type: Payment Transaction
 *
 * @OA\Tag(
 *     name="BIAN",
 *     description="BIAN-compliant banking service operations"
 * )
 */
class PaymentInitiationController extends Controller
{
    /**
     * Initiate a new payment transaction.
     *
     * BIAN Operation: Initiate
     * HTTP Method: POST
     * Path: /payment-initiation/{cr-reference-id}/initiate
     *
     * @OA\Post(
     *     path="/api/bian/payment-initiation/initiate",
     *     operationId="initiatePayment",
     *     tags={"BIAN"},
     *     summary="Initiate new payment transaction",
     *     description="Creates a new payment transaction following BIAN Payment Initiation standards",
     *     security={{"sanctum": {}}},
     *
     * @OA\RequestBody(
     *         required=true,
     *
     * @OA\JsonContent(
     *             required={"payerReference", "payeeReference", "paymentAmount", "paymentType"},
     *
     * @OA\Property(property="payerReference",  type="string", format="uuid", description="Payer account UUID"),
     * @OA\Property(property="payeeReference",  type="string", format="uuid", description="Payee account UUID"),
     * @OA\Property(property="paymentAmount",   type="integer", minimum=1, description="Payment amount in cents"),
     * @OA\Property(property="paymentCurrency", type="string", pattern="^[A-Z]{3}$", default="USD"),
     * @OA\Property(property="paymentPurpose",  type="string", maxLength=500),
     * @OA\Property(property="paymentType",     type="string", enum={"internal", "external", "instant", "scheduled"}),
     * @OA\Property(property="valueDate",       type="string", format="date", description="For scheduled payments")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=201,
     *         description="Payment initiated successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(
     *                 property="paymentInitiationTransaction",
     *                 type="object",
     * @OA\Property(property="crReferenceId",   type="string", format="uuid"),
     * @OA\Property(property="paymentStatus",   type="string", enum={"completed", "scheduled", "failed"}),
     * @OA\Property(
     *                     property="paymentDetails",
     *                     type="object",
     * @OA\Property(property="payerReference",  type="string"),
     * @OA\Property(property="payerName",       type="string"),
     * @OA\Property(property="payeeReference",  type="string"),
     * @OA\Property(property="payeeName",       type="string"),
     * @OA\Property(property="paymentAmount",   type="integer"),
     * @OA\Property(property="paymentCurrency", type="string"),
     * @OA\Property(property="paymentPurpose",  type="string", nullable=true),
     * @OA\Property(property="paymentType",     type="string")
     *                 ),
     * @OA\Property(
     *                     property="paymentSchedule",
     *                     type="object",
     * @OA\Property(property="initiationDate",  type="string", format="date-time"),
     * @OA\Property(property="valueDate",       type="string", format="date")
     *                 ),
     * @OA\Property(
     *                     property="balanceAfterPayment",
     *                     type="object",
     * @OA\Property(property="payerBalance",    type="integer"),
     * @OA\Property(property="payeeBalance",    type="integer")
     *                 )
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=422,
     *         description="Insufficient funds or validation error",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(
     *                 property="paymentInitiationTransaction",
     *                 type="object",
     * @OA\Property(property="paymentStatus",   type="string", example="rejected"),
     * @OA\Property(property="statusReason",    type="string")
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function initiate(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'payerReference'  => 'required|uuid|exists:accounts,uuid',
                'payeeReference'  => 'required|uuid|exists:accounts,uuid|different:payerReference',
                'paymentAmount'   => 'required|integer|min:1',
                'paymentCurrency' => 'sometimes|string|size:3',
                'paymentPurpose'  => 'sometimes|string|max:500',
                'paymentType'     => 'required|in:internal,external,instant,scheduled',
                'valueDate'       => 'sometimes|date|after_or_equal:today',
            ]
        );

        // Validate payer has sufficient funds
        $payerAccount = Account::where('uuid', $validated['payerReference'])->first();
        $payeeAccount = Account::where('uuid', $validated['payeeReference'])->first();

        // For backward compatibility, use USD balance
        $payerBalance = $payerAccount->getBalance('USD');

        if ($payerBalance < $validated['paymentAmount']) {
            return response()->json(
                [
                    'paymentInitiationTransaction' => [
                        'crReferenceId'         => Str::uuid()->toString(),
                        'paymentStatus'         => 'rejected',
                        'statusReason'          => 'Insufficient funds',
                        'payerAvailableBalance' => $payerBalance,
                        'requestedAmount'       => $validated['paymentAmount'],
                    ],
                ],
                422
            );
        }

        // Generate Control Record Reference ID
        $crReferenceId = Str::uuid()->toString();

        // Execute payment if immediate
        if ($validated['paymentType'] !== 'scheduled' || ! isset($validated['valueDate'])) {
            $fromUuid = new AccountUuid($validated['payerReference']);
            $toUuid = new AccountUuid($validated['payeeReference']);
            $money = new Money($validated['paymentAmount']);

            try {
                $workflow = WorkflowStub::make(AssetTransferWorkflow::class);
                $workflow->start(
                    $fromUuid,
                    $toUuid,
                    'USD', // assetCode
                    $validated['paymentAmount'], // amount as integer
                    $validated['paymentPurpose'] ?? 'BIAN Payment Initiation'
                );
                $status = 'completed';
            } catch (Exception $e) {
                $status = 'failed';
            }
        } else {
            $status = 'scheduled';
        }

        $payerAccount->refresh();
        $payeeAccount->refresh();

        return response()->json(
            [
                'paymentInitiationTransaction' => [
                    'crReferenceId'  => $crReferenceId,
                    'paymentStatus'  => $status,
                    'paymentDetails' => [
                        'payerReference'  => $validated['payerReference'],
                        'payerName'       => $payerAccount->name,
                        'payeeReference'  => $validated['payeeReference'],
                        'payeeName'       => $payeeAccount->name,
                        'paymentAmount'   => $validated['paymentAmount'],
                        'paymentCurrency' => $validated['paymentCurrency'] ?? 'USD',
                        'paymentPurpose'  => $validated['paymentPurpose'] ?? null,
                        'paymentType'     => $validated['paymentType'],
                    ],
                    'paymentSchedule' => [
                        'initiationDate' => now()->toIso8601String(),
                        'valueDate'      => $validated['valueDate'] ?? now()->toDateString(),
                    ],
                    'balanceAfterPayment' => [
                        'payerBalance' => $payerAccount->getBalance('USD'),
                        'payeeBalance' => $payeeAccount->getBalance('USD'),
                    ],
                ],
            ],
            201
        );
    }

    /**
     * Update a payment transaction.
     *
     * BIAN Operation: Update
     * HTTP Method: PUT
     * Path: /payment-initiation/{cr-reference-id}/update
     *
     * @OA\Put(
     *     path="/api/bian/payment-initiation/{crReferenceId}/update",
     *     operationId="updatePayment",
     *     tags={"BIAN"},
     *     summary="Update payment transaction",
     *     description="Updates the status of a payment transaction (cancel, suspend, resume)",
     *     security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     *         name="crReferenceId",
     *         in="path",
     *         required=true,
     *         description="Control Record Reference ID",
     *
     * @OA\Schema(type="string",               format="uuid")
     *     ),
     *
     * @OA\RequestBody(
     *         required=true,
     *
     * @OA\JsonContent(
     *             required={"paymentStatus", "statusReason"},
     *
     * @OA\Property(property="paymentStatus",  type="string", enum={"cancelled", "suspended", "resumed"}),
     * @OA\Property(property="statusReason",   type="string", maxLength=500)
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Payment updated successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(
     *                 property="paymentInitiationTransaction",
     *                 type="object",
     * @OA\Property(property="crReferenceId",  type="string", format="uuid"),
     * @OA\Property(property="updateAction",   type="string"),
     * @OA\Property(property="updateReason",   type="string"),
     * @OA\Property(property="updateStatus",   type="string", example="successful"),
     * @OA\Property(property="updateDateTime", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=404,
     *         description="Payment transaction not found"
     *     ),
     * @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     * @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function update(Request $request, string $crReferenceId): JsonResponse
    {
        $validated = $request->validate(
            [
                'paymentStatus' => 'required|in:cancelled,suspended,resumed',
                'statusReason'  => 'required|string|max:500',
            ]
        );

        // In a real implementation, this would update the payment record
        // For now, we'll return a simulated response
        return response()->json(
            [
                'paymentInitiationTransaction' => [
                    'crReferenceId'  => $crReferenceId,
                    'updateAction'   => $validated['paymentStatus'],
                    'updateReason'   => $validated['statusReason'],
                    'updateStatus'   => 'successful',
                    'updateDateTime' => now()->toIso8601String(),
                ],
            ]
        );
    }

    /**
     * Retrieve payment transaction details.
     *
     * BIAN Operation: Retrieve
     * HTTP Method: GET
     * Path: /payment-initiation/{cr-reference-id}/retrieve
     *
     * @OA\Get(
     *     path="/api/bian/payment-initiation/{crReferenceId}/retrieve",
     *     operationId="retrievePayment",
     *     tags={"BIAN"},
     *     summary="Retrieve payment details",
     *     description="Retrieves the details of a specific payment transaction",
     *     security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     *         name="crReferenceId",
     *         in="path",
     *         required=true,
     *         description="Control Record Reference ID",
     *
     * @OA\Schema(type="string",                 format="uuid")
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Payment details retrieved successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(
     *                 property="paymentInitiationTransaction",
     *                 type="object",
     * @OA\Property(property="crReferenceId",    type="string", format="uuid"),
     * @OA\Property(property="paymentStatus",    type="string"),
     * @OA\Property(
     *                     property="paymentDetails",
     *                     type="object",
     * @OA\Property(property="payerReference",   type="string"),
     * @OA\Property(property="payeeReference",   type="string"),
     * @OA\Property(property="paymentAmount",    type="integer"),
     * @OA\Property(property="paymentCurrency",  type="string")
     *                 ),
     * @OA\Property(
     *                     property="paymentSchedule",
     *                     type="object",
     * @OA\Property(property="initiationDate",   type="string", format="date-time"),
     * @OA\Property(property="completionDate",   type="string", format="date-time")
     *                 ),
     * @OA\Property(property="paymentReference", type="string", nullable=true)
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=404,
     *         description="Payment transaction not found"
     *     ),
     * @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function retrieve(string $crReferenceId): JsonResponse
    {
        // Query stored events for transfer details
        $event = DB::table('stored_events')
            ->where('event_class', 'App\Domain\Account\Events\MoneyTransferred')
            ->where('aggregate_uuid', $crReferenceId)
            ->first();

        if (! $event) {
            abort(404, 'Payment transaction not found');
        }

        $properties = json_decode($event->event_properties, true);

        return response()->json(
            [
                'paymentInitiationTransaction' => [
                    'crReferenceId'  => $crReferenceId,
                    'paymentStatus'  => 'completed',
                    'paymentDetails' => [
                        'payerReference'  => $properties['from_uuid'] ?? $event->aggregate_uuid,
                        'payeeReference'  => $properties['to_uuid'] ?? null,
                        'paymentAmount'   => $properties['money']['amount'] ?? 0,
                        'paymentCurrency' => 'USD',
                    ],
                    'paymentSchedule' => [
                        'initiationDate' => $event->created_at,
                        'completionDate' => $event->created_at,
                    ],
                    'paymentReference' => $properties['hash']['hash'] ?? null,
                ],
            ]
        );
    }

    /**
     * Execute payment transaction.
     *
     * BIAN Operation: Execute
     * HTTP Method: POST
     * Path: /payment-initiation/{cr-reference-id}/execute
     *
     * @OA\Post(
     *     path="/api/bian/payment-initiation/{crReferenceId}/execute",
     *     operationId="executePayment",
     *     tags={"BIAN"},
     *     summary="Execute payment transaction",
     *     description="Executes a scheduled or pending payment transaction",
     *     security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     *         name="crReferenceId",
     *         in="path",
     *         required=true,
     *         description="Control Record Reference ID",
     *
     * @OA\Schema(type="string",                  format="uuid")
     *     ),
     *
     * @OA\RequestBody(
     *         required=true,
     *
     * @OA\JsonContent(
     *             required={"executionMode"},
     *
     * @OA\Property(property="executionMode",     type="string", enum={"immediate", "retry", "force"})
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Payment executed successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(
     *                 property="paymentExecutionRecord",
     *                 type="object",
     * @OA\Property(property="crReferenceId",     type="string", format="uuid"),
     * @OA\Property(property="executionMode",     type="string"),
     * @OA\Property(property="executionStatus",   type="string", example="completed"),
     * @OA\Property(property="executionDateTime", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=404,
     *         description="Payment transaction not found"
     *     ),
     * @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     * @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function execute(Request $request, string $crReferenceId): JsonResponse
    {
        $validated = $request->validate(
            [
                'executionMode' => 'required|in:immediate,retry,force',
            ]
        );

        // In a real implementation, this would execute a scheduled/pending payment
        return response()->json(
            [
                'paymentExecutionRecord' => [
                    'crReferenceId'     => $crReferenceId,
                    'executionMode'     => $validated['executionMode'],
                    'executionStatus'   => 'completed',
                    'executionDateTime' => now()->toIso8601String(),
                ],
            ]
        );
    }

    /**
     * Request payment status.
     *
     * BIAN Operation: Request
     * Behavior Qualifier: PaymentStatus
     * HTTP Method: POST
     * Path: /payment-initiation/{cr-reference-id}/payment-status/{bq-reference-id}/request
     *
     * @OA\Post(
     *     path="/api/bian/payment-initiation/{crReferenceId}/payment-status/request",
     *     operationId="requestPaymentStatus",
     *     tags={"BIAN"},
     *     summary="Request payment status",
     *     description="Requests the current status of a payment transaction",
     *     security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     *         name="crReferenceId",
     *         in="path",
     *         required=true,
     *         description="Control Record Reference ID",
     *
     * @OA\Schema(type="string",                    format="uuid")
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Payment status retrieved successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(
     *                 property="paymentStatusRecord",
     *                 type="object",
     * @OA\Property(property="crReferenceId",       type="string", format="uuid"),
     * @OA\Property(property="bqReferenceId",       type="string", format="uuid"),
     * @OA\Property(property="paymentStatus",       type="string", enum={"not_found", "completed"}),
     * @OA\Property(property="statusCheckDateTime", type="string", format="date-time"),
     * @OA\Property(property="eventCount",          type="integer")
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function requestPaymentStatus(string $crReferenceId): JsonResponse
    {
        // Query for payment events
        $events = DB::table('stored_events')
            ->where('aggregate_uuid', $crReferenceId)
            ->whereIn(
                'event_class',
                [
                    'App\Domain\Account\Events\MoneyTransferred',
                    'App\Domain\Account\Events\MoneyAdded',
                    'App\Domain\Account\Events\MoneySubtracted',
                ]
            )
            ->orderBy('created_at', 'desc')
            ->get();

        $status = $events->isEmpty() ? 'not_found' : 'completed';

        return response()->json(
            [
                'paymentStatusRecord' => [
                    'crReferenceId'       => $crReferenceId,
                    'bqReferenceId'       => Str::uuid()->toString(),
                    'paymentStatus'       => $status,
                    'statusCheckDateTime' => now()->toIso8601String(),
                    'eventCount'          => $events->count(),
                ],
            ]
        );
    }

    /**
     * Retrieve payment history.
     *
     * BIAN Operation: Retrieve
     * Behavior Qualifier: PaymentHistory
     * HTTP Method: GET
     * Path: /payment-initiation/{cr-reference-id}/payment-history/{bq-reference-id}/retrieve
     *
     * @OA\Get(
     *     path="/api/bian/payment-initiation/{accountReference}/payment-history/retrieve",
     *     operationId="retrievePaymentHistory",
     *     tags={"BIAN"},
     *     summary="Retrieve payment history",
     *     description="Retrieves the payment history for a specific account",
     *     security={{"sanctum": {}}},
     *
     * @OA\Parameter(
     *         name="accountReference",
     *         in="path",
     *         required=true,
     *         description="Account UUID reference",
     *
     * @OA\Schema(type="string",                  format="uuid")
     *     ),
     *
     * @OA\Parameter(
     *         name="fromDate",
     *         in="query",
     *         required=false,
     *         description="Start date for payment history",
     *
     * @OA\Schema(type="string",                  format="date")
     *     ),
     *
     * @OA\Parameter(
     *         name="toDate",
     *         in="query",
     *         required=false,
     *         description="End date for payment history",
     *
     * @OA\Schema(type="string",                  format="date")
     *     ),
     *
     * @OA\Parameter(
     *         name="paymentDirection",
     *         in="query",
     *         required=false,
     *         description="Filter by payment direction",
     *
     * @OA\Schema(type="string",                  enum={"sent", "received", "all"})
     *     ),
     *
     * @OA\Response(
     *         response=200,
     *         description="Payment history retrieved successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(
     *                 property="paymentHistoryRecord",
     *                 type="object",
     * @OA\Property(property="accountReference",  type="string", format="uuid"),
     * @OA\Property(property="bqReferenceId",     type="string", format="uuid"),
     * @OA\Property(
     *                     property="historyPeriod",
     *                     type="object",
     * @OA\Property(property="fromDate",          type="string", format="date"),
     * @OA\Property(property="toDate",            type="string", format="date")
     *                 ),
     * @OA\Property(
     *                     property="payments",
     *                     type="array",
     *
     * @OA\Items(
     *
     * @OA\Property(property="paymentReference",  type="string"),
     * @OA\Property(property="paymentDirection",  type="string", enum={"sent", "received"}),
     * @OA\Property(property="payerReference",    type="string"),
     * @OA\Property(property="payeeReference",    type="string"),
     * @OA\Property(property="paymentAmount",     type="integer"),
     * @OA\Property(property="paymentDateTime",   type="string", format="date-time"),
     * @OA\Property(property="paymentHash",       type="string", nullable=true)
     *                     )
     *                 ),
     * @OA\Property(property="paymentCount",      type="integer"),
     * @OA\Property(property="retrievalDateTime", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=404,
     *         description="Account not found"
     *     ),
     * @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     * @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function retrievePaymentHistory(Request $request, string $accountReference): JsonResponse
    {
        $account = Account::where('uuid', $accountReference)->firstOrFail();

        $validated = $request->validate(
            [
                'fromDate'         => 'sometimes|date',
                'toDate'           => 'sometimes|date|after_or_equal:fromDate',
                'paymentDirection' => 'sometimes|in:sent,received,all',
            ]
        );

        // Query stored events for payment history
        $query = DB::table('stored_events')
            ->where('event_class', 'App\Domain\Account\Events\MoneyTransferred')
            ->where(
                function ($q) use ($accountReference) {
                    $q->where('aggregate_uuid', $accountReference)
                        ->orWhereRaw("JSON_EXTRACT(event_properties, '$.to_uuid') = ?", [$accountReference])
                        ->orWhereRaw("JSON_EXTRACT(event_properties, '$.from_uuid') = ?", [$accountReference]);
                }
            );

        if (isset($validated['fromDate'])) {
            $query->where('created_at', '>=', $validated['fromDate']);
        }

        if (isset($validated['toDate'])) {
            $query->where('created_at', '<=', $validated['toDate']);
        }

        $events = $query->orderBy('created_at', 'desc')->get();

        $payments = $events->map(
            function ($event) use ($accountReference) {
                $properties = json_decode($event->event_properties, true);
                $fromUuid = $properties['from_uuid'] ?? $event->aggregate_uuid;
                $toUuid = $properties['to_uuid'] ?? null;

                return [
                    'paymentReference' => $event->aggregate_uuid,
                    'paymentDirection' => $fromUuid === $accountReference ? 'sent' : 'received',
                    'payerReference'   => $fromUuid,
                    'payeeReference'   => $toUuid,
                    'paymentAmount'    => $properties['money']['amount'] ?? 0,
                    'paymentDateTime'  => $event->created_at,
                    'paymentHash'      => $properties['hash']['hash'] ?? null,
                ];
            }
        );

        if (isset($validated['paymentDirection']) && $validated['paymentDirection'] !== 'all') {
            $payments = $payments->filter(
                function ($payment) use ($validated) {
                    return $payment['paymentDirection'] === $validated['paymentDirection'];
                }
            );
        }

        return response()->json(
            [
                'paymentHistoryRecord' => [
                    'accountReference' => $accountReference,
                    'bqReferenceId'    => Str::uuid()->toString(),
                    'historyPeriod'    => [
                        'fromDate' => $validated['fromDate'] ?? $account->created_at->toDateString(),
                        'toDate'   => $validated['toDate'] ?? now()->toDateString(),
                    ],
                    'payments'          => $payments->values(),
                    'paymentCount'      => $payments->count(),
                    'retrievalDateTime' => now()->toIso8601String(),
                ],
            ]
        );
    }
}
