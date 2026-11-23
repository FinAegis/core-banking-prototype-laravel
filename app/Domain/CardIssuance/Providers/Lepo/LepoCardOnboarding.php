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
use App\Domain\CardIssuance\Support\PublicLink;
use App\Infrastructure\Lepo\LepoApiException;
use App\Infrastructure\Lepo\LepoClient;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

/**
 * Lepo Finance onboarding: Lepo user, SumSub identity check (our hosted page
 * or a native SDK token), funding account and USDC deposit addresses.
 *
 * Lepo's KYC webhook payloads are not documented in detail, so the
 * cardholder's status always comes from Lepo's detailed status: `verified`
 * only once the card issuer (Interlace) approved the user.
 */
final class LepoCardOnboarding implements CardOnboardingProvider
{
    /** Countries and nationalities without cards in v1 (Lepo needs an SSN for US nationals). */
    private const RESTRICTED = LepoCardholderForm::EXCLUDED_COUNTRIES;

    /** Values Lepo accepts for the identity-check questionnaire. */
    public const ANNUAL_SALARY_BANDS = ['0-10000', '10001-30000', '30001-50000', '50001-75000', '75001-100000', '100001-150000', '150001-250000', '250001+'];

    public const MONTHLY_VOLUME_BANDS = ['0-1000', '1001-5000', '5001-15000', '15001-50000', '50001-100000', '100001+'];

    /** Lepo allows up to 50 characters per name. */
    public const NAME_MAX_LENGTH = 50;

