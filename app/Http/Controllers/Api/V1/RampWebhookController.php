<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Ramp\Exceptions\InvalidWebhookSignatureException;
use App\Domain\Ramp\Registries\RampProviderRegistry;
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
        private readonly RampProviderRegistry $registry,
    ) {
    }

    #[OA\Post(
        path: '/api/v1/ramp/webhook/{provider}',
        operationId: 'v1RampWebhook',
        tags: ['Ramp'],
        summary: 'Ramp provider webhook (Stripe Bridge, Onramper, etc.)',
        parameters: [
            new OA\Parameter(name: 'provider', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['stripe_bridge', 'onramper', 'mock'])),
        ]
    )]
    #[OA\Response(response: 200, description: 'Webhook processed')]
    #[OA\Response(response: 400, description: 'Invalid signature or malformed body')]
    #[OA\Response(response: 404, description: 'Unknown provider')]
    #[OA\Response(response: 500, description: 'Processing error')]
    public function handle(Request $request, string $provider): JsonResponse
    {
        $providerInstance = $this->registry->resolve($provider);
        if (! $providerInstance) {
            return response()->json([
                'error' => ['code' => 'UNKNOWN_PROVIDER', 'message' => "Unknown ramp provider: {$provider}"],
            ], 404);
        }

        $rawBody = $request->getContent();
        $signatureHeader = (string) $request->header($providerInstance->getWebhookSignatureHeader(), '');

        try {
            $this->rampService->handleWebhook($providerInstance, $rawBody, $signatureHeader);
        } catch (InvalidWebhookSignatureException $e) {
            Log::warning('Ramp webhook signature rejected', [
                'provider' => $provider,
                'ip'       => $request->ip(),
            ]);

            return response()->json([
                'error' => ['code' => 'INVALID_SIGNATURE', 'message' => 'Webhook signature verification failed'],
            ], 400);
        } catch (RuntimeException $e) {
            Log::error('Ramp webhook processing failed', [
                'provider' => $provider,
                'error'    => $e->getMessage(),
            ]);

            return response()->json([
                'error' => ['code' => 'WEBHOOK_ERROR', 'message' => 'Webhook processing failed'],
            ], 500);
        }

        return response()->json(['status' => 'ok']);
    }
}
