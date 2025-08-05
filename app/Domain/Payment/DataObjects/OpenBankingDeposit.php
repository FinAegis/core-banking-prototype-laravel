<?php

declare(strict_types=1);

namespace App\Domain\Payment\DataObjects;

final class OpenBankingDeposit
{
    public function __construct(
        public readonly string $accountUuid,
        public readonly int $amount,
        public readonly string $currency,
        public readonly string $reference,
        public readonly string $bankName,
        public readonly array $metadata = []
    ) {
    }
}
