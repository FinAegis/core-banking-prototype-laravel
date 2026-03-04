<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Ramp\Services\RampService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use RuntimeException;

class RampWebhookController extends Controller
{
    public function __construct(
        private readonly RampService $rampService,
    ) {
    }

    #[OA\Post(
        path: '/api/v1/ramp/webhook/{provider}',
        operationId: 'v1RampWebhook',
        tags: ['Ramp'],
        summary: 'Ramp provider webhook',
        parameters: [
            new OA\Parameter(name: 'provider', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ]
    )]
    #[OA\Response(response: 200, description: 'Webhook processed')]
    #[OA\Response(response: 400, description: 'Invalid signature')]
    public function handle(Request $request, string $provider): JsonResponse
    {
        $signature = $request->header('X-Webhook-Signature', '');

        try {
            $this->rampService->handleWebhook(
                $provider,
                $request->all(),
                $signature
            );

            return response()->json(['status' => 'ok']);
        } catch (RuntimeException $e) {
            Log::warning('Ramp webhook rejected', [
                'provider' => $provider,
                'error'    => $e->getMessage(),
            ]);

            return response()->json([
                'error' => ['code' => 'WEBHOOK_ERROR', 'message' => $e->getMessage()],
            ], 400);
        }
    }
}
