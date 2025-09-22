<?php

declare(strict_types=1);

namespace App\Domain\AgentProtocol\DataObjects;

use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Represents an agent-to-agent payment request.
 */
class AgentPaymentRequest
{
    public readonly string $transactionId;

    public readonly Carbon $createdAt;

    public function __construct(
        public readonly string $fromAgentDid,
        public readonly string $toAgentDid,
        public readonly float $amount,
        public readonly string $currency,
        public readonly string $purpose,
        public readonly ?array $metadata = null,
        public readonly ?array $escrowConditions = null,
        public readonly ?array $splits = null,
        public readonly ?int $timeoutSeconds = 300,
        ?string $transactionId = null,
        ?Carbon $createdAt = null
    ) {
        $this->transactionId = $transactionId ?? Str::uuid()->toString();
        $this->createdAt = $createdAt ?? now();
    }

    /**
     * Check if this payment requires escrow.
     */
    public function requiresEscrow(): bool
    {
        return ! empty($this->escrowConditions);
    }

    /**
     * Check if this payment requires fees.
     */
    public function requiresFees(): bool
    {
        // Fees apply to all payments except internal transfers
        return ! str_starts_with($this->purpose, 'internal:');
    }

    /**
     * Check if this is a split payment.
     */
    public function hasSplits(): bool
    {
        return ! empty($this->splits) && count($this->splits) > 1;
    }

    /**
     * Create a split request for a specific recipient.
     */
    public function createSplitRequest(array $split): self
    {
        return new self(
            fromAgentDid: $this->fromAgentDid,
            toAgentDid: $split['agentDid'],
            amount: $split['amount'],
            currency: $this->currency,
            purpose: $this->purpose . ' (split)',
            metadata: array_merge($this->metadata ?? [], [
                'original_transaction_id' => $this->transactionId,
                'split_type'              => $split['type'] ?? 'fixed',
                'split_percentage'        => $split['percentage'] ?? null,
            ]),
            escrowConditions: $split['escrowConditions'] ?? null,
            splits: null, // Splits don't have further splits
            timeoutSeconds: $this->timeoutSeconds,
            transactionId: Str::uuid()->toString(),
            createdAt: now()
        );
    }

    /**
     * Validate the payment request.
     */
    public function validate(): array
    {
        $errors = [];

        // Validate DIDs
        if (! $this->isValidDid($this->fromAgentDid)) {
            $errors[] = 'Invalid sender DID format';
        }

        if (! $this->isValidDid($this->toAgentDid)) {
            $errors[] = 'Invalid recipient DID format';
        }

        // Validate amount
        if ($this->amount <= 0) {
            $errors[] = 'Amount must be greater than zero';
        }

        // Validate currency
        if (! in_array($this->currency, ['USD', 'EUR', 'GBP', 'BTC', 'ETH', 'GCU'])) {
            $errors[] = 'Unsupported currency: ' . $this->currency;
        }

        // Validate splits if present
        if ($this->hasSplits()) {
            $totalSplit = array_sum(array_column($this->splits, 'amount'));
            if (abs($totalSplit - $this->amount) > 0.01) {
                $errors[] = 'Split amounts do not match total amount';
            }
        }

        return $errors;
    }

    /**
     * Check if a DID is valid.
     */
    private function isValidDid(string $did): bool
    {
        return preg_match('/^did:finaegis:[a-z]+:[a-f0-9]{32}$/', $did) === 1;
    }

    /**
     * Convert to array for serialization.
     */
    public function toArray(): array
    {
        return [
            'transaction_id'    => $this->transactionId,
            'from_agent_did'    => $this->fromAgentDid,
            'to_agent_did'      => $this->toAgentDid,
            'amount'            => $this->amount,
            'currency'          => $this->currency,
            'purpose'           => $this->purpose,
            'metadata'          => $this->metadata,
            'escrow_conditions' => $this->escrowConditions,
            'splits'            => $this->splits,
            'timeout_seconds'   => $this->timeoutSeconds,
            'created_at'        => $this->createdAt->toIso8601String(),
        ];
    }

    /**
     * Create from array.
     */
    public static function fromArray(array $data): self
    {
        return new self(
            fromAgentDid: $data['from_agent_did'],
            toAgentDid: $data['to_agent_did'],
            amount: (float) $data['amount'],
            currency: $data['currency'],
            purpose: $data['purpose'],
            metadata: $data['metadata'] ?? null,
            escrowConditions: $data['escrow_conditions'] ?? null,
            splits: $data['splits'] ?? null,
            timeoutSeconds: $data['timeout_seconds'] ?? 300,
            transactionId: $data['transaction_id'] ?? null,
            createdAt: isset($data['created_at']) ? Carbon::parse($data['created_at']) : null
        );
    }
}
