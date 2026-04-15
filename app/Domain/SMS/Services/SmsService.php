<?php

declare(strict_types=1);

namespace App\Domain\SMS\Services;

use App\Domain\SMS\Clients\VertexSmsClient;
use App\Domain\SMS\Events\SmsDelivered;
use App\Domain\SMS\Events\SmsFailed;
use App\Domain\SMS\Events\SmsSent;
use App\Domain\SMS\Models\SmsMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Core SMS business logic. Sends messages via VertexSMS,
 * records them in the database, and links to MPP payments.
 */
class SmsService
{
    public function __construct(
        private readonly VertexSmsClient $client,
        private readonly SmsPricingService $pricing,
    ) {
    }

    /**
     * Send an SMS and record it.
     *
     * @param  array{rail?: string, payment_id?: string, receipt_id?: string}  $paymentMeta
     * @return array{message_id: string, status: string, parts: int, destination: string, price_usdc: string}
     */
    public function send(
        string $to,
        string $from,
        string $message,
        array $paymentMeta = [],
    ): array {
        $testMode = (bool) config('sms.defaults.test_mode', false);

        // Step 1: ask Vertex for the authoritative cost + parts + mccmnc.
        // Falls back to local estimation if /sms/cost is unreachable so we
        // never block a send because pricing is temporarily degraded.
        $cost = $this->tryEstimateCost($to, $from, $message);

        if ($cost !== null) {
            $price = $this->pricing->getPriceFromCostEstimate($cost);
            $parts = $cost['parts'];
            $mccmnc = $cost['mccmnc'];
        } else {
            $price = $this->pricing->getPriceForNumber($to);
            $parts = 1;
            $mccmnc = null;
        }

        // Step 2: send via provider. Returns just the message_id — parts come
        // from the cost estimate (the old X-VertexSMS-Amount-Sent header was
        // undocumented and brittle).
        $result = $this->client->sendSms($to, $from, $message, $testMode);

        [$mcc, $mnc] = $this->splitMccMnc($mccmnc);

        // Step 3: persist. Wrap in a transaction so the SmsMessage insert and
        // the subsequent event dispatch observe the same committed row.
        $sms = DB::transaction(fn () => SmsMessage::create([
            'provider'        => (string) config('sms.default_provider', 'mock'),
            'provider_id'     => $result['message_id'],
            'to'              => $to,
            'from'            => $from,
            'message'         => $message,
            'parts'           => $parts,
            'status'          => SmsMessage::STATUS_SENT,
            'price_usdc'      => $price['amount_usdc'],
            'country_code'    => $price['country_code'],
            'mcc'             => $mcc,
            'mnc'             => $mnc,
            'payment_rail'    => $paymentMeta['rail'] ?? null,
            'payment_id'      => $paymentMeta['payment_id'] ?? null,
            'payment_receipt' => $paymentMeta['receipt_id'] ?? null,
            'test_mode'       => $testMode,
        ]));

        Log::info('SMS: Message recorded', [
            'id'           => $sms->id,
            'provider_id'  => $result['message_id'],
            'to'           => $to,
            'parts'        => $parts,
            'price_usdc'   => $price['amount_usdc'],
            'payment_rail' => $paymentMeta['rail'] ?? null,
            'payment_id'   => $paymentMeta['payment_id'] ?? null,
        ]);

        SmsSent::dispatch(
            (string) $sms->id,
            $to,
            $parts,
            $price['amount_usdc'],
            $paymentMeta,
        );

        return [
            'message_id'  => $result['message_id'],
            'status'      => 'sent',
            'parts'       => $parts,
            'destination' => $to,
            'price_usdc'  => $price['amount_usdc'],
        ];
    }

