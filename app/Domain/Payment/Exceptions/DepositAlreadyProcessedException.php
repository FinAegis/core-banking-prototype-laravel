<?php

declare(strict_types=1);

namespace App\Domain\Payment\Exceptions;

/**
 * Thrown when an external payment reference (Stripe PaymentIntent, Paysera
 * order, ...) has already been credited to an account.
 */
class DepositAlreadyProcessedException extends PaymentException
{
    public function __construct(
        public readonly string $source,
        public readonly string $externalReference,
    ) {
        parent::__construct(sprintf('Deposit %s has already been processed.', $externalReference));
    }
}
