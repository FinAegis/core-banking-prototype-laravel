<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Commerce;

use App\Domain\Commerce\Services\MerchantOnboardingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class MobileCommerceController extends Controller
{
    public function __construct(
        private readonly MerchantOnboardingService $merchantService,
    ) {
    }

    /**
     * List available merchants for the user.
     *
     * @OA\Get(
     *     path="/api/v1/commerce/merchants",
     *     operationId="commerceMerchants",
     *     summary="List available merchants",
     *     description="Returns a list of available merchants that accept crypto payments.",
     *     tags={"Commerce"},
     *     security={{"sanctum": {}}},
     *     @OA\Response(
     *         response=200,
     *         description="Success",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array",
     *                 @OA\Items(type="object",
     *                     @OA\Property(property="id", type="string", example="merchant_demo_001"),
     *                     @OA\Property(property="display_name", type="string", example="Demo Coffee Shop"),
     *                     @OA\Property(property="category", type="string", example="food_beverage"),
     *                     @OA\Property(property="accepted_tokens", type="array", @OA\Items(type="string", example="USDC")),
     *                     @OA\Property(property="accepted_networks", type="array", @OA\Items(type="string", example="polygon")),
     *                     @OA\Property(property="icon_url", type="string", nullable=true, example=null),
     *                     @OA\Property(property="active", type="boolean", example=true)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function merchants(Request $request): JsonResponse
    {
        $merchants = [
            [
                'id'                => 'merchant_demo_001',
                'display_name'      => 'Demo Coffee Shop',
                'category'          => 'food_beverage',
                'accepted_tokens'   => ['USDC', 'USDT'],
                'accepted_networks' => ['polygon', 'base'],
                'icon_url'          => null,
                'active'            => true,
            ],
            [
                'id'                => 'merchant_demo_002',
                'display_name'      => 'Digital Store',
                'category'          => 'retail',
                'accepted_tokens'   => ['USDC', 'USDT', 'WETH'],
                'accepted_networks' => ['polygon', 'arbitrum', 'base'],
                'icon_url'          => null,
                'active'            => true,
            ],
        ];

        return response()->json([
            'success' => true,
            'data'    => $merchants,
        ]);
    }

    /**
     * Parse a merchant QR code.
     *
     * @OA\Post(
     *     path="/api/v1/commerce/parse-qr",
     *     operationId="commerceParseQr",
     *     summary="Parse a merchant QR code",
     *     description="Parses a merchant QR code string and extracts payment details such as merchant ID, amount, asset, and network.",
     *     tags={"Commerce"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"qr_data"},
     *             @OA\Property(property="qr_data", type="string", example="finaegis://pay?merchant=merchant_demo_001&amount=25.00&asset=USDC&network=polygon", description="The raw QR code data string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="QR code parsed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="merchant_id", type="string", example="merchant_demo_001"),
     *                 @OA\Property(property="amount", type="string", example="25.00"),
     *                 @OA\Property(property="asset", type="string", example="USDC"),
     *                 @OA\Property(property="network", type="string", example="polygon"),
     *                 @OA\Property(property="metadata", type="object")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Invalid QR code or validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="error", type="object",
     *                 @OA\Property(property="code", type="string", example="INVALID_QR"),
     *                 @OA\Property(property="message", type="string", example="Unable to parse QR code.")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function parseQr(Request $request): JsonResponse
    {
        $request->validate([
            'qr_data' => ['required', 'string'],
        ]);

        $qrData = $request->input('qr_data');

        // Parse QR code format: finaegis://pay?merchant=X&amount=Y&asset=Z&network=N
        $parsed = parse_url($qrData);
        $params = [];
        if (isset($parsed['query'])) {
            parse_str($parsed['query'], $params);
        }

        if (empty($params['merchant'])) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'INVALID_QR',
                    'message' => 'Unable to parse QR code.',
                ],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'merchant_id' => $params['merchant'] ?? null,
                'amount'      => $params['amount'] ?? null,
                'asset'       => $params['asset'] ?? 'USDC',
                'network'     => $params['network'] ?? 'polygon',
                'metadata'    => $params,
            ],
        ]);
    }

    /**
     * Create a payment request for a merchant.
     *
     * @OA\Post(
     *     path="/api/v1/commerce/payment-requests",
     *     operationId="commerceCreatePaymentRequest",
     *     summary="Create a payment request",
     *     description="Creates a new payment request for a merchant with a specified amount, asset, and network. The request expires after 15 minutes.",
     *     tags={"Commerce"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"merchant_id", "amount", "asset", "network"},
     *             @OA\Property(property="merchant_id", type="string", example="merchant_demo_001", description="The merchant ID to create the payment request for"),
     *             @OA\Property(property="amount", type="string", example="25.00", description="The payment amount"),
     *             @OA\Property(property="asset", type="string", enum={"USDC", "USDT", "WETH", "WBTC"}, example="USDC", description="The token asset for payment"),
     *             @OA\Property(property="network", type="string", example="polygon", description="The blockchain network for the transaction")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Payment request created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", example="pr_abc123def456"),
     *                 @OA\Property(property="merchant_id", type="string", example="merchant_demo_001"),
     *                 @OA\Property(property="amount", type="string", example="25.00"),
     *                 @OA\Property(property="asset", type="string", example="USDC"),
     *                 @OA\Property(property="network", type="string", example="polygon"),
     *                 @OA\Property(property="status", type="string", example="pending"),
     *                 @OA\Property(property="created_at", type="string", format="date-time"),
     *                 @OA\Property(property="expires_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function createPaymentRequest(Request $request): JsonResponse
    {
        $request->validate([
            'merchant_id' => ['required', 'string'],
            'amount'      => ['required', 'string'],
            'asset'       => ['required', 'string', 'in:USDC,USDT,WETH,WBTC'],
            'network'     => ['required', 'string'],
        ]);

        $paymentRequest = [
            'id'          => 'pr_' . Str::random(20),
            'merchant_id' => $request->input('merchant_id'),
            'amount'      => $request->input('amount'),
            'asset'       => $request->input('asset'),
            'network'     => $request->input('network'),
            'status'      => 'pending',
            'created_at'  => now()->toIso8601String(),
            'expires_at'  => now()->addMinutes(15)->toIso8601String(),
        ];

        return response()->json([
            'success' => true,
            'data'    => $paymentRequest,
        ], 201);
    }

    /**
     * Process a commerce payment.
     *
     * @OA\Post(
     *     path="/api/v1/commerce/payments",
     *     operationId="commerceProcessPayment",
     *     summary="Process a commerce payment",
     *     description="Processes a payment for an existing payment request. Initiates the blockchain transaction for the commerce payment.",
     *     tags={"Commerce"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"payment_request_id"},
     *             @OA\Property(property="payment_request_id", type="string", example="pr_abc123def456", description="The payment request ID to process")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Payment processing initiated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="id", type="string", example="pay_abc123def456"),
     *                 @OA\Property(property="payment_request_id", type="string", example="pr_abc123def456"),
     *                 @OA\Property(property="status", type="string", example="processing"),
     *                 @OA\Property(property="created_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function processPayment(Request $request): JsonResponse
    {
        $request->validate([
            'payment_request_id' => ['required', 'string'],
        ]);

        $payment = [
            'id'                 => 'pay_' . Str::random(20),
            'payment_request_id' => $request->input('payment_request_id'),
            'status'             => 'processing',
            'created_at'         => now()->toIso8601String(),
        ];

        return response()->json([
            'success' => true,
            'data'    => $payment,
        ], 201);
    }

    /**
     * Generate a payment QR code.
     *
     * @OA\Post(
     *     path="/api/v1/commerce/generate-qr",
     *     operationId="commerceGenerateQr",
     *     summary="Generate a payment QR code",
     *     description="Generates a QR code data string for receiving a payment. The QR code expires after 30 minutes.",
     *     tags={"Commerce"},
     *     security={{"sanctum": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"amount", "asset", "network"},
     *             @OA\Property(property="amount", type="string", example="25.00", description="The payment amount"),
     *             @OA\Property(property="asset", type="string", enum={"USDC", "USDT", "WETH", "WBTC"}, example="USDC", description="The token asset for payment"),
     *             @OA\Property(property="network", type="string", example="polygon", description="The blockchain network for the transaction")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="QR code generated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="qr_data", type="string", example="finaegis://pay?to=user_1&amount=25.00&asset=USDC&network=polygon"),
     *                 @OA\Property(property="expires_at", type="string", format="date-time")
     *             )
     *         )
     *     ),
     *     @OA\Response(response=401, description="Unauthorized"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function generateQr(Request $request): JsonResponse
    {
        $request->validate([
            'amount'  => ['required', 'string'],
            'asset'   => ['required', 'string', 'in:USDC,USDT,WETH,WBTC'],
            'network' => ['required', 'string'],
        ]);

        $user = $request->user();
        $qrData = sprintf(
            'finaegis://pay?to=%s&amount=%s&asset=%s&network=%s',
            'user_' . $user->id,
            $request->input('amount'),
            $request->input('asset'),
            $request->input('network'),
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'qr_data'    => $qrData,
                'expires_at' => now()->addMinutes(30)->toIso8601String(),
            ],
        ]);
    }
}
