<?php

declare(strict_types=1);

namespace App\Domain\Batch\Exceptions;

use Workflow\Exceptions\NonRetryableException;

/**
 * A batch item that must not be processed: the source account is not the
 * batch owner's, an account is missing or frozen, the amount is invalid or
 * the balance is too low. Retrying cannot change the answer, so the activity
 * fails at once instead of being retried, and no money moves.
 */
final class BatchItemRefused extends NonRetryableException
{
}
