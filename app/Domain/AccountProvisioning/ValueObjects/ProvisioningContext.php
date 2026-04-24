<?php

declare(strict_types=1);

namespace App\Domain\AccountProvisioning\ValueObjects;

use Carbon\CarbonImmutable;

final readonly class ProvisioningContext
{
    public function __construct(
        public string $email,
        public string $name,
        public string $region,
        public ?CarbonImmutable $expiresAt,
        public ?string $note,
        public int $operatorId,
        public bool $dryRun = false,
    ) {
    }
}
