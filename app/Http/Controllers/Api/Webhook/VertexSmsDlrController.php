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
        description: 'Receives SMS delivery status updates from VertexSMS. Accepts either a URL-token (`?t=...`) or an HMAC-SHA256 signature (`X-VertexSMS-Signature` header) for authentication.',
        tags: ['SMS Webhooks'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['id', 'status'],
            properties: [
                new OA\Property(property: 'id', type: 'string', description: 'VertexSMS message ID (as returned from POST /sms)'),
                new OA\Property(property: 'status', type: 'integer', enum: [1, 2, 3, 16], description: '1=delivered, 2=undelivered, 3=Viber seen, 16=expired'),
                new OA\Property(property: 'error', type: 'integer', description: '0 when delivered, otherwise Vertex error code'),
                new OA\Property(property: 'mcc', type: 'string', nullable: true, description: 'Mobile Country Code'),
                new OA\Property(property: 'mnc', type: 'string', nullable: true, description: 'Mobile Network Code'),
            ],
        ),
    )]
    #[OA\Response(response: 200, description: 'Delivery report accepted')]
    #[OA\Response(response: 401, description: 'Invalid webhook signature or URL token')]
    #[OA\Response(response: 422, description: 'Missing or invalid message id')]
    public function handle(Request $request): JsonResponse
    {
        if (! $this->authenticate($request)) {
            Log::warning('VertexSMS DLR: Authentication failed', [
                'ip'            => $request->ip(),
                'has_token'     => $request->query('t') !== null,
                'has_signature' => $request->headers->has('X-VertexSMS-Signature'),
            ]);

            return response()->json(['error' => 'Unauthenticated webhook'], 401);
        }

        $messageId = (string) ($request->input('id') ?? $request->input('message_id', ''));

        if ($messageId === '') {
            return response()->json(['error' => 'Missing id'], 422);
        }

        $rawStatus = $request->input('status');
        $errorCode = $request->input('error');
        $mcc = $request->input('mcc');
        $mnc = $request->input('mnc');

        $deliveredAt = $request->input('delivered_at');

        Log::info('VertexSMS DLR: Received', [
            'message_id' => $messageId,
            'raw_status' => $rawStatus,
            'error'      => $errorCode,
        ]);

        $this->smsService->handleDeliveryReport([
            'message_id'   => $messageId,
            'raw_status'   => $rawStatus,
            'delivered_at' => is_string($deliveredAt) && $deliveredAt !== '' ? $deliveredAt : null,
            'error_code'   => is_numeric($errorCode) ? (int) $errorCode : null,
            'mcc'          => is_string($mcc) && $mcc !== '' ? $mcc : null,
            'mnc'          => is_string($mnc) && $mnc !== '' ? $mnc : null,
        ]);

        return response()->json(['received' => true]);
    }

    /**
     * Accept either a valid URL token (`?t=<token>`) OR a valid HMAC
     * `X-VertexSMS-Signature` header.
     */
    private function authenticate(Request $request): bool
    {
        $providedToken = (string) ($request->query('t') ?? '');
        $tokenResult = $this->client->verifyDlrUrlToken($providedToken);

        if ($tokenResult === true) {
            return true;
        }

        // Token configured but mismatched — do NOT fall through to HMAC in
        // production; that would defeat the point of having the token.
        if ($tokenResult === false && app()->environment('production')) {
            return false;
        }

        return $this->client->verifyWebhookSignature(
            $request->getContent(),
            (string) $request->header('X-VertexSMS-Signature', ''),
        );
    }
}
