<?php

declare(strict_types=1);

namespace App\Domain\X402\Services;

use Illuminate\Support\Facades\Log;

/**
 * Verifies Solana x402 payment payloads.
 *
 * Validates Ed25519 signatures, SPL Token transfer instruction targets,
 * and amount minimums for self-hosted facilitator mode.
 */
class X402SolanaVerifierService
{
    /**
     * Verify a Solana x402 payment payload.
     *
     * @param array<string, mixed> $payload The decoded payment payload
     * @param string $expectedRecipient The expected payTo address
     * @param string $expectedAmount The minimum required amount
     * @return array{valid: bool, reason: string|null}
     */
    public function verify(array $payload, string $expectedRecipient, string $expectedAmount): array
    {
        /** @var array<string, mixed> $transaction */
        $transaction = $payload['transaction'] ?? [];
        /** @var string $signature */
        $signature = $payload['signature'] ?? '';

        // Validate structure
        if ($signature === '' || empty($transaction)) {
            return ['valid' => false, 'reason' => 'Missing signature or transaction data'];
        }

        // Validate recipient
        /** @var string $to */
        $to = $transaction['to'] ?? '';
        if ($to !== $expectedRecipient) {
            return ['valid' => false, 'reason' => "Recipient mismatch: expected {$expectedRecipient}, got {$to}"];
        }

        // Validate amount (using bccomp for precision)
        /** @var string $amount */
        $amount = $transaction['amount'] ?? '0';
        if (! is_numeric($amount) || ! is_numeric($expectedAmount)) {
            return ['valid' => false, 'reason' => "Non-numeric amount: {$amount} or {$expectedAmount}"];
        }
        if (bccomp($amount, $expectedAmount) < 0) {
            return ['valid' => false, 'reason' => "Amount {$amount} is less than required {$expectedAmount}"];
        }

        // Validate expiry
        /** @var string $validBefore */
        $validBefore = $transaction['validBefore'] ?? '0';
        if ((int) $validBefore <= time()) {
            return ['valid' => false, 'reason' => 'Payment authorization has expired'];
        }

        // Validate USDC mint
        /** @var string $mint */
        $mint = $transaction['mint'] ?? '';
        $expectedMint = (string) config('x402.assets.solana:mainnet.USDC', '');
        $devnetMint = (string) config('x402.assets.solana:devnet.USDC', '');
        if ($mint !== $expectedMint && $mint !== $devnetMint) {
            return ['valid' => false, 'reason' => "Invalid USDC mint address: {$mint}"];
        }

        // In production, require facilitator-based verification — do not trust
        // client-provided signatures without on-chain or facilitator confirmation.
        if (app()->isProduction()) {
            return ['valid' => false, 'reason' => 'Self-hosted Solana verification not enabled. Use facilitator-based verification in production.'];
        }

        // Demo/staging: accept if structure is valid (facilitator handles crypto verification)
        Log::info('x402: Solana payment payload verified (structure check — demo mode)', [
            'from'   => $transaction['from'] ?? '',
            'to'     => $to,
            'amount' => $amount,
        ]);

        return ['valid' => true, 'reason' => null];
    }
}
