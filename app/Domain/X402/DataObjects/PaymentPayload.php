<?php

declare(strict_types=1);

namespace App\Domain\X402\DataObjects;

use App\Domain\X402\Exceptions\X402InvalidPayloadException;

/**
 * The payment payload sent by the client in the PAYMENT-SIGNATURE header.
 *
 * Contains the protocol version, resource metadata, the accepted payment
 * requirements that the client chose to satisfy, and the signed on-chain
 * payload (e.g. EIP-3009 transferWithAuthorization parameters).
 */
readonly class PaymentPayload
{
    /**
     * @param int                       $x402Version  Protocol version (currently 2).
     * @param ResourceInfo              $resource     Metadata about the protected resource.
     * @param PaymentRequirements       $accepted     The payment option the client selected.
     * @param array<string, mixed>      $payload      Signed on-chain transfer parameters.
     * @param array<string, mixed>|null $extensions   Optional protocol extensions.
     */
    public function __construct(
        public int $x402Version,
        public ResourceInfo $resource,
        public PaymentRequirements $accepted,
        public array $payload,
        public ?array $extensions = null,
    ) {
    }

    /**
     * Serialize to a plain array.
     *
     * @return array{x402Version: int, resource: array, accepted: array, payload: array<string, mixed>, extensions: ?array<string, mixed>}
     */
    public function toArray(): array
    {
        return [
            'x402Version' => $this->x402Version,
            'resource'    => $this->resource->toArray(),
            'accepted'    => $this->accepted->toArray(),
            'payload'     => $this->payload,
            'extensions'  => $this->extensions,
        ];
    }

    /**
     * Hydrate from a plain array.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        foreach (['x402Version', 'resource', 'accepted', 'payload'] as $field) {
            if (! array_key_exists($field, $data)) {
                throw X402InvalidPayloadException::missingField($field);
            }
        }

        return new self(
            x402Version: (int) $data['x402Version'],
            resource: ResourceInfo::fromArray($data['resource']),
            accepted: PaymentRequirements::fromArray($data['accepted']),
            payload: $data['payload'],
            extensions: $data['extensions'] ?? null,
        );
    }

    /**
     * Encode the payment payload as a base64 JSON string.
     */
    public function toBase64(): string
    {
        return base64_encode((string) json_encode($this->toArray(), JSON_THROW_ON_ERROR));
    }

    /**
     * Decode a base64-encoded payment payload.
     *
     * @throws X402InvalidPayloadException
     */
    public static function fromBase64(string $encoded): self
    {
        $decoded = base64_decode($encoded, true);

        if ($decoded === false) {
            throw X402InvalidPayloadException::invalidBase64('Failed to decode PaymentPayload base64 string.');
        }

        /** @var array|null $data */
        $data = json_decode($decoded, true);

        if (! is_array($data)) {
            throw X402InvalidPayloadException::invalidBase64('PaymentPayload base64 payload is not valid JSON.');
        }

        return self::fromArray($data);
    }
}
