<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\Compliance\Kyc\Models\BridgeCustomer;
use App\Domain\Compliance\Kyc\Providers\BridgeKycProvider;
use App\Domain\Compliance\Kyc\Services\BridgePostKycHandler;
use App\Domain\Ramp\Providers\BridgeProvider;
use App\Domain\Subscription\Models\ProcessedWebhookEvent;
use App\Http\Controllers\Controller;
use App\Models\RampSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use Throwable;

/**
 * Dedicated webhook endpoint for Bridge.xyz events.
 *
 * Bridge fires both customer.kyc_link_* (KYC) and virtual_account.activity
 * / transfer.* (ramp) events to a single configured URL. We can't route
 * these through the existing /api/v1/ramp/webhook/{provider} seam because
 * KYC events have no ramp session_id. This controller dispatches by
 * event_type to either BridgeKycProvider (KYC) or RampService (ramp).
 *
 * Event-level dedupe uses the existing `processed_webhook_events` table
 * (per the grilling-session lock) keyed by (provider='bridge', event_id)
 * so Bridge retries are idempotent.
 *
 * Configure your Bridge dashboard to POST to:
 *   https://<your-domain>/api/v1/webhooks/bridge
 *
 * @see docs/BACKEND_HANDOVER_BRIDGE_RAMP.md §3.1, §3.3
 */
class BridgeWebhookController extends Controller
{
    public function __construct(
        private readonly BridgeProvider $rampProvider,
        private readonly BridgeKycProvider $kycProvider,
        private readonly BridgePostKycHandler $postKycHandler,
    ) {
    }

