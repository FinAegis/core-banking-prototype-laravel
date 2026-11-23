<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Models\CardFundingAccount;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Providers\CardholderProfile;
use App\Domain\CardIssuance\Providers\CardProviderException;
use App\Domain\CardIssuance\Providers\Lepo\LepoCardOnboarding;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config([
        'cardissuance.issuers.lepo.base_url'           => 'https://lepo.test/api/v2',
        'cardissuance.issuers.lepo.api_key'            => 'test-key',
        'cardissuance.providers.lepo_deposit_networks' => 'arbitrum,polygon',
    ]);
});

function lepoProfile(string $nationality = 'LT', string $country = 'LT'): CardholderProfile
{
    return new CardholderProfile(
        firstName: 'Ada',
        lastName: 'Lovelace',
        email: 'ada@example.com',
        phone: '+37060000000',
        dateOfBirth: '1990-01-01',
        addressLine1: 'Gedimino 1',
        addressLine2: 'Apt 2',
        city: 'Vilnius',
        state: null,
        postalCode: '01103',
        country: $country,
        nationality: $nationality,
        occupation: 'engineer',
        annualSalary: '30001-50000',
        accountPurpose: 'spending',
        expectedMonthlyVolume: '1001-5000',
        documentIssueDate: '2020-05-01',
    );
}

function lepoCardholderFor(User $user, string $status = 'pending'): Cardholder
{
    $cardholder = new Cardholder();
    $cardholder->forceFill([
        'user_id'           => $user->id, 'provider' => 'lepo', 'first_name' => 'Ada', 'last_name' => 'Lovelace',
        'kyc_status'        => $status, 'issuer_cardholder_id' => '12345',
        'verification_data' => ['questionnaire' => lepoProfile()->questionnaire()],
    ]);
    $cardholder->save();

    return $cardholder;
}

it('creates a lepo user and a local cardholder', function (): void {
    Http::fake(['lepo.test/api/v2/users' => Http::response(['id' => 12345, 'externalUserId' => 'x', 'kycStatus' => 'NOT_STARTED'], 201)]);
    $user = User::factory()->create();

    $cardholder = app(LepoCardOnboarding::class)->createCardholder($user, lepoProfile());

    expect($cardholder->provider)->toBe('lepo')
        ->and($cardholder->issuer_cardholder_id)->toBe('12345')
        ->and($cardholder->kyc_status)->toBe('pending')
        ->and($cardholder->verification_data['questionnaire']['occupation'] ?? null)->toBe('engineer');
    Http::assertSent(fn (Request $request): bool => $request['externalUserId'] === $user->uuid
        && $request['address'] === 'Gedimino 1, Apt 2'
        && $request['country'] === 'LT');
});

it('refuses US nationals before calling lepo', function (string $nationality, string $country): void {
    Http::fake();

    expect(fn () => app(LepoCardOnboarding::class)->createCardholder(User::factory()->create(), lepoProfile($nationality, $country)))
        ->toThrow(CardProviderException::class);
    Http::assertNothingSent();
})->with([['US', 'LT'], ['LT', 'US']]);

it('adopts the existing Lepo user on a 409', function (): void {
    $user = User::factory()->create();
    Http::fake([
        'lepo.test/api/v2/users?*' => Http::response(['users' => [['id' => 777, 'externalUserId' => $user->uuid]], 'total' => 1]),
        'lepo.test/api/v2/users'   => Http::response(['message' => 'exists', 'code' => 'USER_EXISTS'], 409),
    ]);

    $cardholder = app(LepoCardOnboarding::class)->createCardholder($user, lepoProfile());

    expect($cardholder->issuer_cardholder_id)->toBe('777');
});

