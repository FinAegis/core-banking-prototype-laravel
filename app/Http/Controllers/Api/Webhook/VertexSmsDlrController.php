<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Webhook;

use App\Domain\SMS\Clients\VertexSmsClient;
use App\Domain\SMS\Services\SmsService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VertexSmsDlrController extends Controller
{
    public function __construct(
        private readonly SmsService $smsService,
        private readonly VertexSmsClient $client,
    ) {
    }

    /**
     * Handle VertexSMS delivery report webhook.
     */
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
