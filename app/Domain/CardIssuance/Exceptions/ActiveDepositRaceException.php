<?php

/**
 * ActiveDepositRaceException — internal sentinel raised by
 * WaitlistDepositService::startDeposit when a concurrent request grabbed
 * the single-active-deposit slot under lockForUpdate between the
 * pre-check and the INSERT.
 *
 * Caller catches this exception, performs compensation (expire Stripe
 * session + un-redeem quote), and returns ERR_CARDS_002 to the user.
 *
 * Not part of the public service contract — never thrown out of public methods.
 */

declare(strict_types=1);

namespace App\Domain\CardIssuance\Exceptions;

use RuntimeException;

final class ActiveDepositRaceException extends RuntimeException
{
}
