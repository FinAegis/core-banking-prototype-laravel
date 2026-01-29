<?php

declare(strict_types=1);

namespace App\Domain\Wallet\ValueObjects;

use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Value object representing a pending signing request for hardware wallets.
 */
final readonly class PendingSigningRequest
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_AWAITING_DEVICE = 'awaiting_device';

    public const STATUS_SIGNING = 'signing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function __construct(
        public string $requestId,
        public string $userId,
        public string $associationId,
        public string $status,
        public TransactionData $transactionData,
        public string $rawDataToSign,
        public CarbonImmutable $expiresAt,
        public ?string $signature = null,
        public ?string $signedTransactionHash = null,
        public ?string $errorMessage = null,
        public array $metadata = []
    ) {
    }

    /**
     * Create a new pending signing request.
     *
     * @param  array<string, mixed>  $metadata
     */
    public static function create(
        string $requestId,
        string $userId,
        string $associationId,
        TransactionData $transactionData,
        string $rawDataToSign,
        int $ttlSeconds = 300,
        array $metadata = []
    ): self {
        if (empty($requestId)) {
            throw new InvalidArgumentException('Request ID cannot be empty');
        }

        return new self(
            requestId: $requestId,
            userId: $userId,
            associationId: $associationId,
            status: self::STATUS_PENDING,
            transactionData: $transactionData,
            rawDataToSign: $rawDataToSign,
            expiresAt: CarbonImmutable::now()->addSeconds($ttlSeconds),
            metadata: $metadata
        );
    }

    /**
     * Create from array data.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $transactionData = $data['transaction_data'] instanceof TransactionData
            ? $data['transaction_data']
            : new TransactionData(
                from: $data['transaction_data']['from'] ?? '',
                to: $data['transaction_data']['to'] ?? '',
                value: $data['transaction_data']['value'] ?? '0',
                chain: $data['transaction_data']['chain'] ?? '',
                data: $data['transaction_data']['data'] ?? null,
                gasLimit: $data['transaction_data']['gas_limit'] ?? null,
                gasPrice: $data['transaction_data']['gas_price'] ?? null,
                maxFeePerGas: $data['transaction_data']['max_fee_per_gas'] ?? null,
                maxPriorityFeePerGas: $data['transaction_data']['max_priority_fee_per_gas'] ?? null,
                nonce: $data['transaction_data']['nonce'] ?? null,
                metadata: $data['transaction_data']['metadata'] ?? []
            );

        $expiresAt = $data['expires_at'] instanceof CarbonImmutable
            ? $data['expires_at']
            : CarbonImmutable::parse($data['expires_at']);

        return new self(
            requestId: $data['request_id'],
            userId: $data['user_id'],
            associationId: $data['association_id'],
            status: $data['status'],
            transactionData: $transactionData,
            rawDataToSign: $data['raw_data_to_sign'],
            expiresAt: $expiresAt,
            signature: $data['signature'] ?? null,
            signedTransactionHash: $data['signed_transaction_hash'] ?? null,
            errorMessage: $data['error_message'] ?? null,
            metadata: $data['metadata'] ?? []
        );
    }

    public function isExpired(): bool
    {
        return CarbonImmutable::now()->isAfter($this->expiresAt);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isAwaitingDevice(): bool
    {
        return $this->status === self::STATUS_AWAITING_DEVICE;
    }

    public function isSigning(): bool
    {
        return $this->status === self::STATUS_SIGNING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    public function canBeProcessed(): bool
    {
        return ! $this->isExpired()
            && in_array($this->status, [self::STATUS_PENDING, self::STATUS_AWAITING_DEVICE], true);
    }

    public function withStatus(string $status): self
    {
        return new self(
            requestId: $this->requestId,
            userId: $this->userId,
            associationId: $this->associationId,
            status: $status,
            transactionData: $this->transactionData,
            rawDataToSign: $this->rawDataToSign,
            expiresAt: $this->expiresAt,
            signature: $this->signature,
            signedTransactionHash: $this->signedTransactionHash,
            errorMessage: $this->errorMessage,
            metadata: $this->metadata
        );
    }

    public function withSignature(string $signature, string $transactionHash): self
    {
        return new self(
            requestId: $this->requestId,
            userId: $this->userId,
            associationId: $this->associationId,
            status: self::STATUS_COMPLETED,
            transactionData: $this->transactionData,
            rawDataToSign: $this->rawDataToSign,
            expiresAt: $this->expiresAt,
            signature: $signature,
            signedTransactionHash: $transactionHash,
            errorMessage: null,
            metadata: $this->metadata
        );
    }

    public function withError(string $errorMessage): self
    {
        return new self(
            requestId: $this->requestId,
            userId: $this->userId,
            associationId: $this->associationId,
            status: self::STATUS_FAILED,
            transactionData: $this->transactionData,
            rawDataToSign: $this->rawDataToSign,
            expiresAt: $this->expiresAt,
            signature: null,
            signedTransactionHash: null,
            errorMessage: $errorMessage,
            metadata: $this->metadata
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'request_id'              => $this->requestId,
            'user_id'                 => $this->userId,
            'association_id'          => $this->associationId,
            'status'                  => $this->status,
            'transaction_data'        => $this->transactionData->toArray(),
            'raw_data_to_sign'        => $this->rawDataToSign,
            'expires_at'              => $this->expiresAt->toIso8601String(),
            'signature'               => $this->signature,
            'signed_transaction_hash' => $this->signedTransactionHash,
            'error_message'           => $this->errorMessage,
            'metadata'                => $this->metadata,
        ];
    }
}
