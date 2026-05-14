<?php

/**
 * GooglePlayWebhookController — POST /webhooks/google/play.
 *
 * Receives Google Play Real-Time Developer Notifications via Pub/Sub push.
 * The push body shape:
 *   {"message":{"data":"<base64-encoded RTDN JSON>","messageId":"..."},"subscription":"..."}
 *
 * Pub/Sub authenticates the push via a bearer JWT in the Authorization
 * header issued by `accounts.google.com`. We verify the `aud` claim against
 * GOOGLE_PLAY_WEBHOOK_AUDIENCE.
 *
 * Dedup key: Pub/Sub `messageId`. After dedup we re-fetch the canonical
 * subscription state via `purchases.subscriptionsv2.get` (the RTDN body
 * contains only the purchaseToken + notificationType, not the full state).
 *
 * IMPORTANT — always return 200 (§5.5 callout). Google Play Store retries
 * non-2xx indefinitely.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-2-iap-design.md §5.5
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Webhooks;

use App\Domain\Subscription\Iap\GooglePlayReceiptVerifier;
use App\Domain\Subscription\Iap\GoogleVerifiedSubscription;
use App\Domain\Subscription\Iap\IapReceiptPseudonymiser;
use App\Domain\Subscription\Iap\IapSubscriptionService;
use App\Domain\Subscription\Iap\IapVerificationException;
use App\Domain\Subscription\Models\IapReceipt;
use App\Domain\Subscription\Models\IapSubscription;
use App\Domain\Subscription\Models\IapSubscriptionEvent;
use App\Domain\Subscription\Models\ProcessedWebhookEvent;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class GooglePlayWebhookController
{
    public const PROVIDER = 'google';

    private const GOOGLE_CERTS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    public function __construct(
        private readonly GooglePlayReceiptVerifier $verifier,
        private readonly IapSubscriptionService $service,
        private readonly IapReceiptPseudonymiser $pseudonymiser,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        // Pub/Sub push JWT verification — gated bypass in local/testing per
        // CLAUDE.md webhook-auth-bypass pattern.
        if (! $this->verifyPubSubJwt($request)) {
            Log::warning('iap.google.webhook.invalid_jwt');

            return response()->json(['received' => true, 'reason' => 'invalid_jwt']);
        }

        $envelope = $request->json()->all();
        if (! is_array($envelope)) {
            return response()->json(['received' => true, 'reason' => 'invalid_body']);
        }

        /** @var array<string, mixed> $envelope */
        $message = (array) ($envelope['message'] ?? []);
        $messageId = (string) ($message['messageId'] ?? '');
        $dataB64 = (string) ($message['data'] ?? '');

        if ($messageId === '' || $dataB64 === '') {
            return response()->json(['received' => true, 'reason' => 'missing_fields']);
        }

        $decoded = base64_decode($dataB64, true);
        if ($decoded === false) {
            return response()->json(['received' => true, 'reason' => 'invalid_b64']);
        }

        try {
            /** @var array<string, mixed> $rtdn */
            $rtdn = json_decode($decoded, true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable $e) {
            Log::warning('iap.google.webhook.payload_invalid', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['received' => true, 'reason' => 'invalid_json']);
        }

        try {
            DB::transaction(function () use ($rtdn, $messageId): void {
                $existing = ProcessedWebhookEvent::query()
                    ->where('provider', self::PROVIDER)
                    ->where('event_id', $messageId)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return;
                }

                ProcessedWebhookEvent::query()->create([
                    'provider'     => self::PROVIDER,
                    'event_id'     => $messageId,
                    'event_type'   => $this->extractNotificationName($rtdn),
                    'processed_at' => now(),
                ]);

                $this->dispatch($rtdn, $messageId);
            });
        } catch (Throwable $e) {
            // §5.5 / acceptance §9: always 200.
            Log::error('iap.google.webhook.handler_error', [
                'messageId' => $messageId,
                'error'     => $e->getMessage(),
            ]);
        }

        return response()->json([
            'received'  => true,
            'messageId' => $messageId,
        ]);
    }

    /**
     * @param array<string, mixed> $rtdn
     */
    private function dispatch(array $rtdn, string $messageId): void
    {
        $notification = (array) ($rtdn['subscriptionNotification'] ?? []);
        $purchaseToken = (string) ($notification['purchaseToken'] ?? '');
        $notificationType = (int) ($notification['notificationType'] ?? 0);

        if ($purchaseToken === '' || $notificationType === 0) {
            Log::info('iap.google.webhook.not_subscription_notification', [
                'messageId' => $messageId,
            ]);

            return;
        }

        // Subscription product id is delivered in the notification on most
        // newer RTDN payloads under `subscriptionId`.
        $productId = (string) ($notification['subscriptionId'] ?? '');

        // Re-fetch authoritative state via Play Developer API.
        try {
            $verified = $this->verifier->verify($purchaseToken, $productId);
        } catch (IapVerificationException $e) {
            // Post-erasure path: hash-fallback receipt lookup.
            $this->handlePostErasureOrAck($purchaseToken, $notificationType, $messageId, $e);

            return;
        }

        $purchaseTokenHash = hash_hmac(
            'sha256',
            $verified->purchaseToken,
            (string) config('subscription.iap.receipt_pepper', ''),
        );

        /** @var IapSubscription|null $sub */
        $sub = IapSubscription::query()
            ->where('play_subscription_resource_id', $verified->subscriptionResourceId)
            ->lockForUpdate()
            ->first();

        if ($sub === null) {
            // SUBSCRIPTION_PURCHASED arrives before mobile's /iap/verify in
            // some racy paths. Log; mobile's verify call creates the row.
            Log::info('iap.google.webhook.no_subscription_row', [
                'subscriptionResourceId' => $verified->subscriptionResourceId,
                'notificationType'       => $notificationType,
            ]);

            return;
        }

        $this->applyStateTransition($sub, $verified, $notificationType, $messageId, $purchaseTokenHash);
    }

    private function applyStateTransition(
        IapSubscription $sub,
        GoogleVerifiedSubscription $verified,
        int $notificationType,
        string $messageId,
        string $purchaseTokenHash,
    ): void {
        $previousStatus = $sub->status;

        match ($notificationType) {
            1 => $sub->fill([ // SUBSCRIPTION_RECOVERED
                'status'                 => IapSubscription::STATUS_ACTIVE,
                'current_period_ends_at' => $verified->expiryTime,
                'last_notification_type' => 'SUBSCRIPTION_RECOVERED',
            ]),
            2 => $sub->fill([ // SUBSCRIPTION_RENEWED
                'status'                 => IapSubscription::STATUS_ACTIVE,
                'current_period_ends_at' => $verified->expiryTime,
                'last_notification_type' => 'SUBSCRIPTION_RENEWED',
            ]),
            3 => $sub->fill([ // SUBSCRIPTION_CANCELED
                'cancel_at_period_end'   => true,
                'last_notification_type' => 'SUBSCRIPTION_CANCELED',
            ]),
            4 => $sub->fill([ // SUBSCRIPTION_PURCHASED
                'status'                        => IapSubscription::STATUS_ACTIVE,
                'current_period_starts_at'      => $verified->startTime,
                'current_period_ends_at'        => $verified->expiryTime,
                'google_purchase_token_hash'    => $purchaseTokenHash,
                'play_subscription_resource_id' => $verified->subscriptionResourceId,
                'last_notification_type'        => 'SUBSCRIPTION_PURCHASED',
            ]),
            5 => $sub->fill([ // SUBSCRIPTION_ON_HOLD
                'status'                 => IapSubscription::STATUS_PAST_DUE,
                'last_notification_type' => 'SUBSCRIPTION_ON_HOLD',
            ]),
            6 => $sub->fill([ // SUBSCRIPTION_IN_GRACE_PERIOD — keep active
                'status'                 => IapSubscription::STATUS_GRACE_PERIOD,
                'last_notification_type' => 'SUBSCRIPTION_IN_GRACE_PERIOD',
            ]),
            7 => $sub->fill([ // SUBSCRIPTION_RESTARTED
                'cancel_at_period_end'   => false,
                'status'                 => IapSubscription::STATUS_ACTIVE,
                'last_notification_type' => 'SUBSCRIPTION_RESTARTED',
            ]),
            8 => $sub->fill([ // SUBSCRIPTION_PRICE_CHANGE_CONFIRMED — log only
                'last_notification_type' => 'SUBSCRIPTION_PRICE_CHANGE_CONFIRMED',
            ]),
            9 => $sub->fill([ // SUBSCRIPTION_DEFERRED
                'current_period_ends_at' => $verified->expiryTime,
                'last_notification_type' => 'SUBSCRIPTION_DEFERRED',
            ]),
            10 => $sub->fill([ // SUBSCRIPTION_PAUSED
                'status'                 => IapSubscription::STATUS_PAUSED,
                'paused_at'              => now(),
                'paused_until'           => $verified->expiryTime,
                'last_notification_type' => 'SUBSCRIPTION_PAUSED',
            ]),
            11 => $sub->fill([ // SUBSCRIPTION_PAUSE_SCHEDULE_CHANGED
                'paused_until'           => $verified->expiryTime,
                'last_notification_type' => 'SUBSCRIPTION_PAUSE_SCHEDULE_CHANGED',
            ]),
            12 => $sub->fill([ // SUBSCRIPTION_REVOKED
                'status'                 => IapSubscription::STATUS_EXPIRED,
                'expired_at'             => now(),
                'last_notification_type' => 'SUBSCRIPTION_REVOKED',
            ]),
            13 => $sub->fill([ // SUBSCRIPTION_EXPIRED
                'status'                 => IapSubscription::STATUS_EXPIRED,
                'expired_at'             => now(),
                'last_notification_type' => 'SUBSCRIPTION_EXPIRED',
            ]),
            default => null,
        };

        $sub->last_event_id = $messageId;
        $sub->save();

        $this->appendEvent($sub, 'GoogleSubscription_' . $notificationType, [
            'notificationType' => $notificationType,
            'state'            => $verified->state,
            'previousStatus'   => $previousStatus,
            'productId'        => $verified->productId,
        ]);

        // Outbox writes for renewal / refund / expired (matching §5.10 table).
        $eventKind = match ($notificationType) {
            1, 2    => 'iap_subscription_renewal',
            4       => 'iap_subscription_initial',
            12      => 'iap_refund',
            13      => 'iap_subscription_expired',
            default => null,
        };

        if ($eventKind === null) {
            return;
        }

        $payload = [
            'userId'       => $sub->user_id,
            'aggregateId'  => $sub->id,
            'amount'       => $verified->amountSmallestUnit,
            'decimals'     => $verified->amountDecimals,
            'denomination' => $verified->amountCurrency,
            'productId'    => $verified->productId,
            'emittedAt'    => now()->toIso8601String(),
            'rawType'      => 'google_play_rtdn_' . $notificationType,
        ];

        if ($eventKind === 'iap_refund') {
            $payload['amount'] = -1 * (int) $payload['amount'];
        }

        $this->service->enqueueRevenueOutbox(
            store: IapSubscription::STORE_GOOGLE,
            notificationId: 'google:' . $messageId,
            eventKind: $eventKind,
            payload: $payload,
        );
    }

    private function handlePostErasureOrAck(
        string $purchaseToken,
        int $notificationType,
        string $messageId,
        IapVerificationException $verifyError,
    ): void {
        // The hash here is on the raw purchaseToken because we don't have a
        // stable resource id post-erasure.
        $hash = $this->pseudonymiser->fingerprint($purchaseToken);

        $receipt = IapReceipt::query()
            ->where('store', IapSubscription::STORE_GOOGLE)
            ->where('original_transaction_id_hash', $hash)
            ->orderByDesc('id')
            ->first();

        if ($receipt === null) {
            Log::info('iap.google.webhook.verify_failed_no_scrubbed_match', [
                'messageId'        => $messageId,
                'notificationType' => $notificationType,
                'reason'           => $verifyError->getMessage(),
            ]);

            return;
        }

        // RENEWAL post-erasure: increment counter + log.
        if ($notificationType === 2) {
            $receipt->scrubbed_renewal_count = (int) $receipt->scrubbed_renewal_count + 1;
            $receipt->save();

            $level = $receipt->scrubbed_renewal_count >= 3 ? 'error' : 'warning';
            Log::log($level, 'iap.webhook.stale_renewal_post_erasure', [
                'store'                  => 'google',
                'receipt_id'             => $receipt->id,
                'scrubbed_renewal_count' => $receipt->scrubbed_renewal_count,
            ]);

            return;
        }

        // REFUND / REVOKED post-erasure: closed_account_refunds (best-effort).
        if (in_array($notificationType, [12], true)) {
            $this->recordClosedAccountRefund('google_play', $receipt, $messageId);
        }
    }

    /**
     * Verify the Pub/Sub push JWT.
     */
    private function verifyPubSubJwt(Request $request): bool
    {
        $audience = (string) config('subscription.iap.google.webhook_audience', '');

        // Local/testing bypass — gated on env + empty config (CLAUDE.md
        // pattern), never `return true` unconditionally.
        if (app()->environment('local', 'testing') && $audience === '') {
            return true;
        }

        $auth = (string) $request->header('Authorization', '');
        if (! str_starts_with($auth, 'Bearer ')) {
            return false;
        }
        $jwt = substr($auth, 7);

        try {
            // Cache the JWKS for 1 hour — Google rotates keys rarely. Without
            // this, every webhook delivery fetches Google's certs URL; if the
            // call ever fails (network blip, Google rate-limit, DNS), the
            // webhook silently returns 200 and the notification is dropped
            // because Google won't retry on a 2xx.
            /** @var array<string, mixed>|null $jwks */
            $jwks = Cache::remember(
                'iap.google.webhook.jwks',
                3600,
                function (): ?array {
                    $response = Http::timeout(5)->get(self::GOOGLE_CERTS_URL)->json();

                    return is_array($response) ? $response : null;
                },
            );
            if ($jwks === null) {
                return false;
            }

            /** @var array<string, \Firebase\JWT\Key> $keys */
            $keys = JWK::parseKeySet($jwks);
            $decoded = JWT::decode($jwt, $keys);
        } catch (ConnectionException $e) {
            Log::warning('iap.google.webhook.jwks_fetch_failed', [
                'error' => $e->getMessage(),
            ]);

            return false;
        } catch (Throwable $e) {
            Log::warning('iap.google.webhook.jwt_invalid', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        $aud = property_exists($decoded, 'aud') ? (string) $decoded->aud : '';
        if ($aud !== $audience) {
            Log::warning('iap.google.webhook.jwt_aud_mismatch', [
                'expected' => $audience,
                'got'      => $aud,
            ]);

            return false;
        }

        return true;
    }

    /**
     * @param array<string, mixed> $rtdn
     */
    private function extractNotificationName(array $rtdn): string
    {
        $sub = (array) ($rtdn['subscriptionNotification'] ?? []);
        $type = (int) ($sub['notificationType'] ?? 0);

        return $type === 0 ? 'unknown' : 'google_rtdn_' . $type;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function appendEvent(IapSubscription $aggregate, string $eventClass, array $payload): void
    {
        /** @var int $max */
        $max = (int) IapSubscriptionEvent::query()
            ->where('aggregate_uuid', $aggregate->id)
            ->max('aggregate_version');

        IapSubscriptionEvent::query()->create([
            'id'                => (string) Str::uuid(),
            'aggregate_uuid'    => $aggregate->id,
            'aggregate_version' => $max + 1,
            'event_class'       => $eventClass,
            'event_payload'     => $payload,
            'metadata'          => [
                'store'      => $aggregate->store,
                'recordedAt' => now()->toIso8601String(),
            ],
            'created_at' => Carbon::now()->format('Y-m-d H:i:s.u'),
        ]);
    }

    private function recordClosedAccountRefund(
        string $source,
        IapReceipt $receipt,
        string $notificationUuid,
    ): void {
        try {
            $hasTable = DB::getSchemaBuilder()->hasTable('closed_account_refunds');
            if (! $hasTable) {
                Log::info('iap.webhook.closed_account_refund.table_missing', [
                    'source'     => $source,
                    'receipt_id' => $receipt->id,
                ]);

                return;
            }

            DB::table('closed_account_refunds')->insert([
                'source'               => $source,
                'iap_receipt_id'       => $receipt->id,
                'original_tx_hash'     => $receipt->original_transaction_id_hash,
                'notification_uuid'    => $notificationUuid,
                'amount_smallest_unit' => $receipt->amount_smallest_unit,
                'amount_decimals'      => $receipt->amount_decimals,
                'amount_currency'      => $receipt->amount_currency,
                'created_at'           => now(),
                'updated_at'           => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('iap.webhook.closed_account_refund.write_failed', [
                'source' => $source,
                'error'  => $e->getMessage(),
            ]);
        }
    }
}
