<?php

declare(strict_types=1);

namespace App\Domain\AccountProvisioning\Contracts;

use App\Domain\AccountProvisioning\ValueObjects\ProvisioningContext;
use App\Models\User;
use Carbon\CarbonImmutable;

interface AccountProfile
{
    /** Profile slug, e.g. 'reviewer'. */
    public function name(): string;

    /**
     * Flag column => value map that will be written to account_flags.
     *
     * @return array<string, bool|int|string|CarbonImmutable|null>
     */
    public function flags(ProvisioningContext $ctx): array;

    /** Apply seeded content (wallets, balances, cards, etc.) for the user. */
    public function provision(User $user, ProvisioningContext $ctx): void;
}
