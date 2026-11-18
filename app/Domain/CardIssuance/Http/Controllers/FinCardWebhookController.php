<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Http\Controllers;

use App\Domain\CardIssuance\Services\FinCardAccountService;
use App\Domain\CardIssuance\Services\FinCardCardholderService;
use App\Domain\CardIssuance\Services\FinCardCardService;
use App\Domain\Subscription\Models\ProcessedWebhookEvent;
use App\Http\Controllers\Controller;
use App\Infrastructure\FinCard\FinCardWebhookVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use Throwable;

/**
 * Dedicated webhook endpoint for FinCard (FinHub) platform events.
 *
 * FinCard fires card-lifecycle, authorization, 3DS, cardholder-KYC, work-order
 * and crypto-wallet events to a single configured URL. Authentication is by
 * RSA signature (SHA256withRSA over the raw body) — not Sanctum — verified via
 * {@see FinCardWebhookVerifier}. The platform retries on any non-success reply,
 * so processing is idempotent (dedupe on `processed_webhook_events`, keyed by
 * the event's order/trade number) and a handled event acknowledges with
 * `{"success": true}` — the exact body FinCard expects to stop retrying.
 *
 * Configure the FinCard dashboard to POST to:
 *   https://<your-domain>/api/v1/webhooks/fincard
 *
 * Phase 1 stands up verification, dedupe and acknowledgement. Per-category
 * side effects (KYC state, account credit, card state, transaction sync) are
 * wired in later phases at the marked extension point — precise routing depends
 * on confirming the production event envelope against the sandbox.
 *
 * @see docs/superpowers/specs/2026-07-06-fincard-card-issuing-design.md §9
 */
class FinCardWebhookController extends Controller
{
    /** FinCard cardholder-event types (KYC approval workflow). */
    private const CARDHOLDER_EVENTS = ['wait_audit', 'under_review', 'pass_audit', 'reject'];

    /** Wallet v2 (crypto) funding event types (uppercase — distinct from card-op). */
    private const WALLET_EVENTS = ['DEPOSIT', 'WITHDRAW'];

    /** Card-operation event types (lowercase deposit/withdraw affect the card, not the account). */
    private const CARD_OP_EVENTS = ['create', 'deposit', 'withdraw', 'Freeze', 'UnFreeze', 'cancel', 'blocked', 'overdraft_statement'];

    public function __construct(
        private readonly FinCardWebhookVerifier $verifier,
        private readonly FinCardCardholderService $cardholderService,
        private readonly FinCardAccountService $accountService,
        private readonly FinCardCardService $cardService,
    ) {
    }

    #[OA\Post(
        path: '/api/v1/webhooks/fincard',
        operationId: 'v1FinCardWebhook',
        tags: ['FinCard Webhooks'],
        summary: 'FinCard (FinHub) platform event webhook',
        description: 'RSA-signature-verified (SHA256withRSA over the raw body, base64) against FinCard\'s platform public key. Accepts the X-FC-SIGNATURE or X-WSB-SIGNATURE header. Idempotent via processed_webhook_events.',
    )]
    #[OA\Response(response: 200, description: 'Event accepted (or ignored as duplicate)')]
    #[OA\Response(response: 401, description: 'Invalid signature')]
    #[OA\Response(response: 400, description: 'Invalid payload')]
    public function handle(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();

        $signature = FinCardWebhookVerifier::signatureFrom(
            static fn (string $name): ?string => $request->header($name),
        );

        if (! $this->verifier->verify($rawBody, $signature)) {
            Log::warning('FinCard webhook: invalid signature');

            return response()->json(['success' => false, 'error' => 'Invalid signature'], 401);
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            Log::error('FinCard webhook: invalid JSON body');

            return response()->json(['success' => false, 'error' => 'Invalid payload'], 400);
        }

        $eventId = $this->extractEventId($payload);
        $eventType = (string) ($payload['eventType'] ?? $payload['type'] ?? '');

        if ($eventId === '' || $eventType === '') {
            Log::warning('FinCard webhook: missing event id or type', ['type' => $eventType]);

            return response()->json(['success' => false, 'error' => 'Missing event id or type'], 400);
        }

        // Event-level idempotency via the shared table, keyed on FinCard's
        // order/trade number so platform retries are safe.
        $row = ProcessedWebhookEvent::firstOrCreate(
            ['provider' => 'fincard', 'event_id' => $eventId],
            ['event_type' => $eventType, 'processed_at' => now()],
        );

        if (! $row->wasRecentlyCreated) {
            Log::info('FinCard webhook: duplicate event ignored', [
                'event_id'   => $eventId,
                'event_type' => $eventType,
            ]);

            return response()->json(['success' => true], 200);
        }

        try {
            $this->dispatchEvent($eventType, $payload);
        } catch (Throwable $e) {
            // Don't surface to FinCard — they retry, and the dedupe row prevents
            // double-application. Operators see the failure in logs.
            Log::error('FinCard webhook: handler threw', [
                'event_id'   => $eventId,
                'event_type' => $eventType,
                'exception'  => $e->getMessage(),
            ]);
        }

        return response()->json(['success' => true], 200);
    }

    /**
     * Route a verified event to its per-category handler. Cardholder KYC events
     * (Phase 2) update local KYC state; funding/card/transaction categories are
     * wired in later phases.
     *
     * @param  array<string, mixed>  $payload
     */
    private function dispatchEvent(string $eventType, array $payload): void
    {
        if (in_array($eventType, self::CARDHOLDER_EVENTS, true)) {
            $this->cardholderService->applyKycWebhook($eventType, $payload);

            return;
        }

        if (in_array($eventType, self::WALLET_EVENTS, true)) {
            $this->accountService->applyFundingWebhook($eventType, $payload);

            return;
        }

        if (in_array($eventType, self::CARD_OP_EVENTS, true)) {
            $this->cardService->applyCardWebhook($eventType, $payload);

            return;
        }

        Log::info('FinCard webhook received (no handler yet)', [
            'event_type' => $eventType,
            // Never log `data` — it may carry PAN / PII.
        ]);
    }

    /**
     * FinCard's idempotency identifiers are the order/trade numbers; fall back
     * to an explicit event id if present.
     *
     * @param  array<string, mixed>  $payload
     */
    private function extractEventId(array $payload): string
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];

        foreach (['orderNo', 'tradeNo', 'eventId', 'id'] as $key) {
            $candidate = (string) ($payload[$key] ?? $data[$key] ?? '');
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }
}
