<?php

declare(strict_types=1);

namespace Zelta\DataObjects;

/**
 * Result DTO wrapping a response with payment metadata.
 */
final readonly class PaymentResult
{
    /**
     * @param array<string, mixed> $body       Decoded response body
     * @param int                  $statusCode HTTP status code
     * @param bool                 $paid       Whether a payment was made
     * @param array<string, mixed> $paymentMeta Payment details (protocol, network, amount)
     */
    public function __construct(
        public array $body,
        public int $statusCode,
        public bool $paid = false,
        public array $paymentMeta = [],
    ) {
    }
}
