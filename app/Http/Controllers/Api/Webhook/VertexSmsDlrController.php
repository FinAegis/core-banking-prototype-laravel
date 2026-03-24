<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Webhook;

use App\Domain\SMS\Clients\VertexSmsClient;
use App\Domain\SMS\Services\SmsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Tag(name: 'SMS Webhooks')]
class VertexSmsDlrController extends Controller
{
    public function __construct(
        private readonly SmsService $smsService,
        private readonly VertexSmsClient $client,
    ) {
    }

    #[OA\Post(
        path: '/api/v1/webhooks/vertexsms/dlr',
        operationId: 'vertexSmsDlr',
        summary: 'VertexSMS delivery report webhook',
        description: 'Receives SMS delivery status updates from VertexSMS. Verifies HMAC-SHA256 signature from X-VertexSMS-Signature header.',
        tags: ['SMS Webhooks'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['message_id', 'status'],
            properties: [
                new OA\Property(property: 'message_id', type: 'string'),
                new OA\Property(property: 'status', type: 'string', enum: ['sent', 'delivered', 'failed']),
                new OA\Property(property: 'delivered_at', type: 'string', nullable: true),
            ],
        ),
    )]
    #[OA\Response(response: 200, description: 'Delivery report accepted')]
    #[OA\Response(response: 401, description: 'Invalid webhook signature')]
    #[OA\Response(response: 422, description: 'Missing message_id')]
    public function handle(Request $request): JsonResponse
    {
        // Verify signature
        $signature = $request->header('X-VertexSMS-Signature', '');

        if (! is_string($signature)) {
            $signature = '';
        }

        if (! $this->client->verifyWebhookSignature($request->getContent(), $signature)) {
            Log::warning('VertexSMS DLR: Invalid webhook signature', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $messageId = (string) $request->input('message_id', '');
        $status = (string) $request->input('status', '');
        $deliveredAt = $request->input('delivered_at');

        if ($messageId === '') {
            return response()->json(['error' => 'Missing message_id'], 422);
        }

        Log::info('VertexSMS DLR: Received', [
            'message_id' => $messageId,
            'status'     => $status,
        ]);

        $this->smsService->handleDeliveryReport([
            'message_id'   => $messageId,
            'status'       => $status,
            'delivered_at' => is_string($deliveredAt) ? $deliveredAt : null,
        ]);

        return response()->json(['received' => true]);
    }
}