it('hands out a signed identity-check page by default', function (): void {
    Http::fake();
    $cardholder = lepoCardholderFor(User::factory()->create());

    $start = app(LepoCardOnboarding::class)->startKyc($cardholder)->toArray();

    expect($start['method'])->toBe('hosted_url')
        ->and($start['url'] ?? '')->toContain('/cards/kyc/' . $cardholder->id . '?')
        ->and($start['url'] ?? '')->toContain('signature=')
        ->and($start['expires_at'] ?? null)->not->toBeNull()
        ->and($cardholder->fresh()?->kyc_status)->toBe('pending');
    // The page asks Lepo for the token when it opens, not when the link is made.
    Http::assertNothingSent();
});

it('builds the identity-check link from APP_URL over https in production', function (): void {
    Http::fake();
    config(['app.url' => 'http://zelta.app']);
    app()->detectEnvironment(fn (): string => 'production');
    $cardholder = lepoCardholderFor(User::factory()->create());

    $url = (string) app(LepoCardOnboarding::class)->startKyc($cardholder)->url;

    app()->detectEnvironment(fn (): string => 'testing');
    expect($url)->toStartWith('https://zelta.app/cards/kyc/' . $cardholder->id . '?');
});

it('forgets that the check was sent once Lepo changes the status', function (): void {
    Http::fake(['lepo.test/api/v2/users/12345/kyc/detailed-status' => Http::response(['cardIssuerKycApproved' => false, 'interlace' => ['status' => 'action_required', 'requiredAction' => 'RESUBMIT']])]);
    $cardholder = lepoCardholderFor(User::factory()->create());
    $cardholder->forceFill(['verification_data' => [...($cardholder->verification_data ?? []), 'kyc_submitted_at' => now()->toIso8601String()]])->save();

    app(LepoCardOnboarding::class)->refreshKycStatus($cardholder);

    expect($cardholder->fresh()?->kyc_status)->toBe('action_required')
        ->and($cardholder->fresh()?->verification_data)->not->toHaveKey('kyc_submitted_at')
        ->and($cardholder->fresh()?->verification_data['questionnaire'] ?? null)->not->toBeNull();
});

it('gets a web identity-check token with the questionnaire answers', function (): void {
    Http::fake(['lepo.test/api/v2/users/12345/kyc/sumsub-access-token' => Http::response(['success' => true, 'token' => 'web-token', 'userId' => '12345'])]);
    $cardholder = lepoCardholderFor(User::factory()->create());

    expect(app(LepoCardOnboarding::class)->webKycToken($cardholder))->toBe('web-token');
    Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/users/12345/kyc/sumsub-access-token')
        && $request['occupation'] === 'engineer' && $request['documentIssueDate'] === '2020-05-01');
});

it('refuses a web identity-check answer without a token', function (): void {
    Http::fake(['lepo.test/api/v2/users/12345/kyc/sumsub-access-token' => Http::response(['success' => true, 'token' => null])]);
    $cardholder = lepoCardholderFor(User::factory()->create());

    expect(fn () => app(LepoCardOnboarding::class)->webKycToken($cardholder))->toThrow(CardProviderException::class);
});

it('starts a SumSub SDK session', function (): void {
    config(['cardissuance.providers.lepo_kyc_method' => 'sumsub_sdk']);
    Http::fake(['lepo.test/api/v2/users/12345/kyc/sumsub-access-token' => Http::response(['success' => true, 'token' => 'sdk-token', 'userId' => '12345'])]);
    $cardholder = lepoCardholderFor(User::factory()->create());

    $start = app(LepoCardOnboarding::class)->startKyc($cardholder);

    expect($start->toArray())->toBe(['method' => 'sumsub_sdk', 'token' => 'sdk-token'])
        ->and($cardholder->fresh()?->kyc_status)->toBe('pending');
    Http::assertSent(fn (Request $request): bool => $request['occupation'] === 'engineer' && $request['documentIssueDate'] === '2020-05-01');
});

