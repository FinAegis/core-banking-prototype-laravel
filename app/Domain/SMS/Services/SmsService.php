<?php

declare(strict_types=1);

namespace App\Domain\SMS\Services;

use App\Domain\SMS\Clients\VertexSmsClient;
use App\Domain\SMS\Events\SmsDelivered;
use App\Domain\SMS\Events\SmsFailed;
use App\Domain\SMS\Events\SmsSent;
use App\Domain\SMS\Models\SmsMessage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

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
        $callerId = (string) (auth()->id() ?? 'system');
        $dedupKey = 'sms:dedup:' . hash('sha256', $callerId . '|' . $to . '|' . $from . '|' . $message);
        if (! Cache::add($dedupKey, true, 60)) {
            Log::warning('SMS: Duplicate send blocked', ['to' => $to, 'from' => $from]);
            throw new RuntimeException('Duplicate SMS detected within 60-second window');
        }

        $testMode = (bool) config('sms.defaults.test_mode', false);
        $priced = $this->pricing->priceFor($to, $from, $message);

        $result = $this->client->sendSms($to, $from, $message, $testMode);

        $sms = SmsMessage::create([
            'provider'        => (string) config('sms.default_provider', 'mock'),
            'provider_id'     => $result['message_id'],
            'to'              => $to,
            'from'            => $from,
            'message'         => $message,
            'parts'           => $priced['parts'],
            'status'          => SmsMessage::STATUS_SENT,
            'price_usdc'      => $priced['amount_usdc'],
            'country_code'    => $priced['country_code'],
            'mcc'             => $priced['mcc'],
            'mnc'             => $priced['mnc'],
            'payment_rail'    => $paymentMeta['rail'] ?? null,
            'payment_id'      => $paymentMeta['payment_id'] ?? null,
            'payment_receipt' => $paymentMeta['receipt_id'] ?? null,
            'test_mode'       => $testMode,
        ]);

        Log::info('SMS: Message recorded', [
            'id'           => $sms->id,
            'provider_id'  => $result['message_id'],
            'to'           => $to,
            'parts'        => $priced['parts'],
            'price_usdc'   => $priced['amount_usdc'],
            'price_source' => $priced['source'],
            'payment_rail' => $paymentMeta['rail'] ?? null,
        ]);

        SmsSent::dispatch(
            (string) $sms->id,
            $to,
            $priced['parts'],
            $priced['amount_usdc'],
            $paymentMeta,
        );

        return [
            'message_id'  => $result['message_id'],
            'status'      => 'sent',
            'parts'       => $priced['parts'],
            'destination' => $to,
            'price_usdc'  => $priced['amount_usdc'],
        ];
    }

    /**
     * Handle a delivery report from VertexSMS.
     *
     * Pessimistic locking prevents races when concurrent DLR webhooks for the
     * same message arrive in the same window.
     *
     * @param  array{message_id: string, raw_status: mixed, delivered_at?: string|null, error_code?: int|null, mcc?: string|null, mnc?: string|null}  $dlr
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

            $newStatus = $this->normalizeDlrStatus($dlr['raw_status']);
            $currentStatus = (string) $sms->status;

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

            // error_code handled separately: 0 is a valid value (= success)
            if (array_key_exists('error_code', $dlr) && $dlr['error_code'] !== null) {
                $updates['error_code'] = $dlr['error_code'];
            }

            foreach (['mcc', 'mnc'] as $optional) {
                if (! empty($dlr[$optional])) {
                    $updates[$optional] = $dlr[$optional];
                }
            }

            $sms->update($updates);

            // Alert on VertexSMS error code 24 = Account balance limit reached
            if (($dlr['error_code'] ?? null) === 24) {
                Log::critical('SMS: VertexSMS account balance exhausted — all further SMS will fail until topped up', [
                    'provider_id' => $dlr['message_id'],
                    'error_code'  => 24,
                    'sms_id'      => $sms->id,
                ]);
            }

            if ($newStatus === SmsMessage::STATUS_DELIVERED) {
                SmsDelivered::dispatch((string) $sms->id, $dlr['message_id']);
            } elseif ($newStatus === SmsMessage::STATUS_FAILED) {
                SmsFailed::dispatch((string) $sms->id, $dlr['message_id'], (string) $dlr['raw_status']);
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
     * Map either Vertex's numeric DLR code (1/2/3/16) or a legacy string into
     * the internal SmsMessage status enum. Single source of truth — the
     * controller passes raw values straight through.
     */
    private function normalizeDlrStatus(mixed $raw): string
    {
        if (is_numeric($raw)) {
            return match ((int) $raw) {
                1, 3    => SmsMessage::STATUS_DELIVERED,
                2, 16   => SmsMessage::STATUS_FAILED,
                default => SmsMessage::STATUS_SENT,
            };
        }

        if (is_string($raw)) {
            return match (strtolower($raw)) {
                'delivered', 'success' => SmsMessage::STATUS_DELIVERED,
                'failed', 'error', 'rejected',
                'expired', 'undeliverable', 'undelivered' => SmsMessage::STATUS_FAILED,
                default                                   => SmsMessage::STATUS_SENT,
            };
        }

        return SmsMessage::STATUS_SENT;
    }

    /**
     * pending → sent → delivered (terminal)
     * pending → sent → failed (terminal).
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
