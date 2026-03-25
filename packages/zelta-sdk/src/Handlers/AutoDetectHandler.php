<?php

declare(strict_types=1);

namespace Zelta\Handlers;

use Psr\Http\Message\ResponseInterface;
use Zelta\Contracts\PaymentHandlerInterface;

/**
 * Auto-detects the payment protocol from the 402 response headers
 * and delegates to the appropriate handler.
 */
class AutoDetectHandler implements PaymentHandlerInterface
{
    /** @var list<PaymentHandlerInterface> */
    private array $handlers;

    public function __construct(PaymentHandlerInterface ...$handlers)
    {
        $this->handlers = array_values($handlers);
    }

    public function canHandle(ResponseInterface $response): bool
    {
        foreach ($this->handlers as $handler) {
            if ($handler->canHandle($response)) {
                return true;
            }
        }

        return false;
    }

    public function handlePaymentRequired(ResponseInterface $response, string $url): array
    {
        foreach ($this->handlers as $handler) {
            if ($handler->canHandle($response)) {
                return $handler->handlePaymentRequired($response, $url);
            }
        }

        return [];
    }
}
