<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Http\Controllers;

use App\Domain\CardIssuance\Jobs\ProcessLepoWebhook;
use App\Domain\CardIssuance\Providers\Lepo\LepoThreeDsCodes;
use App\Domain\CardIssuance\Webhooks\LepoWebhookRouter;
use App\Domain\Subscription\Models\ProcessedWebhookEvent;
use App\Http\Controllers\Controller;
use App\Infrastructure\Lepo\LepoWebhookVerifier;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use Throwable;

/**
 * Lepo Finance platform webhooks.
 *
 * Lepo retries a delivery up to 15 times over about 76 hours and may deliver
 * duplicates, so every event is deduplicated on processed_webhook_events
 * (provider='lepo', event_id) and processed once in a queued job. The answer
 * is sent as soon as the event is stored, keeping deliveries fast.
 */
final class LepoWebhookController extends Controller
{
    #[OA\Post(
        path: '/api/v1/webhooks/lepo',
        operationId: 'v1LepoWebhook',
        tags: ['Lepo Webhooks'],
        summary: 'Lepo Finance platform event webhook',
        description: 'Verified with the X-LepoFinance-Signature header: lowercase hex HMAC-SHA256 of the raw body keyed by the webhook secret. Idempotent via processed_webhook_events; processed asynchronously.',
    )]
    #[OA\Response(response: 200, description: 'Event accepted (or ignored as duplicate)')]
    #[OA\Response(response: 401, description: 'Invalid signature')]
    #[OA\Response(response: 400, description: 'Invalid payload')]
    public function handle(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();

        // Lepo treats 4xx as a permanent failure and stops retrying, so every
        // rejection is logged (without the payload): a rotated secret that was
        // not updated here would otherwise drop events silently.
        if (! LepoWebhookVerifier::fromConfig()->verify($rawBody, (string) $request->header('X-LepoFinance-Signature', ''))) {
            Log::warning('Lepo webhook rejected: invalid signature', $this->deliveryContext($request));

            return response()->json(['success' => false, 'error' => 'Invalid signature'], 401);
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            Log::warning('Lepo webhook rejected: body is not JSON', $this->deliveryContext($request));

            return response()->json(['success' => false, 'error' => 'Invalid payload'], 400);
        }

        $eventId = $payload['id'] ?? null;
        $type = $payload['type'] ?? null;
        if (! is_string($eventId) || $eventId === '' || ! is_string($type) || $type === '') {
            Log::warning('Lepo webhook rejected: missing event id or type', $this->deliveryContext($request));

            return response()->json(['success' => false, 'error' => 'Missing event id or type'], 400);
        }

        $row = ProcessedWebhookEvent::firstOrCreate(
            ['provider' => 'lepo', 'event_id' => $eventId],
            ['event_type' => $type, 'processed_at' => now()],
        );

        if ($row->wasRecentlyCreated) {
            $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

            try {
                // A 3-D Secure code is kept (encrypted, until it expires) before
                // the payload is queued without it.
                if (LepoWebhookRouter::carriesSecureCode($type)) {
                    app(LepoThreeDsCodes::class)->store($data);
                }

                app(Dispatcher::class)->dispatch(new ProcessLepoWebhook($eventId, $type, LepoWebhookRouter::redact($type, $data)));
            } catch (Throwable $e) {
                // Without the job the event would be acknowledged but never
                // processed; forget it so Lepo's retry gets another chance.
                $row->delete();

                throw $e;
            }
        }

        return response()->json(['success' => true], 200);
    }

    /**
     * @return array<string, string>
     */
    private function deliveryContext(Request $request): array
    {
        return [
            'webhook_id' => (string) $request->header('X-LepoFinance-Webhook-Id', ''),
            'attempt'    => (string) $request->header('X-LepoFinance-Delivery-Attempt', ''),
        ];
    }
}
