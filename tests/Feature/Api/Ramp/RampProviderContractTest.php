<?php

/**
 * Parameterized contract test — every RampProviderInterface implementation
 * must pass every assertion here. Adding a new provider = adding one line
 * to the dataset below.
 */

declare(strict_types=1);

use App\Domain\Ramp\Contracts\RampProviderInterface;
use App\Domain\Ramp\Providers\MockRampProvider;
use App\Domain\Ramp\Providers\OnramperProvider;
use App\Domain\Ramp\Providers\StripeBridgeProvider;

dataset('ramp_providers', [
    'mock'          => fn () => app(MockRampProvider::class),
    'onramper'      => fn () => app(OnramperProvider::class),
    'stripe_bridge' => fn () => app(StripeBridgeProvider::class),
]);

beforeEach(function () {
    config([
        'services.stripe.secret'                => 'sk_test_fake_key',
        'services.stripe.bridge_webhook_secret' => 'whsec_test_fake',
        'ramp.providers.onramper.api_key'       => 'fake_onramper_key',
        'ramp.providers.onramper.secret_key'    => 'fake_onramper_secret',
    ]);
});

it('returns a non-empty provider name', function (RampProviderInterface $provider) {
    $name = $provider->getName();
    expect($name)->toBeString();
    expect($name)->not->toBeEmpty();
})->with('ramp_providers');

it('returns a non-empty webhook signature header name', function (RampProviderInterface $provider) {
    $header = $provider->getWebhookSignatureHeader();
    expect($header)->toBeString();
    expect($header)->not->toBeEmpty();
})->with('ramp_providers');

it('returns supported currencies in the canonical keyed shape', function (RampProviderInterface $provider) {
    $supported = $provider->getSupportedCurrencies();

    expect($supported)
        ->toHaveKeys(['fiatCurrencies', 'cryptoCurrencies', 'modes', 'limits']);

    /** @var list<string> $fiatCurrencies */
    $fiatCurrencies = $supported['fiatCurrencies'];
    expect($fiatCurrencies)->toBeArray();
    expect($fiatCurrencies)->not->toBeEmpty();

    /** @var list<string> $cryptoCurrencies */
    $cryptoCurrencies = $supported['cryptoCurrencies'];
    expect($cryptoCurrencies)->toBeArray();
    expect($cryptoCurrencies)->not->toBeEmpty();

    /** @var array{minAmount: int, maxAmount: int, dailyLimit: int} $limits */
    $limits = $supported['limits'];
    expect($limits)->toHaveKeys(['minAmount', 'maxAmount', 'dailyLimit']);
    expect($limits['minAmount'])->toBeInt();
    expect($limits['maxAmount'])->toBeInt();
    expect($limits['dailyLimit'])->toBeInt();
})->with('ramp_providers');

it('getWebhookValidator returns a callable that takes (rawBody, signatureHeader)', function (RampProviderInterface $provider) {
    $validator = $provider->getWebhookValidator();

    // The return type is already callable, so is_callable() check is redundant
    // but we verify it works by calling it

    // Missing/empty signature must always reject (no matter the provider)
    expect($validator('{}', ''))->toBeFalse();
})->with('ramp_providers');

it('normalizeWebhookPayload returns null for an empty payload', function (RampProviderInterface $provider) {
    expect($provider->normalizeWebhookPayload([]))->toBeNull();
})->with('ramp_providers');

it('normalizeWebhookPayload returns null for garbage payload', function (RampProviderInterface $provider) {
    expect($provider->normalizeWebhookPayload(['unrelated' => 'junk']))->toBeNull();
})->with('ramp_providers');
