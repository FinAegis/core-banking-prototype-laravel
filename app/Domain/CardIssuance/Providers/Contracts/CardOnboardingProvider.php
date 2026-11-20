<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Providers\Contracts;

use App\Domain\CardIssuance\Models\CardFundingAccount;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Providers\CardholderProfile;
use App\Domain\CardIssuance\Providers\KycStart;
use App\Models\User;

/**
 * Onboarding steps every card provider implements: the provider-side
 * cardholder, the identity check, and the funding account with its deposit
 * addresses.
 */
interface CardOnboardingProvider
{
    public function name(): string;

    public function createCardholder(User $user, CardholderProfile $profile): Cardholder;

    public function startKyc(Cardholder $cardholder): KycStart;

    public function refreshKycStatus(Cardholder $cardholder): Cardholder;

    public function openFundingAccount(Cardholder $cardholder): CardFundingAccount;

    /**
     * Stores the provider's deposit addresses that this platform offers and
     * returns how many are stored.
     */
    public function syncDepositAddresses(CardFundingAccount $account): int;
}
