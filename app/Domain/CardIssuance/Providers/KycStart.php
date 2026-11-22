<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Providers;

/**
 * How the app starts the identity check: `sumsub_sdk` (a token for the
 * SumSub mobile SDK) or `hosted_url` (a page to open).
 */
final readonly class KycStart
{
    public function __construct(
        public string $method,
        public ?string $token,
        public ?string $url,
        public ?string $expiresAt,
    ) {
    }

    /**
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return array_filter([
            'method'     => $this->method,
            'token'      => $this->token,
            'url'        => $this->url,
            'expires_at' => $this->expiresAt,
        ], static fn (?string $value): bool => $value !== null && $value !== '');
    }
}
