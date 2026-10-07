<?php

declare(strict_types=1);

namespace App\Domain\Custodian\Services;

use App\Domain\Custodian\Connectors\MockBankConnector;
use Illuminate\Support\Facades\Log;

class WebhookVerificationService
{
    /**
     * Verify webhook signature for different custodians.
     */
    public function verifySignature(
        string $custodianName,
        string $payload,
        string $signature,
        array $headers = []
    ): bool {
        return match ($custodianName) {
            'paysera'   => $this->verifyPayseraSignature($payload, $signature, $headers),
            'santander' => $this->verifySantanderSignature($payload, $signature, $headers),
            'mock'      => MockBankConnector::isPermitted(), // only where the mock connector is permitted
            default     => false,
        };
    }

    /**
     * Verify Paysera webhook signature.
     *
     * Paysera uses HMAC-SHA256 with a shared secret.
     */
    private function verifyPayseraSignature(string $payload, string $signature, array $headers): bool
    {
        $secret = config('custodians.connectors.paysera.webhook_secret');

        if (! $secret) {
            Log::warning('Paysera webhook secret not configured');

            return false;
        }

        // Paysera sends the signature in the X-Paysera-Signature header
        $expectedSignature = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Verify Santander webhook signature.
     *
     * Santander uses a different signature scheme.
     */
    private function verifySantanderSignature(string $payload, string $signature, array $headers): bool
    {
        $secret = config('custodians.connectors.santander.webhook_secret');

        if (! $secret) {
            Log::warning('Santander webhook secret not configured');

            return false;
        }

        // Santander includes timestamp in signature calculation
        $timestamp = $headers['x-santander-timestamp'] ?? $headers['X-Santander-Timestamp'] ?? '';
        $dataToSign = $timestamp . '.' . $payload;

        $expectedSignature = hash_hmac('sha512', $dataToSign, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Check if webhook timestamp is within acceptable range.
     */
    public function isTimestampValid(int $timestamp, int $toleranceSeconds = 300): bool
    {
        $currentTime = time();
        $difference = abs($currentTime - $timestamp);

        return $difference <= $toleranceSeconds;
    }
}