    /** Lepo network or chain spellings → our network names. */
    public const NETWORKS = [
        'arbitrum' => 'arbitrum', 'arb' => 'arbitrum', '42161' => 'arbitrum', 'arbitrum one' => 'arbitrum',
        'polygon'  => 'polygon', 'matic' => 'polygon', '137' => 'polygon', 'polygon pos' => 'polygon',
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

        $existing = $this->existingCardholder($user);
        if ($existing !== null) {
            return $existing;
        }

        $lepoUserId = $this->createOrAdoptUser($user, $profile);

        $cardholder = new Cardholder();
        $cardholder->forceFill([
            'user_id'                => $user->id,
            'provider'               => 'lepo',
            'first_name'             => $profile->firstName,
            'last_name'              => $profile->lastName,
            'email'                  => $this->accountEmail($user, $profile),
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

        try {
            $cardholder->save();
        } catch (UniqueConstraintViolationException) {
            // A concurrent request created it first (Lepo answered it 201 and
            // this one 409): keep the single row.
            return $this->existingCardholder($user) ?? throw CardProviderException::providerUnavailable('lepo', 'DUPLICATE_CARDHOLDER');
        }

        return $cardholder;
    }

    /**
     * Replaces the questionnaire answers of a cardholder that has not been
     * through the identity check yet, so a refused answer can be corrected.
     *
     * @param  array<string, string>  $questionnaire  keyed as Lepo names the answers
     */
    public function updateQuestionnaire(Cardholder $cardholder, array $questionnaire): Cardholder
    {
        $data = is_array($cardholder->verification_data) ? $cardholder->verification_data : [];
        $data['questionnaire'] = $questionnaire;

        $cardholder->forceFill(['verification_data' => $data])->save();

        return $cardholder;
    }

    public static function kycMethod(): string
    {
        return config('cardissuance.providers.lepo_kyc_method') === 'sumsub_sdk' ? 'sumsub_sdk' : 'hosted_url';
    }

    public function startKyc(Cardholder $cardholder): KycStart
    {
        if (self::kycMethod() === 'hosted_url') {
            // The page asks Lepo for a token when it opens, so an unopened
            // link costs nothing and an expired token is never handed out.
            $expires = now()->addMinutes(max(5, (int) config('cardissuance.providers.kyc_link_minutes', 30)));

            // Only the path is signed, so a proxy or a scheme change between
            // the API and the page cannot break the signature.
            $path = URL::temporarySignedRoute('cards.kyc.hosted', $expires, ['cardholder' => $cardholder->id], absolute: false);

            return new KycStart('hosted_url', null, PublicLink::to($path), $expires->toIso8601String());
        }

        // The status moves on only when Lepo reports a submission: a user who
        // closes the SDK without submitting can simply start again. An
        // already approved check has no token; the status is synced and the
        // app reads it from the onboarding state.
        $token = $this->sdkToken($cardholder)
            ?? throw CardProviderException::providerUnavailable('lepo', 'KYC_ALREADY_APPROVED');

        return new KycStart('sumsub_sdk', $token, null, null);
    }

    /**
     * A SumSub access token for the web SDK on our identity-check page, or
     * null when Lepo says the user's identity check is already approved (the
     * cardholder's status is then synced from Lepo).
     */
    public function webKycToken(Cardholder $cardholder): ?string
    {
        return $this->sdkToken($cardholder);
    }

    /**
     * The token both SumSub SDKs need comes from sumsub-access-token. The
     * accessToken that sumsub/kyc-url answers is a link to Lepo's own hosted
     * page, not an SDK token (Lepo, 2026-11-23). Lepo needs the questionnaire
     * answers on every call and answers 400 KYC_ALREADY_APPROVED once the
     * check is approved.
     */
    private function sdkToken(Cardholder $cardholder): ?string
    {
        try {
            $response = $this->client->createSumsubAccessToken($this->lepoUserId($cardholder), $this->questionnaireOf($cardholder));
        } catch (LepoApiException $e) {
            if ($e->lepoCode() === 'KYC_ALREADY_APPROVED') {
                $this->syncAfterApproval($cardholder);

                return null;
            }

            throw CardProviderException::providerUnavailable('lepo', $e->lepoCode());
        }

        $token = $response['token'] ?? null;
        if (! is_string($token) || $token === '') {
            throw CardProviderException::providerUnavailable('lepo', 'NO_KYC_TOKEN');
        }

        return $token;
    }

    /**
     * Read the decision Lepo already made. If that read fails, the webhook
     * still brings the status, so the approval is not reported as an error.
     */
    private function syncAfterApproval(Cardholder $cardholder): void
    {
        try {
            $this->refreshKycStatus($cardholder);
        } catch (CardProviderException $e) {
            Log::warning('Lepo identity check already approved, but its status could not be read', [
                'cardholder_id' => $cardholder->id,
                'reason'        => $e->getMessage(),
            ]);
        }
    }

    public function refreshKycStatus(Cardholder $cardholder): Cardholder
    {
        $detail = $this->call(fn (): array => $this->client->getKycDetailedStatus($this->lepoUserId($cardholder)));
        $issuer = is_array($detail['interlace'] ?? null) ? $detail['interlace'] : [];
        $issuerStatus = strtolower((string) ($issuer['status'] ?? ''));

        // Before the card issuer sees the user, a decision can only come from
        // the identity check itself (the user's own kycStatus).
        $undecided = ! in_array($issuerStatus, ['approved', 'rejected', 'action_required', 'retry', 'resubmission_required', 'pending', 'submitted', 'in_review', 'processing'], true);
        if ($undecided && ($detail['cardIssuerKycApproved'] ?? false) !== true && ($detail['lepoFinanceKycApproved'] ?? false) !== true) {
            $lepoUser = $this->call(fn (): array => $this->client->getUser($this->lepoUserId($cardholder)));
            $issuerStatus = match (strtoupper((string) ($lepoUser['kycStatus'] ?? ''))) {
                'REJECTED'             => 'rejected',
                'RETRY'                => 'action_required',
                'PENDING', 'SUBMITTED' => 'pending',
                default                => $issuerStatus,
            };
        }

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

        // "Sent from the page" only bridges the wait for Lepo's first answer;
        // once Lepo changes the status it no longer applies.
        if ($status !== $cardholder->kyc_status && is_array($cardholder->verification_data) && array_key_exists('kyc_submitted_at', $cardholder->verification_data)) {
            $data = $cardholder->verification_data;
            unset($data['kyc_submitted_at']);
            $changes['verification_data'] = $data;
        }
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
            $accountId = $response['interlaceAccountId'] ?? $response['accountId'] ?? null;
            $account->provider_account_id = is_scalar($accountId) && (string) $accountId !== '' ? (string) $accountId : null;
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

        $kept = [];
        $skipped = 0;
        foreach ($rows as $row) {
            if (! is_array($row) || ($row['isActive'] ?? true) === false) {
                continue;
            }

            $network = $this->network($row);
            $address = $row['address'] ?? null;
            if ($network === null || ! in_array($network, $offered, true) || strtoupper((string) ($row['currency'] ?? '')) !== 'USDC' || ! is_string($address) || $address === '') {
                $skipped++;

                continue;
            }

            $account->depositAddresses()->updateOrCreate(
                ['network' => $network, 'asset' => 'USDC'],
                ['provider' => 'lepo', 'address' => $address, 'is_active' => true],
            );
            $kept[] = $network;
        }

        // Addresses Lepo no longer returns, or networks no longer offered, must
        // not be shown: money sent there would not reach the card.
        $account->depositAddresses()->whereNotIn('network', $kept === [] ? [''] : $kept)->update(['is_active' => false]);

        if ($skipped > 0) {
            Log::info('Lepo deposit addresses skipped', ['funding_account_id' => $account->id, 'skipped' => $skipped, 'kept' => count($kept)]);
        }

        return count($kept);
    }

    /**
     * @param  array<mixed>  $row
     */
    private function network(array $row): ?string
    {
        foreach (['network', 'chain'] as $field) {
            $value = strtolower(trim((string) ($row[$field] ?? '')));
            if ($value !== '' && isset(self::NETWORKS[$value])) {
                return self::NETWORKS[$value];
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function questionnaireOf(Cardholder $cardholder): array
    {
        $data = is_array($cardholder->verification_data) ? $cardholder->verification_data : [];

        return is_array($data['questionnaire'] ?? null) ? $data['questionnaire'] : [];
    }

    private function existingCardholder(User $user): ?Cardholder
    {
        return Cardholder::query()->where('user_id', $user->id)->where('provider', 'lepo')->first();
    }

    private function accountEmail(User $user, CardholderProfile $profile): string
    {
        $email = trim((string) $user->email);

        return $email !== '' ? $email : $profile->email;
    }

    private function createOrAdoptUser(User $user, CardholderProfile $profile): string
    {
        try {
            $created = $this->client->createUser([
                'email'          => $this->accountEmail($user, $profile),
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
