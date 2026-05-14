<?php

/**
 * AppleNotificationsWebhookController — POST /webhooks/apple/notifications.
 *
 * Receives App Store Server Notifications V2. Apple delivers the entire
 * envelope as a JWS-signed payload (the outer `signedPayload`); the inner
 * `data.signedTransactionInfo` is itself a JWS containing the transaction.
 *
 * Dedup key: `notificationUUID` from the decoded payload (NOT the inner
 * transaction id — Apple guarantees uniqueness only at the envelope level).
 *
 * IMPORTANT — return 200 even on processing errors. App Store retries any
 * non-2xx indefinitely, which can cause duplicate-processing storms; we log
 * the failure and acknowledge. Stripe's handler returns 500 on Throwable;
 * THIS handler does not. See spec §5.4 callout.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-2-iap-design.md §5.4
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Webhooks;

use App\Domain\Subscription\Iap\AppleReceiptVerifier;
use App\Domain\Subscription\Iap\IapReceiptPseudonymiser;
use App\Domain\Subscription\Iap\IapSubscriptionService;
use App\Domain\Subscription\Models\IapReceipt;
use App\Domain\Subscription\Models\IapSubscription;
use App\Domain\Subscription\Models\IapSubscriptionEvent;
use App\Domain\Subscription\Models\ProcessedWebhookEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

final class AppleNotificationsWebhookController
{
    public const PROVIDER = 'apple';

    public function __construct(
        private readonly AppleReceiptVerifier $verifier,
        private readonly IapSubscriptionService $service,
        private readonly IapReceiptPseudonymiser $pseudonymiser,
    ) {
    }

    public function handle(Request $request): JsonResponse
    {
        // IAP tables live on the global connection; no UsesTenantConnection
        // models are touched. DB::transaction() is safe here per CLAUDE.md
        // multi-connection rule.
        $payload = (string) $request->getContent();

        try {
            $decoded = $this->decodePayload($payload);
        } catch (Throwable $e) {
            Log::warning('iap.apple.webhook.payload_invalid', [
                'error' => $e->getMessage(),
            ]);

            // Still 200 — never block Apple's retry queue on parse failure.
            return response()->json(['received' => true, 'reason' => 'payload_invalid']);
        }

        $notificationUuid = (string) ($decoded['notificationUUID'] ?? '');
        $notificationType = (string) ($decoded['notificationType'] ?? '');

        if ($notificationUuid === '' || $notificationType === '') {
            Log::warning('iap.apple.webhook.missing_fields', [
                'notificationType' => $notificationType,
                'notificationUUID' => $notificationUuid,
            ]);

            return response()->json(['received' => true, 'reason' => 'missing_fields']);
        }

        try {
            DB::transaction(function () use ($decoded, $notificationUuid, $notificationType): void {
                $existing = ProcessedWebhookEvent::query()
                    ->where('provider', self::PROVIDER)
                    ->where('event_id', $notificationUuid)
                    ->lockForUpdate()
                    ->first();

                if ($existing !== null) {
                    return;
                }

                ProcessedWebhookEvent::query()->create([
                    'provider'     => self::PROVIDER,
                    'event_id'     => $notificationUuid,
                    'event_type'   => $notificationType,
                    'processed_at' => now(),
                ]);

                $this->dispatch($decoded, $notificationUuid);
            });
        } catch (Throwable $e) {
            // §5.4 / acceptance §9: ALWAYS 200 — log only.
            Log::error('iap.apple.webhook.handler_error', [
                'notificationUUID' => $notificationUuid,
                'notificationType' => $notificationType,
                'error'            => $e->getMessage(),
            ]);
        }

        return response()->json([
            'received'         => true,
            'notificationUUID' => $notificationUuid,
        ]);
    }

    /**
     * Decode the outer JWS envelope into an associative array.
     *
     * @return array<string, mixed>
     */
    private function decodePayload(string $rawBody): array
    {
        // Apple POSTs `{"signedPayload":"<jws>"}`.
        /** @var array<string, mixed>|null $envelope */
        $envelope = json_decode($rawBody, true);

        if (is_array($envelope) && isset($envelope['signedPayload']) && is_string($envelope['signedPayload'])) {
            $decoded = $this->verifier->decodeNotificationPayload($envelope['signedPayload']);

            // Hoist transactionInfo from the inner signed JWS so dispatch()
            // doesn't have to re-decode.
            $data = (array) ($decoded['data'] ?? []);
            if (isset($data['signedTransactionInfo']) && is_string($data['signedTransactionInfo'])) {
                $data['transactionInfo'] = $this->verifier->decodeSignedTransactionInfo(
                    (string) $data['signedTransactionInfo'],
                );
            }
            if (isset($data['signedRenewalInfo']) && is_string($data['signedRenewalInfo'])) {
                $data['renewalInfo'] = $this->verifier->decodeSignedTransactionInfo(
                    (string) $data['signedRenewalInfo'],
                );
            }
            $decoded['data'] = $data;

            return $decoded;
        }

        // Local/testing fallback: accept a plain JSON body (allows tests to
        // POST a structured payload directly without round-tripping a JWS).
        if (app()->environment('local', 'testing') && is_array($envelope)) {
            return $envelope;
        }

        return [];
    }

    /**
     * Dispatch decoded ASN V2 payload to its handler.
     *
     * @param array<string, mixed> $payload
     */
    private function dispatch(array $payload, string $notificationUuid): void
    {
        $type = (string) ($payload['notificationType'] ?? '');
        $subtype = (string) ($payload['subtype'] ?? '');

        match ($type) {
            'SUBSCRIBED'                => $this->onSubscribed($payload, $subtype, $notificationUuid),
            'DID_RENEW'                 => $this->onDidRenew($payload, $notificationUuid),
            'DID_CHANGE_RENEWAL_STATUS' => $this->onDidChangeRenewalStatus($payload, $subtype, $notificationUuid),
            'DID_FAIL_TO_RENEW'         => $this->onDidFailToRenew($payload, $notificationUuid),
            'GRACE_PERIOD_EXPIRED'      => $this->onGracePeriodExpired($payload, $notificationUuid),
            'EXPIRED'                   => $this->onExpired($payload, $notificationUuid),
            'REFUND'                    => $this->onRefund($payload, $notificationUuid),
            'REFUND_DECLINED'           => $this->onRefundDeclined($payload, $notificationUuid),
            'REVOKE'                    => $this->onRevoke($payload, $notificationUuid),
            'CONSUMPTION_REQUEST',
            'DID_CHANGE_RENEWAL_PREF',
            'PRICE_INCREASE' => Log::info('iap.apple.webhook.logged_only', [
                'type'             => $type,
                'subtype'          => $subtype,
                'notificationUUID' => $notificationUuid,
            ]),
            default => Log::info('iap.apple.webhook.unhandled', [
                'type'             => $type,
                'notificationUUID' => $notificationUuid,
            ]),
        };
    }

    /**
     * Find an IAP subscription row by Apple originalTransactionId. Falls back
     * to the post-erasure hash lookup; returns [subscription, isScrubbed]
     * where isScrubbed indicates the receipt was already pseudonymised.
     *
     * @return array{0: IapSubscription|null, 1: IapReceipt|null, 2: bool}
     */
    private function findSubscriptionByOriginalTxId(string $originalTransactionId): array
    {
        /** @var IapSubscription|null $sub */
        $sub = IapSubscription::query()
            ->where('store', IapSubscription::STORE_APPLE)
            ->where('original_transaction_id', $originalTransactionId)
            ->lockForUpdate()
            ->first();

        /** @var IapReceipt|null $receipt */
        $receipt = null;

        if ($sub !== null) {
            $receipt = IapReceipt::query()
                ->where('iap_subscription_id', $sub->id)
                ->orderByDesc('id')
                ->first();

            return [$sub, $receipt, false];
        }

        // Hash fallback for post-erasure rows.
        $hash = $this->pseudonymiser->fingerprint($originalTransactionId);
        $receipt = IapReceipt::query()
            ->where('store', IapSubscription::STORE_APPLE)
            ->where('original_transaction_id_hash', $hash)
            ->orderByDesc('id')
            ->first();

        if ($receipt !== null) {
            /** @var IapSubscription|null $sub2 */
            $sub2 = IapSubscription::query()
                ->where('id', $receipt->iap_subscription_id)
                ->lockForUpdate()
                ->first();

            return [$sub2, $receipt, true];
        }

        return [null, null, false];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function onSubscribed(array $payload, string $subtype, string $notificationUuid): void
    {
        $tx = $this->transactionInfo($payload);
        $originalTransactionId = (string) ($tx['originalTransactionId'] ?? '');

        if ($originalTransactionId === '') {
            return;
        }

        [$sub, $_receipt, $_scrubbed] = $this->findSubscriptionByOriginalTxId($originalTransactionId);

        // INITIAL_BUY: only act if we don't already have a row (mobile's
        // /iap/verify will normally beat the webhook). RESUBSCRIBE: clear
        // cancel_at_period_end.
        if ($subtype === 'RESUBSCRIBE' && $sub !== null) {
            $sub->cancel_at_period_end = false;
            $sub->status = IapSubscription::STATUS_ACTIVE;
            $sub->last_notification_type = 'SUBSCRIBED.RESUBSCRIBE';
            $sub->last_event_id = $notificationUuid;
            $sub->save();

            $this->appendEvent($sub, 'AppleSubscriptionResubscribed', $tx);
            $this->writeOutbox($sub, $notificationUuid, 'iap_subscription_reactivated', $tx);

            return;
        }

        Log::info('iap.apple.webhook.subscribed', [
            'subtype'               => $subtype,
            'originalTransactionId' => $originalTransactionId,
            'has_existing_row'      => $sub !== null,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function onDidRenew(array $payload, string $notificationUuid): void
    {
        $tx = $this->transactionInfo($payload);
        $originalTransactionId = (string) ($tx['originalTransactionId'] ?? '');
        if ($originalTransactionId === '') {
            return;
        }

        [$sub, $receipt, $scrubbed] = $this->findSubscriptionByOriginalTxId($originalTransactionId);

        if ($scrubbed && $receipt !== null) {
            $receipt->scrubbed_renewal_count = (int) $receipt->scrubbed_renewal_count + 1;
            $receipt->save();

            $level = $receipt->scrubbed_renewal_count >= 3 ? 'error' : 'warning';
            Log::log($level, 'iap.webhook.stale_renewal_post_erasure', [
                'store'                  => 'apple',
                'receipt_id'             => $receipt->id,
                'scrubbed_renewal_count' => $receipt->scrubbed_renewal_count,
            ]);

            return;
        }

        if ($sub === null) {
            Log::info('iap.apple.webhook.did_renew.no_subscription', [
                'originalTransactionId' => $originalTransactionId,
            ]);

            return;
        }

        $expiresAt = $this->parseAppleEpochMillis($tx['expiresDate'] ?? null);
        if ($expiresAt !== null) {
            $sub->current_period_ends_at = $expiresAt;
        }
        $sub->status = IapSubscription::STATUS_ACTIVE;
        $sub->last_notification_type = 'DID_RENEW';
        $sub->last_event_id = $notificationUuid;
        $sub->save();

        $this->appendEvent($sub, 'AppleSubscriptionRenewed', $tx);

        $this->writeOutbox($sub, $notificationUuid, 'iap_subscription_renewal', $tx);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function onDidChangeRenewalStatus(array $payload, string $subtype, string $notificationUuid): void
    {
        $tx = $this->transactionInfo($payload);
        $originalTransactionId = (string) ($tx['originalTransactionId'] ?? '');
        if ($originalTransactionId === '') {
            return;
        }

        [$sub] = $this->findSubscriptionByOriginalTxId($originalTransactionId);
        if ($sub === null) {
            return;
        }

        if ($subtype === 'AUTO_RENEW_DISABLED') {
            $sub->cancel_at_period_end = true;
        } elseif ($subtype === 'AUTO_RENEW_ENABLED') {
            $sub->cancel_at_period_end = false;
        }
        $sub->last_notification_type = 'DID_CHANGE_RENEWAL_STATUS.' . $subtype;
        $sub->last_event_id = $notificationUuid;
        $sub->save();

        $this->appendEvent($sub, 'AppleSubscriptionRenewalStatusChanged', [
            'subtype' => $subtype,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function onDidFailToRenew(array $payload, string $notificationUuid): void
    {
        $tx = $this->transactionInfo($payload);
        $originalTransactionId = (string) ($tx['originalTransactionId'] ?? '');
        if ($originalTransactionId === '') {
            return;
        }

        [$sub] = $this->findSubscriptionByOriginalTxId($originalTransactionId);
        if ($sub === null) {
            return;
        }

        $sub->status = IapSubscription::STATUS_PAST_DUE;
        $sub->last_notification_type = 'DID_FAIL_TO_RENEW';
        $sub->last_event_id = $notificationUuid;
        $sub->save();

        $this->appendEvent($sub, 'AppleSubscriptionDidFailToRenew', $tx);

        // Slice 4 owns the grace_period_started cue dispatch (see
        // SubscriptionWebhookController::onAppleDidFailToRenew which we
        // deliberately do NOT call from slice 2 — the stub there is for the
        // future cue dispatch wiring; cue insertion is slice 4 scope per §6).
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function onGracePeriodExpired(array $payload, string $notificationUuid): void
    {
        $this->markStatus($payload, IapSubscription::STATUS_EXPIRED, $notificationUuid, 'AppleGracePeriodExpired');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function onExpired(array $payload, string $notificationUuid): void
    {
        $this->markStatus($payload, IapSubscription::STATUS_EXPIRED, $notificationUuid, 'AppleSubscriptionExpired');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function onRefund(array $payload, string $notificationUuid): void
    {
        $tx = $this->transactionInfo($payload);
        $originalTransactionId = (string) ($tx['originalTransactionId'] ?? '');
        if ($originalTransactionId === '') {
            return;
        }

        [$sub, $receipt, $scrubbed] = $this->findSubscriptionByOriginalTxId($originalTransactionId);

        // Post-erasure REFUND: record in `closed_account_refunds` if the table
        // exists in this environment; otherwise log + return. Slice 2 does not
        // create the closed_account_refunds table (that's a separate domain
        // concern); log on absence.
        if ($scrubbed && $receipt !== null) {
            $this->recordClosedAccountRefund('apple_iap', $receipt, $notificationUuid, $tx);

            return;
        }

        if ($sub === null) {
            Log::info('iap.apple.webhook.refund.no_subscription', [
                'originalTransactionId' => $originalTransactionId,
            ]);

            return;
        }

        $sub->status = IapSubscription::STATUS_REFUNDED;
        $sub->refunded_at = now();
        $sub->last_notification_type = 'REFUND';
        $sub->last_event_id = $notificationUuid;
        $sub->save();

        $this->appendEvent($sub, 'AppleSubscriptionRefunded', $tx);

        // Negative-amount outbox row per ADR-0004 sign-prefix.
        $payload = $this->outboxPayloadFromAppleTx($sub, $tx);
        $payload['amount'] = -1 * (int) ($payload['amount'] ?? 0);
        $this->service->enqueueRevenueOutbox(
            store: IapSubscription::STORE_APPLE,
            notificationId: 'apple:' . $notificationUuid,
            eventKind: 'iap_refund',
            payload: $payload,
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function onRefundDeclined(array $payload, string $notificationUuid): void
    {
        $tx = $this->transactionInfo($payload);
        $originalTransactionId = (string) ($tx['originalTransactionId'] ?? '');
        if ($originalTransactionId === '') {
            return;
        }

        [$sub] = $this->findSubscriptionByOriginalTxId($originalTransactionId);
        if ($sub === null) {
            return;
        }

        // Restore from refunded → active per spec §5.4 table.
        if ($sub->status === IapSubscription::STATUS_REFUNDED) {
            $sub->status = IapSubscription::STATUS_ACTIVE;
            $sub->refunded_at = null;
            $sub->last_notification_type = 'REFUND_DECLINED';
            $sub->last_event_id = $notificationUuid;
            $sub->save();

            $this->appendEvent($sub, 'AppleRefundDeclined', $tx);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function onRevoke(array $payload, string $notificationUuid): void
    {
        // Family Sharing revoked. Per §6 (out of scope), cue dispatch
        // (`family_sharing_unsupported`) is slice 4 territory — slice 2 just
        // marks the row expired and logs.
        $this->markStatus($payload, IapSubscription::STATUS_EXPIRED, $notificationUuid, 'AppleSubscriptionRevoked');

        Log::info('iap.apple.webhook.revoke.cue_deferred_to_slice_4', [
            'notificationUUID' => $notificationUuid,
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function markStatus(array $payload, string $status, string $notificationUuid, string $eventClass): void
    {
        $tx = $this->transactionInfo($payload);
        $originalTransactionId = (string) ($tx['originalTransactionId'] ?? '');
        if ($originalTransactionId === '') {
            return;
        }

        [$sub] = $this->findSubscriptionByOriginalTxId($originalTransactionId);
        if ($sub === null) {
            return;
        }

        $sub->status = $status;
        if ($status === IapSubscription::STATUS_EXPIRED) {
            $sub->expired_at = now();
        }
        $sub->last_notification_type = (string) ($payload['notificationType'] ?? '');
        $sub->last_event_id = $notificationUuid;
        $sub->save();

        $this->appendEvent($sub, $eventClass, $tx);
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return array<string, mixed>
     */
    private function transactionInfo(array $payload): array
    {
        $data = (array) ($payload['data'] ?? []);

        return (array) ($data['transactionInfo'] ?? []);
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

    /**
     * @param array<string, mixed> $tx
     */
    private function writeOutbox(IapSubscription $sub, string $notificationUuid, string $eventKind, array $tx): void
    {
        $payload = $this->outboxPayloadFromAppleTx($sub, $tx);

        $this->service->enqueueRevenueOutbox(
            store: IapSubscription::STORE_APPLE,
            notificationId: 'apple:' . $notificationUuid,
            eventKind: $eventKind,
            payload: $payload,
        );
    }

    /**
     * @param array<string, mixed> $tx
     *
     * @return array<string, mixed>
     */
    private function outboxPayloadFromAppleTx(IapSubscription $sub, array $tx): array
    {
        $price = $tx['price'] ?? 0;
        $amount = is_numeric($price) ? (int) $price : 0;
        $currency = strtoupper((string) ($tx['currency'] ?? 'EUR'));

        return [
            'userId'       => $sub->user_id,
            'aggregateId'  => $sub->id,
            'amount'       => $amount,
            'decimals'     => 2,
            'denomination' => $currency,
            'productId'    => (string) ($tx['productId'] ?? ''),
            'emittedAt'    => now()->toIso8601String(),
            'rawType'      => 'apple_iap_notification',
        ];
    }

    private function parseAppleEpochMillis(mixed $value): ?Carbon
    {
        if (! is_int($value) && ! (is_string($value) && ctype_digit($value))) {
            return null;
        }

        return Carbon::createFromTimestampMs((int) $value);
    }

    /**
     * Best-effort write into `closed_account_refunds` (if the table exists in
     * this deployment). Slice 2 does not own that schema; we degrade
     * gracefully when it's missing.
     *
     * @param array<string, mixed> $tx
     */
    private function recordClosedAccountRefund(
        string $source,
        IapReceipt $receipt,
        string $notificationUuid,
        array $tx,
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
                'amount_smallest_unit' => is_numeric($tx['price'] ?? null) ? (int) $tx['price'] : 0,
                'amount_decimals'      => 2,
                'amount_currency'      => strtoupper((string) ($tx['currency'] ?? 'EUR')),
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
