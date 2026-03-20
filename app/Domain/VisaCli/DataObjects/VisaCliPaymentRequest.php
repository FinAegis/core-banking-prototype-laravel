<?php

declare(strict_types=1);

namespace App\Domain\VisaCli\DataObjects;

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
    }
}
