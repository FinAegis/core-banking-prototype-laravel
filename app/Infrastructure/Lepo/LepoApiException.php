<?php

declare(strict_types=1);

namespace App\Infrastructure\Lepo;

use RuntimeException;

/**
 * A failed Lepo API call. Messages name the path and Lepo's error code or
 * message only — never the request or response body, which can carry
 * personal data.
 */
final class LepoApiException extends RuntimeException
{
    private function __construct(
        string $message,
        private readonly int $httpStatus,
        private readonly ?string $lepoCode,
    ) {
        parent::__construct($message);
    }

    public static function transport(string $path, int $status): self
    {
        return new self("Lepo request to {$path} failed (HTTP {$status}).", $status, null);
    }

    /**
     * Lepo's own error text is left out on purpose: it can echo submitted
     * personal data (for example an e-mail address that already exists).
     */
    public static function business(string $path, ?string $code, int $status = 200): self
    {
        $label = $code !== null && $code !== '' ? " [{$code}]" : '';

        return new self("Lepo rejected {$path}{$label} (HTTP {$status}).", $status, $code);
    }

    public static function unexpectedResponse(string $path, int $status): self
    {
        return new self("Lepo answered {$path} with a body that is not JSON (HTTP {$status}).", $status, null);
    }

    public static function notConfigured(): self
    {
        return new self('Lepo is not configured (LEPO_API_KEY is empty).', 0, null);
    }

    public function lepoCode(): ?string
    {
        return $this->lepoCode;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
