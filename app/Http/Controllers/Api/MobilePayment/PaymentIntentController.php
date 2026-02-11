<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\MobilePayment;

use App\Domain\MobilePayment\Contracts\PaymentIntentServiceInterface;
use App\Domain\MobilePayment\Exceptions\MerchantNotFoundException;
use App\Domain\MobilePayment\Exceptions\PaymentIntentException;
use App\Http\Controllers\Controller;
use App\Http\Requests\MobilePayment\CreatePaymentIntentRequest;
use App\Http\Requests\MobilePayment\SubmitPaymentIntentRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Mobile Payments",
 *     description="Mobile payment intents, transactions, receipts, and wallet operations"
 * )
 */
class PaymentIntentController extends Controller
{
    public function __construct(
        private readonly PaymentIntentServiceInterface $paymentIntentService,
    ) {
    }

    /**
     * Create a new payment intent.
     *
     * POST /v1/payments/intents
     *
     * @OA\Post(
     *     path="/api/v1/payments/intents",
     *     operationId="mobilePaymentCreateIntent",
     *     summary="Create a new payment intent",
     *     description="Creates a payment intent for a merchant transaction. Supports idempotency via X-Idempotency-Key header or body field. The intent must be submitted separately to authorize payment.",
     *     tags={"Mobile Payments"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="X-Idempotency-Key",
     *         in="header",
     *         required=false,
     *         description="Idempotency key to prevent duplicate payments",
     *         @OA\Schema(type="string", maxLength=128)
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"merchantId", "amount", "asset", "preferredNetwork"},
     *             @OA\Property(property="merchantId", type="string", example="merchant_abc123", description="Merchant identifier (max 64 chars)"),
     *             @OA\Property(property="amount", type="number", example=25.50, description="Payment amount (must be > 0)"),
     *             @OA\Property(property="asset", type="string", enum={"USDC"}, example="USDC", description="Payment asset"),
     *             @OA\Property(property="preferredNetwork", type="string", enum={"SOLANA", "TRON"}, example="SOLANA", description="Preferred payment network"),
     *             @OA\Property(property="shield", type="boolean", example=false, description="Enable privacy shield"),
     *             @OA\Property(property="idempotencyKey", type="string", example="idem_key_123", description="Idempotency key (alternative to header)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Payment intent created",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="intentId", type="string", example="pi_abc123"),
     *                 @OA\Property(property="status", type="string", example="PENDING"),
     *                 @OA\Property(property="amount", type="number", example=25.50),
     *                 @OA\Property(property="asset", type="string", example="USDC"),
     *                 @OA\Property(property="merchant", type="object",
     *                     @OA\Property(property="displayName", type="string", example="Coffee Shop")
     *                 )
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
     *         response=404,
     *         description="Merchant not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="MERCHANT_NOT_FOUND"),
     *                 @OA\Property(property="message", type="string", example="Merchant not found.")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error or payment intent creation failed",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="PAYMENT_INTENT_ERROR"),
     *                 @OA\Property(property="message", type="string", example="Unable to create payment intent.")
     *             )
     *         )
     *     )
     * )
     */
    public function create(CreatePaymentIntentRequest $request): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            $user = $request->user();

            $data = $request->validated();

            // Accept idempotency key from header (preferred) or body
            if (! isset($data['idempotencyKey']) && $request->hasHeader('X-Idempotency-Key')) {
                $data['idempotencyKey'] = $request->header('X-Idempotency-Key');
            }

            $intent = $this->paymentIntentService->create(
                $user->id,
                $data,
            );

