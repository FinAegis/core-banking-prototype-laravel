<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Ramp\Services\RampService;
use App\Domain\Ramp\Services\StripeBridgeService;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\RampSessionResource;
use App\Models\RampSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;
use RuntimeException;

class RampController extends Controller
{
    public function __construct(
        private readonly RampService $rampService,
        private readonly StripeBridgeService $stripeBridgeService,
    ) {
    }

    #[OA\Get(
        path: '/api/v1/ramp/quotes',
        operationId: 'v1RampQuotes',
        tags: ['Ramp'],
        summary: 'Get quotes from all aggregated providers',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'type', in: 'query', required: true, schema: new OA\Schema(type: 'string', enum: ['on', 'off'])),
            new OA\Parameter(name: 'fiat', in: 'query', required: true, schema: new OA\Schema(type: 'string', example: 'USD')),
            new OA\Parameter(name: 'amount', in: 'query', required: true, schema: new OA\Schema(type: 'number', example: 100)),
            new OA\Parameter(name: 'crypto', in: 'query', required: true, schema: new OA\Schema(type: 'string', example: 'USDC')),
        ]
    )]
    #[OA\Response(
        response: 200,
        description: 'Provider quotes',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'quotes', type: 'array', items: new OA\Items(type: 'object', properties: [
                        new OA\Property(property: 'provider_name', type: 'string', example: 'Simplex'),
                        new OA\Property(property: 'quote_id', type: 'string', example: 'q_12345'),
                        new OA\Property(property: 'fiat_amount', type: 'number', example: 100),
                        new OA\Property(property: 'crypto_amount', type: 'number', example: 0.0025),
                        new OA\Property(property: 'exchange_rate', type: 'number', example: 0.000026),
                        new OA\Property(property: 'fee', type: 'number', example: 3.5),
                        new OA\Property(property: 'network_fee', type: 'number', example: 0.5),
                        new OA\Property(property: 'fee_currency', type: 'string', example: 'USD'),
                        new OA\Property(property: 'payment_methods', type: 'array', items: new OA\Items(type: 'string')),
                    ])),
                    new OA\Property(property: 'provider', type: 'string', example: 'stripe_bridge'),
                    new OA\Property(property: 'valid_until', type: 'string', format: 'date-time'),
                ]),
            ]
        )
    )]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function quotes(Request $request): JsonResponse
    {
        $request->validate([
            'type'   => 'required|string|in:on,off',
            'fiat'   => 'required|string|size:3',
            'amount' => 'required|numeric|min:1',
            'crypto' => 'required|string',
        ]);

        try {
            $result = $this->rampService->getQuotes(
                $request->input('type'),
                strtoupper($request->input('fiat')),
                (string) $request->input('amount'),
                strtoupper($request->input('crypto'))
            );

            return response()->json(['data' => $result]);
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => ['code' => 'QUOTE_ERROR', 'message' => $e->getMessage()],
            ], 422);
        }
    }

    #[OA\Post(
        path: '/api/v1/ramp/session',
        operationId: 'v1CreateRampSession',
        tags: ['Ramp'],
        summary: 'Create a ramp session with a selected quote',
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['type', 'fiat_currency', 'fiat_amount', 'crypto_currency', 'wallet_address'],
                properties: [
                    new OA\Property(property: 'type', type: 'string', enum: ['on', 'off']),
                    new OA\Property(property: 'fiat_currency', type: 'string', example: 'USD'),
                    new OA\Property(property: 'fiat_amount', type: 'number', example: 100),
                    new OA\Property(property: 'crypto_currency', type: 'string', example: 'USDC'),
                    new OA\Property(property: 'wallet_address', type: 'string', example: '0x...'),
                    new OA\Property(property: 'quote_id', type: 'string', example: 'q_12345', description: 'Selected quote ID from GET /quotes'),
                ]
            )
        )
    )]
    #[OA\Response(response: 201, description: 'Session created with checkout_url')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function createSession(Request $request): JsonResponse
    {
        $request->validate([
            'type'            => 'required|string|in:on,off',
            'fiat_currency'   => 'required|string|size:3',
            'fiat_amount'     => 'required|numeric|min:1',
            'crypto_currency' => 'required|string',
            'wallet_address'  => 'required|string',
            'quote_id'        => 'nullable|string',
        ]);

        try {
            /** @var \App\Models\User $user */
            $user = $request->user();
            $session = $this->rampService->createSession(
                $user,
                $request->input('type'),
                strtoupper($request->input('fiat_currency')),
                (string) $request->input('fiat_amount'),
                strtoupper($request->input('crypto_currency')),
                $request->input('wallet_address'),
                $request->input('quote_id')
            );

            return response()->json([
                'data' => new RampSessionResource($session),
            ], 201);
        } catch (RuntimeException $e) {
            return response()->json([
                'error' => ['code' => 'SESSION_ERROR', 'message' => $e->getMessage()],
            ], 422);
        }
    }

    #[OA\Get(
        path: '/api/v1/ramp/session/{id}',
        operationId: 'v1GetRampSession',
        tags: ['Ramp'],
        summary: 'Get ramp session status',
        security: [['sanctum' => []]],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[OA\Response(response: 200, description: 'Session status')]
    #[OA\Response(response: 404, description: 'Not found')]
    public function getSession(Request $request, string $id): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $session = RampSession::where('id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (! $session) {
            return response()->json([
                'error' => ['code' => 'NOT_FOUND', 'message' => 'Session not found'],
            ], 404);
        }

        $session = $this->rampService->getSessionStatus($session);

        return response()->json([
            'data' => new RampSessionResource($session),
        ]);
    }

    #[OA\Get(
        path: '/api/v1/ramp/sessions',
        operationId: 'v1ListRampSessions',
        tags: ['Ramp'],
        summary: 'List user ramp sessions',
        security: [['sanctum' => []]]
    )]
    #[OA\Response(response: 200, description: 'Session list')]
    public function listSessions(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $sessions = $this->rampService->getUserSessions($user);

        return response()->json([
            'data' => RampSessionResource::collection($sessions),
        ]);
    }

    #[OA\Get(
        path: '/api/v1/ramp/supported',
        operationId: 'v1RampSupported',
        tags: ['Ramp'],
        summary: 'Get supported currencies and provider info',
        security: [['sanctum' => []]]
    )]
    #[OA\Response(
        response: 200,
        description: 'Supported currencies',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'provider', type: 'string', example: 'stripe_bridge'),
                    new OA\Property(property: 'fiat_currencies', type: 'array', items: new OA\Items(type: 'string'), example: '["USD","EUR","GBP"]'),
                    new OA\Property(property: 'crypto_currencies', type: 'array', items: new OA\Items(type: 'string'), example: '["USDC","USDT","ETH","BTC"]'),
                    new OA\Property(property: 'modes', type: 'array', items: new OA\Items(type: 'object', properties: [
                        new OA\Property(property: 'type', type: 'string', example: 'on'),
                        new OA\Property(property: 'label', type: 'string', example: 'Buy Crypto'),
                    ])),
                ]),
            ]
        )
    )]
    public function supported(): JsonResponse
    {
        $provider = (string) config('ramp.default_provider');

        if ($provider === 'stripe_bridge') {
            $supported = $this->stripeBridgeService->getSupportedCurrencies();

            return response()->json([
                'data' => [
                    'provider'          => 'stripe_bridge',
                    'fiat_currencies'   => $supported['fiatCurrencies'],
                    'crypto_currencies' => $supported['cryptoCurrencies'],
                    'modes'             => [
                        ['type' => 'on', 'label' => 'Buy Crypto'],
                        ['type' => 'off', 'label' => 'Sell Crypto'],
                    ],
                    'limits' => [
                        'min_amount'  => $supported['limits']['minAmount'],
                        'max_amount'  => $supported['limits']['maxAmount'],
                        'daily_limit' => $supported['limits']['dailyLimit'],
                    ],
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'provider'          => $provider,
                'fiat_currencies'   => config('ramp.supported_fiat'),
                'crypto_currencies' => config('ramp.supported_crypto'),
                'modes'             => [
                    ['type' => 'on', 'label' => 'Buy Crypto'],
                    ['type' => 'off', 'label' => 'Sell Crypto'],
                ],
                'limits' => [
                    'min_amount'  => config('ramp.limits.min_fiat_amount'),
                    'max_amount'  => config('ramp.limits.max_fiat_amount'),
                    'daily_limit' => config('ramp.limits.daily_limit'),
                ],
            ],
        ]);
    }
}
