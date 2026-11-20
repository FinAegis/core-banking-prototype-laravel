<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Providers\Lepo;

use App\Domain\CardIssuance\Models\CardFundingAccount;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Providers\CardholderProfile;
use App\Domain\CardIssuance\Providers\CardProviderException;
use App\Domain\CardIssuance\Providers\CardProviderSelector;
use App\Domain\CardIssuance\Providers\Contracts\CardOnboardingProvider;
use App\Domain\CardIssuance\Providers\KycStart;
use App\Infrastructure\Lepo\LepoApiException;
use App\Infrastructure\Lepo\LepoClient;
use App\Models\User;

/**
 * Lepo Finance onboarding: Lepo user, SumSub identity check (SDK token),
 * funding account and USDC deposit addresses.
 *
 * Lepo's KYC webhook payloads are not documented in detail, so the
 * cardholder's status always comes from Lepo's detailed status: `verified`
 * only once the card issuer (Interlace) approved the user.
 */
final class LepoCardOnboarding implements CardOnboardingProvider
{
    /** Countries and nationalities without cards in v1 (Lepo needs an SSN for US nationals). */
    private const RESTRICTED = ['US'];

    /** Lepo network or chain spellings → our network names. */
    private const NETWORKS = [
        'arbitrum' => 'arbitrum', 'arb' => 'arbitrum', '42161' => 'arbitrum',
        'polygon'  => 'polygon', 'matic' => 'polygon', '137' => 'polygon',
        'base'     => 'base', '8453' => 'base',
        'ethereum' => 'ethereum', 'eth' => 'ethereum', '1' => 'ethereum',
    ];

    public function __construct(private readonly LepoClient $client)
    {
    }

    public function name(): string
    {
        return 'lepo';
    }

    public function createCardholder(User $user, CardholderProfile $profile): Cardholder
    {
        foreach ([$profile->nationality, $profile->country] as $code) {
            if (in_array(strtoupper($code), self::RESTRICTED, true)) {
                throw CardProviderException::restrictedNationality(strtoupper($code));
            }
        }

        $lepoUserId = $this->createOrAdoptUser($user, $profile);

        $cardholder = new Cardholder();
        $cardholder->forceFill([
            'user_id'                => $user->id,
            'provider'               => 'lepo',
            'first_name'             => $profile->firstName,
            'last_name'              => $profile->lastName,
            'email'                  => $profile->email,
            'phone'                  => $profile->phone,
            'kyc_status'             => 'pending',
            'issuer_cardholder_id'   => $lepoUserId,
            'shipping_address_line1' => $profile->addressLine1,
            'shipping_address_line2' => $profile->addressLine2,
            'shipping_city'          => $profile->city,
            'shipping_state'         => $profile->state,
            'shipping_postal_code'   => $profile->postalCode,
            'shipping_country'       => strtoupper($profile->country),
            'verification_data'      => [
                'date_of_birth' => $profile->dateOfBirth,
                'nationality'   => strtoupper($profile->nationality),
                'questionnaire' => $profile->questionnaire(),
            ],
        ]);
        $cardholder->save();

        return $cardholder;
    }

    public function startKyc(Cardholder $cardholder): KycStart
    {
        $data = is_array($cardholder->verification_data) ? $cardholder->verification_data : [];
        $questionnaire = is_array($data['questionnaire'] ?? null) ? $data['questionnaire'] : [];

        $response = $this->call(fn (): array => $this->client->createSumsubAccessToken($this->lepoUserId($cardholder), $questionnaire));

        $token = $response['token'] ?? null;
        if (! is_string($token) || $token === '') {
            throw CardProviderException::providerUnavailable('lepo', 'NO_KYC_TOKEN');
        }

        if (in_array($cardholder->kyc_status, ['pending', 'action_required'], true)) {
            $cardholder->forceFill(['kyc_status' => 'in_review'])->save();
        }

        return new KycStart('sumsub_sdk', $token, null, null);
    }

    public function refreshKycStatus(Cardholder $cardholder): Cardholder
    {
        $detail = $this->call(fn (): array => $this->client->getKycDetailedStatus($this->lepoUserId($cardholder)));
        $issuer = is_array($detail['interlace'] ?? null) ? $detail['interlace'] : [];
        $issuerStatus = strtolower((string) ($issuer['status'] ?? ''));

        $status = match (true) {
            ($detail['cardIssuerKycApproved'] ?? false) === true || ($issuer['approved'] ?? false) === true => 'verified',
            $issuerStatus === 'rejected'                                                                    => 'rejected',
            in_array($issuerStatus, ['action_required', 'retry', 'resubmission_required'], true)
                || (is_string($issuer['requiredAction'] ?? null) && $issuer['requiredAction'] !== '') => 'action_required',
            in_array($issuerStatus, ['pending', 'submitted', 'in_review', 'processing'], true)
                || ($detail['lepoFinanceKycApproved'] ?? false) === true => 'in_review',
            default                                                      => $cardholder->kyc_status,
        };

        $changes = ['kyc_status' => $status];
        if ($status === 'verified' && $cardholder->verified_at === null) {
            $changes['verified_at'] = now();
        }

        if ($status === 'rejected') {
            $reason = $issuer['reason'] ?? null;
            $changes['kyc_rejection_reason'] = is_string($reason) ? mb_substr($reason, 0, 255) : null;
        }

        $cardholder->forceFill($changes)->save();

        return $cardholder;
    }

