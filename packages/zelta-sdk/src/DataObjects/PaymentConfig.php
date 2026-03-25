<?php

declare(strict_types=1);

namespace Zelta\DataObjects;

/**
 * Configuration DTO for the Zelta client.
 */
final readonly class PaymentConfig
{
    public function __construct(
        public string $baseUrl,
        public ?string $apiKey = null,
        public ?string $preferredNetwork = null,
        public bool $autoPay = true,
        public int $timeoutSeconds = 30,
    ) {
    }
}