    /**
     * Handle a delivery report from VertexSMS.
     *
     * Uses pessimistic locking to prevent race conditions from
     * concurrent DLR webhooks for the same message.
     *
     * @param  array{message_id: string, status: string, delivered_at?: string|null, error_code?: int|null, mcc?: string|null, mnc?: string|null}  $dlr
     */
    public function handleDeliveryReport(array $dlr): void
    {
        DB::transaction(function () use ($dlr): void {
            $sms = SmsMessage::where('provider_id', $dlr['message_id'])
                ->lockForUpdate()
                ->first();

            if ($sms === null) {
                Log::warning('SMS: DLR for unknown message', ['provider_id' => $dlr['message_id']]);

                return;
            }

            $newStatus = $this->normalizeDlrStatus($dlr['status'] ?? '');
            $currentStatus = (string) $sms->status;

            // Only allow forward state transitions
            if (! $this->isValidTransition($currentStatus, $newStatus)) {
                Log::debug('SMS: DLR skipped (invalid transition)', [
                    'provider_id' => $dlr['message_id'],
                    'current'     => $currentStatus,
                    'new'         => $newStatus,
                ]);

                return;
            }

            $updates = [
                'status'       => $newStatus,
                'delivered_at' => $dlr['delivered_at'] ?? ($newStatus === SmsMessage::STATUS_DELIVERED ? now() : null),
            ];

            if (array_key_exists('error_code', $dlr) && $dlr['error_code'] !== null) {
                $updates['error_code'] = $dlr['error_code'];
            }
            if (array_key_exists('mcc', $dlr) && $dlr['mcc'] !== null && $dlr['mcc'] !== '') {
                $updates['mcc'] = $dlr['mcc'];
            }
            if (array_key_exists('mnc', $dlr) && $dlr['mnc'] !== null && $dlr['mnc'] !== '') {
                $updates['mnc'] = $dlr['mnc'];
            }

            $sms->update($updates);

            if ($newStatus === SmsMessage::STATUS_DELIVERED) {
                SmsDelivered::dispatch((string) $sms->id, $dlr['message_id']);
            } elseif ($newStatus === SmsMessage::STATUS_FAILED) {
                SmsFailed::dispatch((string) $sms->id, $dlr['message_id'], $dlr['status'] ?? 'unknown');
            }

            Log::info('SMS: DLR processed', [
                'id'          => $sms->id,
                'provider_id' => $dlr['message_id'],
                'status'      => $newStatus,
                'error_code'  => $dlr['error_code'] ?? null,
            ]);
        });
    }

    /**
     * Get message status by provider ID.
     *
     * @return array{message_id: string, status: string, delivered_at: string|null, payment_status: string|null}|null
     */
    public function getStatus(string $providerMessageId): ?array
    {
        $sms = SmsMessage::where('provider_id', $providerMessageId)->first();

        if ($sms === null) {
            return null;
        }

        return [
            'message_id'     => (string) $sms->provider_id,
            'status'         => (string) $sms->status,
            'delivered_at'   => $sms->delivered_at?->toIso8601String(),
            'payment_status' => $sms->payment_receipt !== null ? 'settled' : 'pending',
        ];
    }

    /**
     * Get supported info (for public endpoint).
     *
     * @return array{provider: string, enabled: bool, test_mode: bool, networks: array<string>}
     */
    public function getSupportedInfo(): array
    {
        return [
            'provider'  => (string) config('sms.default_provider', 'mock'),
            'enabled'   => (bool) config('sms.enabled', false),
            'test_mode' => (bool) config('sms.defaults.test_mode', false),
            'networks'  => ['eip155:8453', 'eip155:1'],
        ];
    }

    /**
     * @return array{parts: int, price_per_part_eur: float, total_price_eur: float, country_iso: string, mccmnc: ?string}|null
     */
    private function tryEstimateCost(string $to, string $from, string $message): ?array
    {
        try {
            return $this->client->estimateCost($to, $from, $message);
        } catch (Throwable $e) {
            Log::warning('SMS: cost estimate failed, falling back to local pricing', [
                'to'    => $to,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Split a concatenated MCCMNC like "24601" into ["246", "01"].
     *
     * @return array{0: string|null, 1: string|null}
     */
    private function splitMccMnc(?string $mccmnc): array
    {
        if ($mccmnc === null || $mccmnc === '' || ! ctype_digit($mccmnc) || strlen($mccmnc) < 4) {
            return [null, null];
        }

        return [
            substr($mccmnc, 0, 3),
            substr($mccmnc, 3),
        ];
    }

    private function normalizeDlrStatus(string $status): string
    {
        return match (strtolower($status)) {
            'delivered', 'success' => SmsMessage::STATUS_DELIVERED,
            'failed', 'error', 'rejected',
            'expired', 'undeliverable', 'undelivered' => SmsMessage::STATUS_FAILED,
            'sent', 'accepted', 'enroute'             => SmsMessage::STATUS_SENT,
            default                                   => SmsMessage::STATUS_SENT,
        };
    }

    /**
     * Check if a DLR status transition is valid (forward-only).
     *
     * pending → sent → delivered (terminal)
     * pending → sent → failed (terminal)
     */
    private function isValidTransition(string $current, string $new): bool
    {
        $order = [
            SmsMessage::STATUS_PENDING   => 0,
            SmsMessage::STATUS_SENT      => 1,
            SmsMessage::STATUS_DELIVERED => 2,
            SmsMessage::STATUS_FAILED    => 2,
        ];

        return ($order[$new] ?? 0) >= ($order[$current] ?? 0);
    }
}
