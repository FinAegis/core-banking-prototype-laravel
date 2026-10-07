<?php

declare(strict_types=1);

namespace App\Domain\Payment\Exceptions;

/**
 * Thrown when a deposit confirmation does not belong to the authenticated
 * user (the payment was started by someone else, or targets an account the
 * user does not own) or does not match the stored order.
 */
class DepositNotAllowedException extends PaymentException
{
}
