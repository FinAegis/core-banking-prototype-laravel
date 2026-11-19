<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Subscription\Models\ProcessedWebhookEvent;
use App\Http\Controllers\Controller;
use App\Jobs\ProcessOndatoWebhook;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use Throwable;

#[OA\Tag(
    name: 'Ondato Webhooks',
    description: 'Endpoints for receiving webhook notifications from Ondato KYC'
)]
class OndatoWebhookController extends Controller
{
    /**
     * Handle identity verification webhook from Ondato.
     */
    #[OA\Post(
        path: '/api/webhooks/ondato/identity-verification',
        operationId: 'ondatoIdentityVerificationWebhook',
        tags: ['Ondato Webhooks'],
        summary: 'Receive Ondato identity verification webhook',
        description: 'Endpoint for receiving identity verification webhook notifications from Ondato',
        requestBody: new OA\RequestBody(required: true, description: 'Webhook payload from Ondato', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'id', type: 'string', example: '3fa85f64-5717-4562-b3fc-2c963f66afa6'),
        new OA\Property(property: 'applicationId', type: 'string', example: 'app-123'),
        new OA\Property(property: 'identityVerificationId', type: 'string', example: 'idv-456'),
        new OA\Property(property: 'status', type: 'string', example: 'PROCESSED'),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'Webhook received (status "duplicate" when the same signed body was already received)',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['received', 'duplicate'], example: 'received'),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid payload'
    )]
    #[OA\Response(
        response: 401,
        description: 'Invalid signature'
    )]
    public function identityVerification(Request $request): JsonResponse
    {
        return $this->handleWebhook($request, 'identity-verification');
    }

    /**
     * Handle identification webhook from Ondato.
     */
    #[OA\Post(
        path: '/api/webhooks/ondato/identification',
        operationId: 'ondatoIdentificationWebhook',
        tags: ['Ondato Webhooks'],
        summary: 'Receive Ondato identification webhook',
        description: 'Endpoint for receiving identification result webhook notifications from Ondato',
        requestBody: new OA\RequestBody(required: true, description: 'Webhook payload from Ondato', content: new OA\JsonContent(properties: [
        new OA\Property(property: 'id', type: 'string', example: '3fa85f64-5717-4562-b3fc-2c963f66afa6'),
        new OA\Property(property: 'identityVerificationId', type: 'string', example: 'idv-456'),
        new OA\Property(property: 'status', type: 'string', example: 'PROCESSED'),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'Webhook received (status "duplicate" when the same signed body was already received)',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'status', type: 'string', enum: ['received', 'duplicate'], example: 'received'),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid payload'
    )]
    #[OA\Response(
        response: 401,
        description: 'Invalid signature'
    )]
    public function identification(Request $request): JsonResponse
    {
        return $this->handleWebhook($request, 'identification');
    }

    /**
     * Common webhook handler — validates, returns 200 immediately, dispatches job.
     */
    private function handleWebhook(Request $request, string $webhookType): JsonResponse
    {
        $rawPayload = $request->getContent();

        // Parse the payload
        $data = json_decode($rawPayload, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            Log::error('Ondato webhook: invalid JSON payload', [
                'type'  => $webhookType,
                'error' => json_last_error_msg(),
            ]);

            return response()->json(['error' => 'Invalid payload'], 400);
        }

        // Validate signature
        $signature = $request->header('X-Ondato-Signature', '');
        $ondatoService = app(\App\Domain\Compliance\Services\OndatoService::class);

        if (! $ondatoService->validateWebhookSignature($rawPayload, (string) $signature)) {
            Log::warning('Ondato webhook: invalid signature', [
                'type' => $webhookType,
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        // Extract event type from payload
        $eventType = $data['status'] ?? $data['type'] ?? 'UNKNOWN';

        Log::info('Ondato webhook received', [
            'type'       => $webhookType,
            'event_type' => $eventType,
            'payload_id' => $data['id'] ?? null,
        ]);

        // Each signed delivery is processed once. The HMAC covers the body
        // only, so a replayed body (for example an old PROCESSED after a
        // later REJECTED) is dropped here. Keyed on the body hash, because
        // the payload `id` can be the verification id shared by every
        // status change.
        $eventId = $webhookType . ':' . hash('sha256', $rawPayload);
        $claim = ProcessedWebhookEvent::query()->createOrFirst(
            ['provider' => 'ondato', 'event_id' => $eventId],
            ['event_type' => mb_substr(is_scalar($eventType) ? (string) $eventType : 'UNKNOWN', 0, 128), 'processed_at' => now()],
        );

        if (! $claim->wasRecentlyCreated) {
            Log::info('Ondato webhook: duplicate delivery ignored', [
                'type'       => $webhookType,
                'event_type' => $eventType,
            ]);

            return response()->json(['status' => 'duplicate'], 200);
        }

        // Dispatch async job for processing
        try {
            ProcessOndatoWebhook::dispatch($eventType, $data, $webhookType);
        } catch (Throwable $e) {
            // Not queued: give the claim back so Ondato's retry is processed.
            $claim->delete();

            throw $e;
        }

        return response()->json(['status' => 'received'], 200);
    }
}
