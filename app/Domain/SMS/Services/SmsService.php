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

        // Get pricing before sending (for record)
        $price = $this->pricing->getPriceForNumber($to);

        // Send via provider
        $result = $this->client->sendSms($to, $from, $message, $testMode);

        // Update price if multi-part
        if ($result['parts'] > 1) {
            $price = $this->pricing->getPriceForNumber($to, $result['parts']);
        }

        // Record in database — wrap in transaction for consistency
        $sms = DB::transaction(fn () => SmsMessage::create([
            'provider'        => (string) config('sms.default_provider', 'mock'),
            'provider_id'     => $result['message_id'],
            'to'              => $to,
            'from'            => $from,
            'message'         => $message,
            'parts'           => $result['parts'],
            'status'          => SmsMessage::STATUS_SENT,
            'price_usdc'      => $price['amount_usdc'],
            'country_code'    => $price['country_code'],
            'payment_rail'    => $paymentMeta['rail'] ?? null,
            'payment_id'      => $paymentMeta['payment_id'] ?? null,
            'payment_receipt' => $paymentMeta['receipt_id'] ?? null,
            'test_mode'       => $testMode,
        ]));

        Log::info('SMS: Message recorded', [
            'id'           => $sms->id,
            'provider_id'  => $result['message_id'],
            'to'           => $to,
            'parts'        => $result['parts'],
            'price_usdc'   => $price['amount_usdc'],
            'payment_rail' => $paymentMeta['rail'] ?? null,
            'payment_id'   => $paymentMeta['payment_id'] ?? null,
        ]);

        SmsSent::dispatch(
            (string) $sms->id,
            $to,
            $result['parts'],
            $price['amount_usdc'],
            $paymentMeta,
        );

        return [
            'message_id'  => $result['message_id'],
            'status'      => 'sent',
            'parts'       => $result['parts'],
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
     * @param  array{message_id: string, status: string, delivered_at?: string|null}  $dlr
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

            $sms->update([
                'status'       => $newStatus,
                'delivered_at' => $dlr['delivered_at'] ?? ($newStatus === SmsMessage::STATUS_DELIVERED ? now() : null),
            ]);

            if ($newStatus === SmsMessage::STATUS_DELIVERED) {
                SmsDelivered::dispatch((string) $sms->id, $dlr['message_id']);
            } elseif ($newStatus === SmsMessage::STATUS_FAILED) {
                SmsFailed::dispatch((string) $sms->id, $dlr['message_id'], $dlr['status'] ?? 'unknown');
            }

            Log::info('SMS: DLR processed', [
                'id'          => $sms->id,
                'provider_id' => $dlr['message_id'],
                'status'      => $newStatus,
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

    private function normalizeDlrStatus(string $status): string
    {
        return match (strtolower($status)) {
            'delivered', 'success'        => SmsMessage::STATUS_DELIVERED,
            'failed', 'error', 'rejected' => SmsMessage::STATUS_FAILED,
            'expired', 'undeliverable'    => SmsMessage::STATUS_FAILED,
            'sent', 'accepted', 'enroute' => SmsMessage::STATUS_SENT,
            default                       => SmsMessage::STATUS_SENT,
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