    public function openFundingAccount(Cardholder $cardholder): CardFundingAccount
    {
        $account = CardFundingAccount::firstOrCreate(
            ['user_id' => $cardholder->user_id, 'provider' => 'lepo'],
            ['cardholder_id' => $cardholder->id],
        );

        if ($account->status !== 'pending') {
            return $account;
        }

        try {
            $response = $this->client->createFundingAccount($this->lepoUserId($cardholder));
            $accountId = $response['accountId'] ?? $response['id'] ?? null;
            $account->provider_account_id = is_scalar($accountId) ? (string) $accountId : null;
        } catch (LepoApiException $e) {
            if ($e->httpStatus() !== 409) {
                throw CardProviderException::providerUnavailable('lepo', $e->lepoCode());
            }
        }

        $account->status = 'active';
        $account->save();

        return $account;
    }

    public function syncDepositAddresses(CardFundingAccount $account): int
    {
        $cardholder = $account->cardholder;
        if ($cardholder === null) {
            return 0;
        }

        $response = $this->call(fn (): array => $this->client->getCryptoAddresses($this->lepoUserId($cardholder)));
        $offered = CardProviderSelector::listFromConfig('cardissuance.providers.lepo_deposit_networks');
        $rows = array_merge(
            is_array($response['addresses'] ?? null) ? $response['addresses'] : [],
            is_array($response['cryptoAddresses'] ?? null) ? $response['cryptoAddresses'] : [],
        );

        $stored = 0;
        foreach ($rows as $row) {
            if (! is_array($row) || ($row['isActive'] ?? true) === false) {
                continue;
            }

            $network = self::NETWORKS[strtolower((string) ($row['network'] ?? $row['chain'] ?? ''))] ?? null;
            $address = $row['address'] ?? null;
            if ($network === null || ! in_array($network, $offered, true) || strtoupper((string) ($row['currency'] ?? '')) !== 'USDC' || ! is_string($address) || $address === '') {
                continue;
            }

            $account->depositAddresses()->updateOrCreate(
                ['network' => $network, 'asset' => 'USDC'],
                ['provider' => 'lepo', 'address' => $address, 'is_active' => true],
            );
            $stored++;
        }

        return $stored;
    }

    private function createOrAdoptUser(User $user, CardholderProfile $profile): string
    {
        try {
            $created = $this->client->createUser([
                'email'          => $profile->email,
                'firstName'      => $profile->firstName,
                'lastName'       => $profile->lastName,
                'externalUserId' => (string) $user->uuid,
                'phone'          => $profile->phone,
                'dateOfBirth'    => $profile->dateOfBirth,
                'address'        => implode(', ', array_filter([$profile->addressLine1, $profile->addressLine2])),
                'city'           => $profile->city,
                'state'          => $profile->state,
                'country'        => strtoupper($profile->country),
                'zipCode'        => $profile->postalCode,
            ]);
        } catch (LepoApiException $e) {
            if ($e->httpStatus() !== 409) {
                throw CardProviderException::providerUnavailable('lepo', $e->lepoCode());
            }

            $created = $this->call(fn (): ?array => $this->client->findUserByExternalId((string) $user->uuid)) ?? [];
        }

        $id = $created['id'] ?? null;
        if (! is_scalar($id) || (string) $id === '') {
            throw CardProviderException::providerUnavailable('lepo', 'NO_USER_ID');
        }

        return (string) $id;
    }

    private function lepoUserId(Cardholder $cardholder): string
    {
        $id = (string) $cardholder->issuer_cardholder_id;
        if ($id === '') {
            throw CardProviderException::providerUnavailable('lepo', 'NO_USER_ID');
        }

        return $id;
    }

    /**
     * @template T
     *
     * @param  callable(): T  $call
     * @return T
     */
    private function call(callable $call): mixed
    {
        try {
            return $call();
        } catch (LepoApiException $e) {
            throw CardProviderException::providerUnavailable('lepo', $e->lepoCode());
        }
    }
}
