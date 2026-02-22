<?php

declare(strict_types=1);

namespace App\Domain\X402\DataObjects;

/**
 * Response from the facilitator's settle endpoint.
 *
 * Contains the on-chain settlement result including the transaction hash
 * and network where the transfer was executed.
 */
readonly class SettleResponse
{
    /**
     * @param bool        $success      Whether settlement succeeded.
     * @param string|null $errorReason  Machine-readable reason code on failure.
     * @param string|null $errorMessage Human-readable explanation on failure.
     * @param string|null $payer        The payer's wallet address.
     * @param string      $transaction  On-chain transaction hash.
     * @param string      $network      CAIP-2 network identifier where settlement occurred.
     * @param array<string, mixed>|null $extensions  Optional protocol extensions.
     */
    public function __construct(
        public bool $success,
        public ?string $errorReason = null,
        public ?string $errorMessage = null,
        public ?string $payer = null,
        public string $transaction = '',
        public string $network = '',
        public ?array $extensions = null,
    ) {
    }

    /**
     * Serialize to a plain array.
     *
     * @return array{success: bool, errorReason: ?string, errorMessage: ?string, payer: ?string, transaction: string, network: string, extensions: ?array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'success'      => $this->success,
            'errorReason'  => $this->errorReason,
            'errorMessage' => $this->errorMessage,
            'payer'        => $this->payer,
            'transaction'  => $this->transaction,
            'network'      => $this->network,
            'extensions'   => $this->extensions,
        ];
    }

    /**
     * Hydrate from a plain array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (! array_key_exists('success', $data)) {
            throw \App\Domain\X402\Exceptions\X402InvalidPayloadException::missingField('success');
        }

        return new self(
            success: $data['success'],
            errorReason: $data['errorReason'] ?? null,
            errorMessage: $data['errorMessage'] ?? null,
            payer: $data['payer'] ?? null,
            transaction: $data['transaction'] ?? '',
            network: $data['network'] ?? '',
            extensions: $data['extensions'] ?? null,
        );
    }

    /**
     * Encode the settle response as a base64 JSON string.
     */
    public function toBase64(): string
    {
        return base64_encode((string) json_encode($this->toArray(), JSON_THROW_ON_ERROR));
    }
}
