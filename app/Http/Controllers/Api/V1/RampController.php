<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Ramp\Services\RampService;
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
    ) {
    }

    #[OA\Get(
        path: '/api/v1/ramp/quote',
        operationId: 'v1RampQuote',
        tags: ['Ramp'],
        summary: 'Get a ramp quote',
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
        description: 'Ramp quote',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'data', type: 'object', properties: [
                    new OA\Property(property: 'fiat_amount', type: 'number', example: 100),
                    new OA\Property(property: 'crypto_amount', type: 'number', example: 98.5),
                    new OA\Property(property: 'exchange_rate', type: 'number', example: 1.0),
                    new OA\Property(property: 'fee', type: 'number', example: 1.5),
                    new OA\Property(property: 'fee_currency', type: 'string', example: 'USD'),
                    new OA\Property(property: 'provider', type: 'string', example: 'mock'),
                    new OA\Property(property: 'valid_until', type: 'string', format: 'date-time'),
                ]),
            ]
        )
    )]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function quote(Request $request): JsonResponse
    {
        $request->validate([
            'type'   => 'required|string|in:on,off',
            'fiat'   => 'required|string|size:3',
            'amount' => 'required|numeric|min:1',
            'crypto' => 'required|string',
        ]);

        try {
            $quote = $this->rampService->getQuote(
                $request->input('type'),
                strtoupper($request->input('fiat')),
                (float) $request->input('amount'),
                strtoupper($request->input('crypto'))
            );

            return response()->json(['data' => $quote]);
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
        summary: 'Create a ramp session',
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
                ]
            )
        )
    )]
    #[OA\Response(response: 201, description: 'Session created')]
    #[OA\Response(response: 422, description: 'Validation error')]
    public function createSession(Request $request): JsonResponse
    {
        $request->validate([
            'type'            => 'required|string|in:on,off',
            'fiat_currency'   => 'required|string|size:3',
            'fiat_amount'     => 'required|numeric|min:1',
            'crypto_currency' => 'required|string',
            'wallet_address'  => 'required|string',
        ]);

        try {
            $session = $this->rampService->createSession(
                $request->user(),
                $request->input('type'),
                strtoupper($request->input('fiat_currency')),
                (float) $request->input('fiat_amount'),
                strtoupper($request->input('crypto_currency')),
                $request->input('wallet_address')
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
        $session = RampSession::where('id', $id)
            ->where('user_id', $request->user()->id)
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
        $sessions = $this->rampService->getUserSessions($request->user());

        return response()->json([
            'data' => RampSessionResource::collection($sessions),
        ]);
    }
}