it('maps the detailed kyc status', function (array $detail, string $expected): void {
    Http::fake(['lepo.test/api/v2/users/12345/kyc/detailed-status' => Http::response($detail)]);
    $cardholder = lepoCardholderFor(User::factory()->create(), 'in_review');

    $refreshed = app(LepoCardOnboarding::class)->refreshKycStatus($cardholder);

    expect($refreshed->kyc_status)->toBe($expected);
    if ($expected === 'verified') {
        expect($refreshed->verified_at)->not->toBeNull();
    }
})->with([
    'approved by the card issuer' => [['lepoFinanceKycApproved' => true, 'cardIssuerKycApproved' => true, 'interlace' => ['status' => 'approved', 'approved' => true]], 'verified'],
    'rejected'                    => [['cardIssuerKycApproved' => false, 'interlace' => ['status' => 'rejected', 'approved' => false, 'reason' => 'x']], 'rejected'],
    'resubmission required'       => [['cardIssuerKycApproved' => false, 'interlace' => ['status' => 'action_required', 'requiredAction' => 'RESUBMIT']], 'action_required'],
    'sumsub only'                 => [['lepoFinanceKycApproved' => true, 'cardIssuerKycApproved' => false, 'interlace' => ['status' => 'pending']], 'in_review'],
]);

it('opens the funding account once', function (): void {
    Http::fake(['lepo.test/api/v2/users/create-interlace-account/12345' => Http::response(['success' => true, 'interlaceAccountId' => 'ia-1'])]);
    $cardholder = lepoCardholderFor(User::factory()->create(), 'verified');
    $onboarding = app(LepoCardOnboarding::class);

    $first = $onboarding->openFundingAccount($cardholder);
    $second = $onboarding->openFundingAccount($cardholder);

    expect($first->id)->toBe($second->id)
        ->and($first->status)->toBe('active')
        ->and($first->provider_account_id)->toBe('ia-1')
        ->and(CardFundingAccount::query()->count())->toBe(1);
    Http::assertSentCount(1);
});

it('treats an already open funding account as open', function (): void {
    Http::fake(['lepo.test/*' => Http::response(['message' => 'exists', 'code' => 'ACCOUNT_EXISTS'], 409)]);

    $account = app(LepoCardOnboarding::class)->openFundingAccount(lepoCardholderFor(User::factory()->create(), 'verified'));

    expect($account->status)->toBe('active');
});

it('stores only the offered USDC networks', function (): void {
    Http::fake(['lepo.test/api/v2/users/12345/crypto-addresses' => Http::response(['userId' => 12345, 'addresses' => [
        ['address' => '0xarb', 'currency' => 'USDC', 'network' => 'ARBITRUM', 'isActive' => true],
        ['address' => '0xpol', 'currency' => 'USDC', 'chain' => '137', 'isActive' => true],
        ['address' => '0xusdt', 'currency' => 'USDT', 'network' => 'ARBITRUM', 'isActive' => true],
        ['address' => 'Ttron', 'currency' => 'USDC', 'network' => 'TRON', 'isActive' => true],
        ['address' => '0xold', 'currency' => 'USDC.e', 'network' => 'POLYGON', 'isActive' => true],
        ['address' => '0xoff', 'currency' => 'USDC', 'network' => 'BASE', 'isActive' => true],
    ]])]);
    $cardholder = lepoCardholderFor(User::factory()->create(), 'verified');
    $account = CardFundingAccount::create(['user_id' => $cardholder->user_id, 'cardholder_id' => $cardholder->id, 'provider' => 'lepo', 'status' => 'active']);

    $stored = app(LepoCardOnboarding::class)->syncDepositAddresses($account);

    expect($stored)->toBe(2)
        ->and($account->depositAddresses()->orderBy('network')->pluck('address', 'network')->all())->toBe(['arbitrum' => '0xarb', 'polygon' => '0xpol']);
});

