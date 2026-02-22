<?php

declare(strict_types=1);

namespace App\Domain\X402\DataObjects;

/**
 * Response from the facilitator's verify endpoint.
 *
 * Indicates whether the payment authorization is cryptographically valid
 * and has not been used or expired.
 */
readonly class VerifyResponse
{
    /**
     * @param bool        $isValid        Whether the payment payload is valid.
     * @param string|null $invalidReason  Machine-readable reason code when invalid.
     * @param string|null $invalidMessage Human-readable explanation when invalid.
     * @param string|null $payer          The payer's wallet address (when valid).
     * @param array<string, mixed>|null $extensions  Optional protocol extensions.
     */
    public function __construct(
        public bool $isValid,
        public ?string $invalidReason = null,
        public ?string $invalidMessage = null,
        public ?string $payer = null,
        public ?array $extensions = null,
    ) {
    }

    /**
     * Serialize to a plain array.
     *
     * @return array{isValid: bool, invalidReason: ?string, invalidMessage: ?string, payer: ?string, extensions: ?array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'isValid'        => $this->isValid,
            'invalidReason'  => $this->invalidReason,
            'invalidMessage' => $this->invalidMessage,
            'payer'          => $this->payer,
            'extensions'     => $this->extensions,
        ];
    }

    /**
     * Hydrate from a plain array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        if (! array_key_exists('isValid', $data)) {
            throw \App\Domain\X402\Exceptions\X402InvalidPayloadException::missingField('isValid');
        }

        return new self(
            isValid: $data['isValid'],
            invalidReason: $data['invalidReason'] ?? null,
            invalidMessage: $data['invalidMessage'] ?? null,
            payer: $data['payer'] ?? null,
            extensions: $data['extensions'] ?? null,
        );
    }
}