            return response()->json([
                'success' => true,
                'data'    => $intent->toApiResponse(),
            ], 201);
        } catch (MerchantNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'MERCHANT_NOT_FOUND',
                    'message' => $e->getMessage(),
                ],
            ], 404);
        } catch (PaymentIntentException $e) {
            return response()->json($e->toApiResponse(), $e->httpStatus());
        }
    }

    /**
     * Get payment intent status.
     *
     * GET /v1/payments/intents/{intentId}
     *
     * @OA\Get(
     *     path="/api/v1/payments/intents/{intentId}",
     *     operationId="mobilePaymentGetIntent",
     *     summary="Get payment intent status",
     *     description="Retrieves the current status and details of a payment intent by its ID.",
     *     tags={"Mobile Payments"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="intentId",
     *         in="path",
     *         required=true,
     *         description="Payment intent ID",
     *         @OA\Schema(type="string", example="pi_abc123")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment intent details",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="intentId", type="string", example="pi_abc123"),
     *                 @OA\Property(property="status", type="string", example="PENDING"),
     *                 @OA\Property(property="amount", type="number", example=25.50),
     *                 @OA\Property(property="asset", type="string", example="USDC"),
     *                 @OA\Property(property="merchant", type="object",
     *                     @OA\Property(property="displayName", type="string", example="Coffee Shop")
     *                 )
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
     *         response=404,
     *         description="Payment intent not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="INTENT_NOT_FOUND"),
     *                 @OA\Property(property="message", type="string", example="Payment intent not found.")
     *             )
     *         )
     *     )
     * )
     */
    public function show(Request $request, string $intentId): JsonResponse
    {
        try {
            /** @var \App\Models\User $user */
            $user = $request->user();

            $intent = $this->paymentIntentService->get(
                $intentId,
                (int) $user->id,
            );

            return response()->json([
                'success' => true,
                'data'    => $intent->toApiResponse(),
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'INTENT_NOT_FOUND',
                    'message' => 'Payment intent not found.',
                ],
            ], 404);
        }
    }

    /**
     * Submit/authorize a payment intent.
     *
     * POST /v1/payments/intents/{intentId}/submit
     *
     * @OA\Post(
     *     path="/api/v1/payments/intents/{intentId}/submit",
     *     operationId="mobilePaymentSubmitIntent",
     *     summary="Submit and authorize a payment intent",
     *     description="Submits a pending payment intent for authorization. The user must authenticate via biometric or PIN before the payment is processed on-chain.",
     *     tags={"Mobile Payments"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="intentId",
     *         in="path",
     *         required=true,
     *         description="Payment intent ID",
     *         @OA\Schema(type="string", example="pi_abc123")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="auth", type="string", enum={"biometric", "pin"}, example="biometric", description="Authentication method"),
     *             @OA\Property(property="shield", type="boolean", example=false, description="Enable privacy shield")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment intent submitted",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="intentId", type="string", example="pi_abc123"),
     *                 @OA\Property(property="status", type="string", example="SUBMITTED")
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
     *         response=404,
     *         description="Payment intent not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="INTENT_NOT_FOUND"),
     *                 @OA\Property(property="message", type="string", example="Payment intent not found.")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Payment intent cannot be submitted (invalid state)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="PAYMENT_INTENT_ERROR"),
     *                 @OA\Property(property="message", type="string", example="Intent is not in a submittable state.")
     *             )
     *         )
     *     )
     * )
     */
    public function submit(SubmitPaymentIntentRequest $request, string $intentId): JsonResponse
    {
        try {
            $authType = $request->input('auth', 'biometric');

            /** @var \App\Models\User $user */
            $user = $request->user();

            $intent = $this->paymentIntentService->submit(
                $intentId,
                $user->id,
                $authType,
            );

            // Spec requires minimal response: only intentId + status
            return response()->json([
                'success' => true,
                'data'    => [
                    'intentId' => $intent->public_id,
                    'status'   => strtoupper($intent->status->value),
                ],
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'INTENT_NOT_FOUND',
                    'message' => 'Payment intent not found.',
                ],
            ], 404);
        } catch (PaymentIntentException $e) {
            return response()->json($e->toApiResponse(), $e->httpStatus());
        }
    }

    /**
     * Cancel a payment intent.
     *
     * POST /v1/payments/intents/{intentId}/cancel
     *
     * @OA\Post(
     *     path="/api/v1/payments/intents/{intentId}/cancel",
     *     operationId="mobilePaymentCancelIntent",
     *     summary="Cancel a payment intent",
     *     description="Cancels a pending payment intent. An optional reason can be provided for auditing purposes.",
     *     tags={"Mobile Payments"},
     *     security={{"sanctum": {}}},
     *     @OA\Parameter(
     *         name="intentId",
     *         in="path",
     *         required=true,
     *         description="Payment intent ID",
     *         @OA\Schema(type="string", example="pi_abc123")
     *     ),
     *     @OA\RequestBody(
     *         required=false,
     *         @OA\JsonContent(
     *             @OA\Property(property="reason", type="string", nullable=true, example="Changed my mind", description="Cancellation reason (max 500 chars)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment intent cancelled",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="intentId", type="string", example="pi_abc123"),
     *                 @OA\Property(property="status", type="string", example="CANCELLED"),
     *                 @OA\Property(property="merchant", type="object",
     *                     @OA\Property(property="displayName", type="string", example="Coffee Shop")
     *                 ),
     *                 @OA\Property(property="amount", type="number", example=25.50)
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
     *         response=404,
     *         description="Payment intent not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="INTENT_NOT_FOUND"),
     *                 @OA\Property(property="message", type="string", example="Payment intent not found.")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Payment intent cannot be cancelled (invalid state)",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="PAYMENT_INTENT_ERROR"),
     *                 @OA\Property(property="message", type="string", example="Intent is not in a cancellable state.")
     *             )
     *         )
     *     )
     * )
     */
    public function cancel(Request $request, string $intentId): JsonResponse
    {
        try {
            $request->validate([
                'reason' => ['sometimes', 'nullable', 'string', 'max:500'],
            ]);
            $reason = $request->input('reason');

            /** @var \App\Models\User $user */
            $user = $request->user();

            $intent = $this->paymentIntentService->cancel(
                $intentId,
                $user->id,
                $reason,
            );

            // Spec requires minimal response: intentId, status, merchant.displayName, amount
            return response()->json([
                'success' => true,
                'data'    => [
                    'intentId' => $intent->public_id,
                    'status'   => strtoupper($intent->status->value),
                    'merchant' => [
                        'displayName' => $intent->merchant?->display_name,
                    ],
                    'amount' => $intent->amount,
                ],
            ]);
        } catch (ModelNotFoundException) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'INTENT_NOT_FOUND',
                    'message' => 'Payment intent not found.',
                ],
            ], 404);
        } catch (PaymentIntentException $e) {
            return response()->json($e->toApiResponse(), $e->httpStatus());
        }
    }
}