    #[OA\Post(
        path: '/api/v1/webhooks/bridge',
        operationId: 'v1BridgeWebhook',
        tags: ['Bridge Webhooks'],
        summary: 'Bridge.xyz event webhook (both KYC and ramp events)',
        description: 'Signature-verified via the X-Webhook-Signature header (RSA-SHA256 against Bridge\'s per-endpoint public key; legacy HMAC Bridge-Signature accepted as fallback). Dispatches by event_type to either KYC or ramp handlers.',
    )]
    #[OA\Response(response: 200, description: 'Event accepted (or ignored as no-op)')]
    #[OA\Response(response: 401, description: 'Invalid signature')]
    #[OA\Response(response: 400, description: 'Invalid payload')]
    public function handle(Request $request): JsonResponse
    {
        $rawBody = $request->getContent();
        // Bridge's current platform signs with X-Webhook-Signature (RSA, v0).
        // Fall back to the legacy Bridge-Signature header (HMAC, v1) so older
        // tenants / fixtures keep working; the verifier auto-detects the scheme.
        $signature = (string) $request->header('X-Webhook-Signature', '');
        if ($signature === '') {
            $signature = (string) $request->header('Bridge-Signature', '');
        }

        // Use the verifier through the ramp provider (same instance as
        // BridgeKycProvider since both pull BridgeWebhookVerifier via the
        // container).
        $validator = $this->rampProvider->getWebhookValidator();
        if (! $validator($rawBody, $signature)) {
            Log::warning('Bridge webhook: invalid signature');

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $payload = json_decode($rawBody, true);
        if (! is_array($payload)) {
            Log::error('Bridge webhook: invalid JSON body');

            return response()->json(['error' => 'Invalid payload'], 400);
        }

        $eventId = (string) ($payload['id'] ?? '');
        $eventType = (string) ($payload['type'] ?? '');

        if ($eventId === '' || $eventType === '') {
            Log::warning('Bridge webhook: missing id or type');

            return response()->json(['error' => 'Missing event id or type'], 400);
        }

        // Event-level idempotency: per the grilling session lock, reuse the
        // shared `processed_webhook_events` table.
        $newRow = ProcessedWebhookEvent::firstOrCreate(
            ['provider' => 'bridge', 'event_id' => $eventId],
            ['event_type' => $eventType, 'processed_at' => now()],
        );

        if (! $newRow->wasRecentlyCreated) {
            Log::info('Bridge webhook: duplicate event ignored', [
                'event_id'   => $eventId,
                'event_type' => $eventType,
            ]);

            return response()->json(['status' => 'duplicate'], 200);
        }

        try {
            if (str_starts_with($eventType, 'customer.kyc_link_')) {
                $this->dispatchKycEvent($payload);
            } else {
                $this->dispatchRampEvent($payload);
            }
        } catch (Throwable $e) {
            Log::error('Bridge webhook: handler threw', [
                'event_id'   => $eventId,
                'event_type' => $eventType,
                'exception'  => $e->getMessage(),
            ]);

            // Don't surface the exception to Bridge — they'll retry, and
            // the dedupe row prevents double-application. Operators see
            // the failure in logs.
        }

        return response()->json(['status' => 'received'], 200);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatchKycEvent(array $payload): void
    {
        $normalized = $this->kycProvider->normalizeWebhookPayload($payload);
        if ($normalized === null) {
            return;
        }

        $bridgeCustomerId = (string) ($normalized['raw']['customer_id'] ?? $normalized['raw']['id'] ?? '');
        if ($bridgeCustomerId === '') {
            Log::warning('Bridge KYC webhook: no customer_id in payload');

            return;
        }

        $customer = DB::transaction(function () use ($bridgeCustomerId, $normalized): ?BridgeCustomer {
            $customer = BridgeCustomer::where('bridge_customer_id', $bridgeCustomerId)
                ->lockForUpdate()
                ->first();

            if ($customer === null) {
                Log::warning('Bridge KYC webhook: bridge_customer not found', [
                    'bridge_customer_id' => $bridgeCustomerId,
                ]);

                return null;
            }

            $newStatus = match ($normalized['status']) {
                'approved' => BridgeCustomer::KYC_APPROVED,
                'rejected' => BridgeCustomer::KYC_REJECTED,
                'pending'  => BridgeCustomer::KYC_PENDING,
                default    => BridgeCustomer::KYC_NOT_STARTED,
            };

            $customer->update(['kyc_status' => $newStatus]);

            return $customer->fresh();
        });

        if ($customer === null) {
            return;
        }

        // Run post-KYC side effects (WS broadcast + push fallback +
        // virtual-account auto-provisioning on approval) outside the
        // transaction so a slow Bridge API call doesn't hold a row lock.
        match ($normalized['status']) {
            'approved' => $this->postKycHandler->handleApproved($customer),
            'rejected' => $this->postKycHandler->handleRejected(
                $customer,
                isset($normalized['raw']['rejection_reason'])
                    ? (string) $normalized['raw']['rejection_reason']
                    : null,
            ),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function dispatchRampEvent(array $payload): void
    {
        $normalized = $this->rampProvider->normalizeWebhookPayload($payload);
        if ($normalized === null) {
            return;
        }

        DB::transaction(function () use ($normalized, $payload): void {
            $session = RampSession::where('provider', BridgeProvider::PROVIDER_NAME)
                ->where('provider_session_id', $normalized['session_id'])
                ->lockForUpdate()
                ->first();

            if ($session === null) {
                // Unsolicited-deposit case (handover §6 lock): auto-create a
                // retroactive ramp_sessions row for virtual_account.activity
                // events that arrived without a preceding POST /ramp/session.
                if (str_starts_with((string) $normalized['session_id'], 'bridge_va_')) {
                    $this->createRetroactiveSession($normalized, $payload);
                } else {
                    Log::warning('Bridge ramp webhook: session not found', [
                        'session_id' => $normalized['session_id'],
                    ]);
                }

                return;
            }

            if (
                in_array(
                    $session->status,
                    [RampSession::STATUS_COMPLETED, RampSession::STATUS_FAILED, RampSession::STATUS_EXPIRED],
                    true,
                )
            ) {
                Log::info('Bridge ramp webhook: session already terminal', [
                    'session_id' => $session->id,
                    'status'     => $session->status,
                ]);

                return;
            }

            $session->update([
                'status'        => $normalized['status'],
                'crypto_amount' => $normalized['crypto_amount'] !== null
                    ? (float) $normalized['crypto_amount']
                    : $session->crypto_amount,
                'metadata' => array_merge($session->metadata ?? [], [
                    'webhook' => [
                        'received_at' => now()->toIso8601String(),
                        'event'       => $payload['type'] ?? null,
                    ],
                ]),
            ]);
        });
    }

    /**
     * @param  array{session_id: string, status: string, crypto_amount: string|null, raw: array<string, mixed>}  $normalized
     * @param  array<string, mixed>  $payload
     */
    private function createRetroactiveSession(array $normalized, array $payload): void
    {
        $virtualAccountId = (string) ($normalized['raw']['virtual_account_id']
            ?? $normalized['raw']['id'] ?? '');

        $customer = BridgeCustomer::where('virtual_account_id', $virtualAccountId)->first();
        if ($customer === null) {
            Log::warning('Bridge ramp webhook: no bridge_customer for unsolicited deposit', [
                'virtual_account_id' => $virtualAccountId,
            ]);

            return;
        }

        $object = $normalized['raw'];
        $cryptoAmount = isset($object['destination_amount']) && is_numeric($object['destination_amount'])
            ? (float) $object['destination_amount']
            : null;
        $fiatAmount = isset($object['source_amount']) && is_numeric($object['source_amount'])
            ? (float) $object['source_amount']
            : null;
        $fiatCurrency = strtoupper((string) ($object['source_currency'] ?? 'USD'));

        RampSession::create([
            'user_id'             => $customer->user_id,
            'provider'            => BridgeProvider::PROVIDER_NAME,
            'type'                => 'on',
            'fiat_currency'       => $fiatCurrency,
            'fiat_amount'         => $fiatAmount,
            'crypto_currency'     => 'USDC',
            'crypto_amount'       => $cryptoAmount,
            'status'              => $normalized['status'],
            'source'              => RampSession::SOURCE_BRIDGE_INITIATED,
            'provider_session_id' => $normalized['session_id'],
            'metadata'            => [
                'provider' => BridgeProvider::PROVIDER_NAME,
                'webhook'  => [
                    'received_at' => now()->toIso8601String(),
                    'event'       => $payload['type'] ?? null,
                ],
            ],
        ]);
    }
}
