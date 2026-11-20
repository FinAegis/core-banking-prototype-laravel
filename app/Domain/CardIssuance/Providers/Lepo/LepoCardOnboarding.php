<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Providers\Lepo;

use App\Domain\CardIssuance\Models\CardFundingAccount;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Providers\CardholderProfile;
use App\Domain\CardIssuance\Providers\Contracts\CardOnboardingProvider;
use App\Domain\CardIssuance\Providers\KycStart;
use App\Models\User;
use LogicException;

/**
 * Lepo Finance onboarding: Lepo user, SumSub identity check (SDK token),
 * funding account and USDC deposit addresses.
 */
final class LepoCardOnboarding implements CardOnboardingProvider
{
    public function name(): string
    {
        return 'lepo';
    }

    public function createCardholder(User $user, CardholderProfile $profile): Cardholder
    {
        throw new LogicException('Not implemented yet.');
    }

    public function startKyc(Cardholder $cardholder): KycStart
    {
        throw new LogicException('Not implemented yet.');
    }

    public function refreshKycStatus(Cardholder $cardholder): Cardholder
    {
        throw new LogicException('Not implemented yet.');
    }

    public function openFundingAccount(Cardholder $cardholder): CardFundingAccount
    {
        throw new LogicException('Not implemented yet.');
    }

    public function syncDepositAddresses(CardFundingAccount $account): int
    {
        throw new LogicException('Not implemented yet.');
    }
}
