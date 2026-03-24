<?php

declare(strict_types=1);

namespace App\Domain\MachinePay\Services\Rails;

use App\Domain\MachinePay\Contracts\PaymentRailInterface;
use App\Domain\MachinePay\DataObjects\MppCredential;
use App\Domain\MachinePay\DataObjects\MppReceipt;
use App\Domain\MachinePay\Enums\PaymentRail;
use App\Domain\X402\Contracts\FacilitatorClientInterface;
use App\Domain\X402\DataObjects\PaymentPayload;
use App\Domain\X402\DataObjects\PaymentRequirements;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * MPP rail adapter that settles payments via x402 (Coinbase facilitator).
 *
 * Bridges MPP credential format to x402 verify + settle flow,
 * enabling USDC-on-chain payments as an MPP rail option.
 */
class X402RailAdapter implements PaymentRailInterface
{
    public function __construct(
        private readonly FacilitatorClientInterface $facilitator,
    ) {
    }

    public function processPayment(MppCredential $credential, array $context = []): MppReceipt
    {
        $x402Payload = $this->extractX402Payload($credential);
        $x402Requirements = $this->extractX402Requirements($credential, $context);

        // Verify via facilitator
        $verifyResult = $this->facilitator->verify($x402Payload, $x402Requirements);

        if (! $verifyResult->isValid) {
            Log::warning('x402 Rail: Verification failed', [
                'reason' => $verifyResult->invalidReason,
            ]);

            return new MppReceipt(
                receiptId: 'rcpt_x402_' . Str::random(16),
                challengeId: $credential->challengeId,
                rail: PaymentRail::X402_USDC->value,
                settlementReference: '',
                settledAt: gmdate('c'),
                amountCents: (int) ($context['amount_cents'] ?? 0),
                currency: (string) ($context['currency'] ?? 'USDC'),
                status: 'error',
            );
        }

        // Settle on-chain
        $settleResult = $this->facilitator->settle($x402Payload, $x402Requirements);

        Log::info('x402 Rail: Payment settled', [
            'challenge_id' => $credential->challengeId,
            'tx_hash'      => $settleResult->transactionHash ?? 'none',
            'success'      => $settleResult->success,
        ]);

        return new MppReceipt(
            receiptId: 'rcpt_x402_' . Str::random(16),
            challengeId: $credential->challengeId,
            rail: PaymentRail::X402_USDC->value,
            settlementReference: $settleResult->transactionHash ?? ('x402_' . Str::random(16)),
            settledAt: gmdate('c'),
            amountCents: (int) ($context['amount_cents'] ?? 0),
            currency: (string) ($context['currency'] ?? 'USDC'),
            status: $settleResult->success ? 'success' : 'error',
        );
    }

    public function verifyPayment(MppCredential $credential): bool
    {
        if (! isset($credential->proofOfPayment['x402_payload'], $credential->proofOfPayment['x402_requirements'])) {
            return false;
        }

        $x402Payload = $this->extractX402Payload($credential);
        $x402Requirements = $this->extractX402Requirements($credential, []);

        $result = $this->facilitator->verify($x402Payload, $x402Requirements);

        return $result->isValid;
    }

    public function refund(string $settlementReference, int $amountCents): bool
    {
        // x402 on-chain USDC transfers are final — no refund mechanism
        Log::info('x402 Rail: Refund not supported for on-chain USDC', [
            'settlement_ref' => $settlementReference,
        ]);

        return false;
    }

    public function getRailIdentifier(): PaymentRail
    {
        return PaymentRail::X402_USDC;
    }

    public function isAvailable(): bool
    {
        return (bool) config('x402.enabled', false);
    }

    /**
     * Extract x402 PaymentPayload from MPP credential.
     */
    private function extractX402Payload(MppCredential $credential): PaymentPayload
    {
        /** @var array<string, mixed> $payloadData */
        $payloadData = $credential->proofOfPayment['x402_payload'] ?? [];

        if ($payloadData === []) {
            throw new RuntimeException('MPP credential missing x402_payload in proof_of_payment');
        }

        return PaymentPayload::fromArray($payloadData);
    }

    /**
     * Extract x402 PaymentRequirements from MPP credential or context.
     *
     * @param array<string, mixed> $context
     */
    private function extractX402Requirements(MppCredential $credential, array $context): PaymentRequirements
    {
        /** @var array<string, mixed> $reqData */
        $reqData = $credential->proofOfPayment['x402_requirements']
            ?? $context['x402_requirements']
            ?? [];

        if ($reqData === []) {
            throw new RuntimeException('MPP credential missing x402_requirements');
        }

        return PaymentRequirements::fromArray($reqData);
    }
}
