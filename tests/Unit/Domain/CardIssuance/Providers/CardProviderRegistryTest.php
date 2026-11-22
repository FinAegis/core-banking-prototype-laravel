<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Providers\CardholderProfile;
use App\Domain\CardIssuance\Providers\CardProviderException;
use App\Domain\CardIssuance\Providers\CardProviderRegistry;
use App\Domain\CardIssuance\Providers\Contracts\CardOnboardingProvider;
use App\Domain\CardIssuance\Providers\KycStart;
use Tests\TestCase;

uses(TestCase::class);

it('resolves the lepo onboarding provider', function (): void {
    $provider = app(CardProviderRegistry::class)->onboarding('lepo');

    expect($provider)->toBeInstanceOf(CardOnboardingProvider::class)
        ->and($provider->name())->toBe('lepo');
});

it('refuses an unknown provider', function (): void {
    expect(fn () => app(CardProviderRegistry::class)->onboarding('acme'))->toThrow(CardProviderException::class);
});

it('presents a kyc start without empty fields', function (): void {
    expect((new KycStart('sumsub_sdk', 'tok', null, null))->toArray())->toBe(['method' => 'sumsub_sdk', 'token' => 'tok']);
});

it('keeps the questionnaire answers under the provider names', function (): void {
    $profile = new CardholderProfile(
        firstName: 'Ada',
        lastName: 'Lovelace',
        email: 'ada@example.com',
        phone: '+37060000000',
        dateOfBirth: '1990-01-01',
        addressLine1: 'Gedimino 1',
        addressLine2: null,
        city: 'Vilnius',
        state: null,
        postalCode: '01103',
        country: 'LT',
        nationality: 'LT',
        occupation: 'engineer',
        annualSalary: '30000-60000',
        accountPurpose: 'spending',
        expectedMonthlyVolume: '1000-5000',
        documentIssueDate: '2020-05-01',
    );

    expect($profile->questionnaire())->toBe([
        'occupation'            => 'engineer',
        'annualSalary'          => '30000-60000',
        'accountPurpose'        => 'spending',
        'expectedMonthlyVolume' => '1000-5000',
        'documentIssueDate'     => '2020-05-01',
    ]);
});
