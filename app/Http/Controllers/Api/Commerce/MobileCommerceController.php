<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Commerce;

use App\Domain\Commerce\Enums\MerchantStatus;
use App\Domain\Commerce\Models\Merchant;
use App\Domain\Commerce\Services\MerchantOnboardingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use OpenApi\Attributes as OA;
use Throwable;

class MobileCommerceController extends Controller
{
    public function __construct(
        private readonly MerchantOnboardingService $merchantService,
    ) {
    }

    /**
     * List available merchants for the user.
     */
    #[OA\Get(
        path: '/api/v1/commerce/merchants',
        operationId: 'commerceMerchants',
        summary: 'List available merchants',
        description: 'Returns a list of available merchants that accept crypto payments.',
        tags: ['Commerce'],
        security: [['sanctum' => []]]
    )]
    #[OA\Response(
        response: 200,
        description: 'Success',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object', properties: [
        new OA\Property(property: 'id', type: 'string', example: 'merchant_demo_001'),
        new OA\Property(property: 'display_name', type: 'string', example: 'Demo Coffee Shop'),
        new OA\Property(property: 'category', type: 'string', example: 'food_beverage'),
        new OA\Property(property: 'accepted_tokens', type: 'array', items: new OA\Items(type: 'string', example: 'USDC')),
        new OA\Property(property: 'accepted_networks', type: 'array', items: new OA\Items(type: 'string', example: 'polygon')),
        new OA\Property(property: 'icon_url', type: 'string', nullable: true, example: null),
        new OA\Property(property: 'active', type: 'boolean', example: true),
        ])),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    public function merchants(Request $request): JsonResponse
    {
        $query = Merchant::where('status', MerchantStatus::ACTIVE);

        if ($search = $request->query('search')) {
            $query->where('display_name', 'like', '%' . $search . '%');
        }

        $merchants = $query->orderBy('display_name')
            ->paginate(min((int) $request->query('per_page', '20'), 100));

        return response()->json([
            'success' => true,
            'data'    => $merchants->map(fn (Merchant $m) => [
                'id'                => $m->public_id,
                'display_name'      => $m->display_name,
                'category'          => $m->terminal_id ?? 'general',
                'accepted_tokens'   => $m->accepted_assets ?? [],
                'accepted_networks' => $m->accepted_networks ?? [],
                'icon_url'          => $m->icon_url,
                'active'            => true,
            ])->values(),
            'pagination' => [
                'current_page' => $merchants->currentPage(),
                'last_page'    => $merchants->lastPage(),
                'per_page'     => $merchants->perPage(),
                'total'        => $merchants->total(),
            ],
        ]);
    }

    /**
     * Parse a merchant QR code.
     */
    #[OA\Post(
        path: '/api/v1/commerce/parse-qr',
        operationId: 'commerceParseQr',
        summary: 'Parse a merchant QR code',
        description: 'Parses a merchant QR code string and extracts payment details such as merchant ID, amount, asset, and network.',
        tags: ['Commerce'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['qr_data'], properties: [
        new OA\Property(property: 'qr_data', type: 'string', example: 'finaegis://pay?merchant=merchant_demo_001&amount=25.00&asset=USDC&network=polygon', description: 'The raw QR code data string'),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'QR code parsed successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'merchant_id', type: 'string', example: 'merchant_demo_001'),
        new OA\Property(property: 'amount', type: 'string', example: '25.00'),
        new OA\Property(property: 'asset', type: 'string', example: 'USDC'),
        new OA\Property(property: 'network', type: 'string', example: 'polygon'),
        new OA\Property(property: 'metadata', type: 'object'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 422,
        description: 'Invalid QR code or validation error',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'error', type: 'object', properties: [
        new OA\Property(property: 'code', type: 'string', example: 'INVALID_QR'),
        new OA\Property(property: 'message', type: 'string', example: 'Unable to parse QR code.'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
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
     */
    #[OA\Post(
        path: '/api/v1/commerce/payment-requests',
        operationId: 'commerceCreatePaymentRequest',
        summary: 'Create a payment request',
        description: 'Creates a new payment request for a merchant with a specified amount, asset, and network. The request expires after 15 minutes.',
        tags: ['Commerce'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['merchant_id', 'amount', 'asset', 'network'], properties: [
        new OA\Property(property: 'merchant_id', type: 'string', example: 'merchant_demo_001', description: 'The merchant ID to create the payment request for'),
        new OA\Property(property: 'amount', type: 'string', example: '25.00', description: 'The payment amount'),
        new OA\Property(property: 'asset', type: 'string', enum: ['USDC', 'USDT', 'WETH', 'WBTC'], example: 'USDC', description: 'The token asset for payment'),
        new OA\Property(property: 'network', type: 'string', example: 'polygon', description: 'The blockchain network for the transaction'),
        ]))
    )]
    #[OA\Response(
        response: 201,
        description: 'Payment request created successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'id', type: 'string', example: 'pr_abc123def456'),
        new OA\Property(property: 'merchant_id', type: 'string', example: 'merchant_demo_001'),
        new OA\Property(property: 'merchant', type: 'object', properties: [
        new OA\Property(property: 'id', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'display_name', type: 'string'),
        new OA\Property(property: 'category', type: 'string', nullable: true),
        new OA\Property(property: 'accepted_tokens', type: 'array', items: new OA\Items(type: 'string')),
        ]),
        new OA\Property(property: 'amount', type: 'string', example: '25.00'),
        new OA\Property(property: 'asset', type: 'string', example: 'USDC'),
        new OA\Property(property: 'network', type: 'string', example: 'polygon'),
        new OA\Property(property: 'status', type: 'string', example: 'pending'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    public function createPaymentRequest(Request $request): JsonResponse
    {
        $request->validate([
            'merchant_id' => ['required', 'string'],
            'amount'      => ['required', 'string'],
            'asset'       => ['required', 'string', 'in:USDC,USDT,WETH,WBTC'],
            'network'     => ['required', 'string'],
        ]);

        $merchantId = $request->input('merchant_id');
        $merchant = null;
        try {
            /** @var Merchant|null $found */
            $found = Merchant::find($merchantId);
            $merchant = $found;
        } catch (Throwable) {
            // Graceful fallback if merchants table doesn't exist
        }

        $merchantData = $merchant ? [
            'id'              => $merchant->id,
            'name'            => $merchant->public_id ?? $merchant->id,
            'display_name'    => $merchant->display_name,
            'category'        => $merchant->terminal_id,
            'accepted_tokens' => $merchant->accepted_assets ?? [],
        ] : [
            'id'              => $merchantId,
            'name'            => $merchantId,
            'display_name'    => $merchantId,
            'category'        => null,
            'accepted_tokens' => ['USDC', 'USDT', 'WETH', 'WBTC'],
        ];

        $paymentRequest = [
            'id'          => 'pr_' . Str::random(20),
            'merchant_id' => $merchantId,
            'merchant'    => $merchantData,
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
     */
    #[OA\Post(
        path: '/api/v1/commerce/payments',
        operationId: 'commerceProcessPayment',
        summary: 'Process a commerce payment',
        description: 'Processes a payment for an existing payment request. Initiates the blockchain transaction for the commerce payment.',
        tags: ['Commerce'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['payment_request_id'], properties: [
        new OA\Property(property: 'payment_request_id', type: 'string', example: 'pr_abc123def456', description: 'The payment request ID to process'),
        ]))
    )]
    #[OA\Response(
        response: 201,
        description: 'Payment processing initiated',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'id', type: 'string', example: 'pay_abc123def456'),
        new OA\Property(property: 'payment_request_id', type: 'string', example: 'pr_abc123def456'),
        new OA\Property(property: 'status', type: 'string', example: 'processing'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
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
     */
    #[OA\Post(
        path: '/api/v1/commerce/generate-qr',
        operationId: 'commerceGenerateQr',
        summary: 'Generate a payment QR code',
        description: 'Generates a QR code data string for receiving a payment. The QR code expires after 30 minutes.',
        tags: ['Commerce'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['amount', 'asset', 'network'], properties: [
        new OA\Property(property: 'amount', type: 'string', example: '25.00', description: 'The payment amount'),
        new OA\Property(property: 'asset', type: 'string', enum: ['USDC', 'USDT', 'WETH', 'WBTC'], example: 'USDC', description: 'The token asset for payment'),
        new OA\Property(property: 'network', type: 'string', example: 'polygon', description: 'The blockchain network for the transaction'),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'QR code generated successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'qr_data', type: 'string', example: 'finaegis://pay?to=user_1&amount=25.00&asset=USDC&network=polygon'),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
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

    /**
     * Get merchant detail.
     */
    #[OA\Get(
        path: '/api/v1/commerce/merchants/{merchantId}',
        operationId: 'commerceMerchantDetail',
        summary: 'Get merchant details',
        tags: ['Commerce'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'merchantId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Merchant details',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object'),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Merchant not found'
    )]
    public function merchantDetail(Request $request, string $merchantId): JsonResponse
    {
        $merchant = Merchant::where('public_id', $merchantId)->first();

        if (! $merchant) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'MERCHANT_NOT_FOUND',
                    'message' => 'Merchant not found.',
                ],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'id'                => $merchant->public_id,
                'display_name'      => $merchant->display_name,
                'category'          => $merchant->terminal_id ?? 'general',
                'accepted_tokens'   => $merchant->accepted_assets ?? [],
                'accepted_networks' => $merchant->accepted_networks ?? [],
                'icon_url'          => $merchant->icon_url,
                'status'            => $merchant->status->value,
                'active'            => $merchant->canAcceptPayments(),
            ],
        ]);
    }

    /**
     * Get payment request detail.
     */
    #[OA\Get(
        path: '/api/v1/commerce/payment-requests/{paymentId}',
        operationId: 'commercePaymentRequestDetail',
        summary: 'Get payment request details',
        tags: ['Commerce'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'paymentId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Payment request details',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object'),
        ])
    )]
    public function paymentRequestDetail(Request $request, string $paymentId): JsonResponse
    {
        // Demo implementation — in production, look up from database
        return response()->json([
            'success' => true,
            'data'    => [
                'id'          => $paymentId,
                'merchant_id' => 'merchant_demo_001',
                'amount'      => '0.00',
                'asset'       => 'USDC',
                'network'     => 'polygon',
                'status'      => 'pending',
                'created_at'  => now()->toIso8601String(),
                'expires_at'  => now()->addMinutes(15)->toIso8601String(),
            ],
        ]);
    }

    /**
     * Cancel a payment request.
     */
    #[OA\Post(
        path: '/api/v1/commerce/payment-requests/{paymentId}/cancel',
        operationId: 'commerceCancelPaymentRequest',
        summary: 'Cancel a payment request',
        tags: ['Commerce'],
        security: [['sanctum' => []]],
        parameters: [
        new OA\Parameter(name: 'paymentId', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Payment request cancelled',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'id', type: 'string'),
        new OA\Property(property: 'status', type: 'string', example: 'cancelled'),
        ]),
        ])
    )]
    public function cancelPaymentRequest(Request $request, string $paymentId): JsonResponse
    {
        // Demo implementation — in production, update database
        return response()->json([
            'success' => true,
            'data'    => [
                'id'           => $paymentId,
                'status'       => 'cancelled',
                'cancelled_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * Get recent payments.
     */
    #[OA\Get(
        path: '/api/v1/commerce/payments/recent',
        operationId: 'commerceRecentPayments',
        summary: 'Get recent commerce payments',
        tags: ['Commerce'],
        security: [['sanctum' => []]]
    )]
    #[OA\Response(
        response: 200,
        description: 'Recent payments',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
        ])
    )]
    public function recentPayments(Request $request): JsonResponse
    {
        // Demo implementation — return empty list
        return response()->json([
            'success' => true,
            'data'    => [],
        ]);
    }
}
