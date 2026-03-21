<?php

declare(strict_types=1);

namespace App\Domain\VisaCli\DataObjects;

use App\Domain\VisaCli\Exceptions\VisaCliPaymentException;
use Illuminate\Support\Str;

final class VisaCliPaymentRequest
{
    public readonly string $requestId;

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        public readonly string $agentId,
        public readonly string $url,
        public readonly int $amountCents,
        public readonly string $currency = 'USD',
        public readonly ?string $cardId = null,
        public readonly ?string $purpose = null,
        public readonly array $metadata = [],
    ) {
        $this->requestId = Str::uuid()->toString();

        $this->validateUrl($url);
        $this->validateCardId($cardId);
    }

    private function validateUrl(string $url): void
    {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new VisaCliPaymentException("Invalid payment URL: {$url}");
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new VisaCliPaymentException("Only http/https URLs are allowed, got: {$scheme}");
        }

        // SSRF prevention: block internal/metadata addresses
        $host = parse_url($url, PHP_URL_HOST);
        if ($host !== null && $host !== false) {
            $host = (string) $host;
            $blocked = ['localhost', '127.0.0.1', '0.0.0.0', '169.254.169.254', '[::1]', 'metadata.google.internal'];
            if (in_array(strtolower($host), $blocked, true) || str_starts_with($host, '10.') || str_starts_with($host, '192.168.')) {
                throw new VisaCliPaymentException('Payment to internal/private addresses is not allowed.');
            }
        }
    }

    private function validateCardId(?string $cardId): void
    {
        if ($cardId !== null && ! preg_match('/^[a-zA-Z0-9_\-]{1,255}$/', $cardId)) {
            throw new VisaCliPaymentException('Invalid card identifier format.');
        }
    }
}
