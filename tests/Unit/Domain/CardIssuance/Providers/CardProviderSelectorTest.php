<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Providers\CardProviderSelector;
use App\Models\User;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function (): void {
    config([
        'cardissuance.providers.enabled'          => 'lepo,fincard',
        'cardissuance.providers.default'          => 'lepo',
        'cardissuance.providers.lepo_pilot_only'  => true,
        'cardissuance.providers.lepo_pilot_users' => '',
    ]);
});

function selectorCardholder(User $user, string $provider): void
{
    $cardholder = new Cardholder();
    $cardholder->forceFill(['user_id' => $user->id, 'first_name' => 'A', 'last_name' => 'B', 'kyc_status' => 'pending', 'provider' => $provider]);
    $cardholder->save();
}

it('gives eligible users the default provider', function (): void {
    $user = User::factory()->create();
    config(['cardissuance.providers.lepo_pilot_users' => $user->uuid]);

    expect(app(CardProviderSelector::class)->providerFor($user))->toBe('lepo');
});

it('returns null outside the pilot', function (): void {
    $user = User::factory()->create();

    expect(app(CardProviderSelector::class)->providerFor($user))->toBeNull();
});

it('gives every user the default provider once the pilot is over', function (): void {
    config(['cardissuance.providers.lepo_pilot_only' => false]);

    expect(app(CardProviderSelector::class)->providerFor(User::factory()->create()))->toBe('lepo');
});

it('matches pilot e-mails case-insensitively', function (): void {
    $user = User::factory()->create(['email' => 'Pilot.Person@Example.com']);
    config(['cardissuance.providers.lepo_pilot_users' => 'someone@example.com, pilot.person@example.com']);

    expect(app(CardProviderSelector::class)->providerFor($user))->toBe('lepo');
});

it('keeps the stored provider when the default changes', function (): void {
    $user = User::factory()->create();
    selectorCardholder($user, 'fincard');

    expect(app(CardProviderSelector::class)->providerFor($user))->toBe('fincard');
});

it('returns null when the default provider is not enabled', function (): void {
    config(['cardissuance.providers.enabled' => 'fincard', 'cardissuance.providers.lepo_pilot_only' => false]);

    expect(app(CardProviderSelector::class)->providerFor(User::factory()->create()))->toBeNull();
});
