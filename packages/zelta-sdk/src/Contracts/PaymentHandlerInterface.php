<?php

declare(strict_types=1);

namespace Zelta\Contracts;

use Psr\Http\Message\ResponseInterface;

/**
 * Interface for handling payment protocol responses.
 *
 * Implementations parse 402 responses and produce payment headers
 * for the retry request.
 */
interface PaymentHandlerInterface
{
    /**
     * Check if this handler can process the given 402 response.
     */
    public function canHandle(ResponseInterface $response): bool;

    /**
     * Parse the 402 response and return headers for the retry request.
     *
     * @return array<string, string> Headers to attach to the retry request
     */
    public function handlePaymentRequired(ResponseInterface $response, string $url): array;
}
