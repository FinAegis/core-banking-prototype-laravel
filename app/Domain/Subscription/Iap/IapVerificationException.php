<?php

/**
 * IapVerificationException — raised by AppleReceiptVerifier /
 * GooglePlayReceiptVerifier when the receipt cannot be cryptographically
 * verified or the store API rejects the receipt.
 *
 * The /iap/verify controller catches this and emits ERR_SUB_001.
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Iap;

use RuntimeException;

final class IapVerificationException extends RuntimeException
{
}