it('accepts an already existing funding account answer', function (): void {
    Http::fake(['lepo.test/*' => Http::response(['success' => false, 'accountAlreadyExists' => true, 'interlaceAccountId' => 'ia-9', 'errorCode' => 'ACCOUNT_EXISTS'])]);

    $account = app(LepoCardOnboarding::class)->openFundingAccount(lepoCardholderFor(User::factory()->create(), 'verified'));

    expect($account->status)->toBe('active')
        ->and($account->provider_account_id)->toBe('ia-9');
});

it('keeps one cardholder when two creates race', function (): void {
    $user = User::factory()->create();
    $existing = lepoCardholderFor($user);
    Http::fake([
        'lepo.test/api/v2/users?*' => Http::response(['users' => [['id' => 12345, 'externalUserId' => $user->uuid]]]),
        'lepo.test/api/v2/users'   => Http::response(['message' => 'exists', 'code' => 'USER_EXISTS'], 409),
    ]);

    $cardholder = app(LepoCardOnboarding::class)->createCardholder($user, lepoProfile());

    expect($cardholder->id)->toBe($existing->id)
        ->and(Cardholder::query()->where('user_id', $user->id)->where('provider', 'lepo')->count())->toBe(1);
});

it('registers the account e-mail at Lepo', function (): void {
    Http::fake(['lepo.test/api/v2/users' => Http::response(['id' => 12345], 201)]);
    $user = User::factory()->create(['email' => 'owner@example.com']);

    app(LepoCardOnboarding::class)->createCardholder($user, lepoProfile());

    Http::assertSent(fn (Request $request): bool => $request['email'] === 'owner@example.com');
});

it('reads the network from either address field', function (): void {
    Http::fake(['lepo.test/api/v2/users/12345/crypto-addresses' => Http::response(['userId' => 12345, 'addresses' => [
        ['address' => '0xarb', 'currency' => 'USDC', 'network' => 'Arbitrum One', 'chain' => 'ARB', 'isActive' => true],
    ]])]);
    $cardholder = lepoCardholderFor(User::factory()->create(), 'verified');
    $account = CardFundingAccount::create(['user_id' => $cardholder->user_id, 'cardholder_id' => $cardholder->id, 'provider' => 'lepo', 'status' => 'active']);

    expect(app(LepoCardOnboarding::class)->syncDepositAddresses($account))->toBe(1);
});

it('deactivates addresses that are no longer offered', function (): void {
    Http::fake(['lepo.test/api/v2/users/12345/crypto-addresses' => Http::response(['userId' => 12345, 'addresses' => [
        ['address' => '0xarb', 'currency' => 'USDC', 'network' => 'ARBITRUM', 'isActive' => true],
    ]])]);
    $cardholder = lepoCardholderFor(User::factory()->create(), 'verified');
    $account = CardFundingAccount::create(['user_id' => $cardholder->user_id, 'cardholder_id' => $cardholder->id, 'provider' => 'lepo', 'status' => 'active']);
    $account->depositAddresses()->create(['provider' => 'lepo', 'network' => 'polygon', 'asset' => 'USDC', 'address' => '0xpol', 'is_active' => true]);

    app(LepoCardOnboarding::class)->syncDepositAddresses($account);

    expect($account->depositAddresses()->where('is_active', true)->pluck('network')->all())->toBe(['arbitrum']);
});

it('reports a decision taken at the identity-check stage', function (string $userStatus, string $expected): void {
    Http::fake([
        'lepo.test/api/v2/users/12345/kyc/detailed-status' => Http::response(['lepoFinanceKycApproved' => false, 'cardIssuerKycApproved' => false, 'interlace' => ['status' => 'not_started']]),
        'lepo.test/api/v2/users/12345'                     => Http::response(['id' => 12345, 'kycStatus' => $userStatus]),
    ]);

    $refreshed = app(LepoCardOnboarding::class)->refreshKycStatus(lepoCardholderFor(User::factory()->create(), 'in_review'));

    expect($refreshed->kyc_status)->toBe($expected);
})->with([['REJECTED', 'rejected'], ['RETRY', 'action_required']]);
