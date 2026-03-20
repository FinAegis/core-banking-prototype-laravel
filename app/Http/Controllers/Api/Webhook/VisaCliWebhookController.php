<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Webhook;

use App\Domain\VisaCli\Enums\VisaCliPaymentStatus;
use App\Domain\VisaCli\Models\VisaCliPayment;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Handle inbound Visa CLI payment status webhooks.
 *
 * Verifies HMAC signature and updates payment records based on
 * status callbacks from the Visa CLI service.
 */
class VisaCliWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        if (! $this->verifySignature($request)) {
            Log::warning('Visa CLI webhook signature verification failed', [
                'ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Invalid signature'], 401);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->all();
        $eventType = (string) ($payload['event'] ?? '');

        Log::info('Visa CLI webhook received', [
            'event'     => $eventType,
            'reference' => $payload['payment_reference'] ?? null,
        ]);

        return match ($eventType) {
            'payment.completed' => $this->handlePaymentCompleted($payload),
            'payment.failed'    => $this->handlePaymentFailed($payload),
            'payment.refunded'  => $this->handlePaymentRefunded($payload),
            default             => response()->json(['status' => 'ignored', 'event' => $eventType]),
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function handlePaymentCompleted(array $payload): JsonResponse
    {
        $reference = (string) ($payload['payment_reference'] ?? '');
        $payment = VisaCliPayment::where('payment_reference', $reference)->first();

        if ($payment === null) {
            return response()->json(['status' => 'not_found'], 404);
        }

        $payment->update(['status' => VisaCliPaymentStatus::COMPLETED]);

        return response()->json(['status' => 'updated']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function handlePaymentFailed(array $payload): JsonResponse
    {
        $reference = (string) ($payload['payment_reference'] ?? '');
        $reason = (string) ($payload['reason'] ?? 'Unknown');

        $payment = VisaCliPayment::where('payment_reference', $reference)->first();

        if ($payment === null) {
            return response()->json(['status' => 'not_found'], 404);
        }

        $payment->markFailed($reason);

        return response()->json(['status' => 'updated']);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function handlePaymentRefunded(array $payload): JsonResponse
    {
        $reference = (string) ($payload['payment_reference'] ?? '');
        $payment = VisaCliPayment::where('payment_reference', $reference)->first();

        if ($payment === null) {
            return response()->json(['status' => 'not_found'], 404);
        }

        $payment->update([
            'status'   => VisaCliPaymentStatus::REFUNDED,
            'metadata' => array_merge($payment->metadata ?? [], [
                'refunded_at' => now()->toIso8601String(),
            ]),
        ]);

        return response()->json(['status' => 'updated']);
    }

    private function verifySignature(Request $request): bool
    {
        $secret = config('visacli.webhook.secret');

        // Skip verification if no secret configured (demo mode)
        if (empty($secret)) {
            return true;
        }

        $signature = $request->header('X-VisaCli-Signature', '');
        $payload = $request->getContent();

        $expected = hash_hmac('sha256', $payload, (string) $secret);

        return hash_equals($expected, (string) $signature);
    }
}
