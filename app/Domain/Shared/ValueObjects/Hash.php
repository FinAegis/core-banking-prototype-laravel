<?php

declare(strict_types=1);

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;

final readonly class Hash
{
    public function __construct(
        private string $hash
    ) {
        if (! $this->isValidHash($hash)) {
            throw new InvalidArgumentException('Invalid hash provided.');
        }
    }

    /**
     * Create hash from data using SHA3-512.
     */
    public static function make(string $data): self
    {
        return new self(hash('sha3-512', $data));
    }

    /**
     * Create hash from multiple data elements.
     */
    public static function fromData(array $data): self
    {
        $encoded = json_encode($data);
        if ($encoded === false) {
            throw new InvalidArgumentException('Failed to encode data for hashing');
        }

        return new self(hash('sha3-512', $encoded));
    }

    /**
     * Get the hash value.
     */
    public function value(): string
    {
        return $this->hash;
    }

    /**
     * Get string representation of the hash.
     */
    public function toString(): string
    {
        return $this->hash;
    }

    /**
     * Validate the hash format.
     */
    private function isValidHash(string $hash): bool
    {
        // SHA3-512 produces a 128-character hexadecimal string
        return ctype_xdigit($hash) && strlen($hash) === 128;
    }

    /**
     * Check if two hashes are equal.
     */
    public function equals(self $other): bool
    {
        return hash_equals($this->hash, $other->hash);
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'hash' => $this->hash,
        ];
    }

    public function __toString(): string
    {
        return $this->hash;
    }
}
