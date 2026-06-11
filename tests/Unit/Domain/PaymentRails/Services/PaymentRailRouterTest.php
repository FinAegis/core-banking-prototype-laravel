<?php

declare(strict_types=1);

use App\Domain\Banking\Services\IntelligentRoutingService;
use App\Domain\PaymentRails\Services\AchService;
use App\Domain\PaymentRails\Services\FedNowService;
use App\Domain\PaymentRails\Services\FedwireService;
use App\Domain\PaymentRails\Services\NachaFileGenerator;
use App\Domain\PaymentRails\Services\NachaFileParser;
use App\Domain\PaymentRails\Services\PaymentRailRouter;
use App\Domain\PaymentRails\Services\RtpService;

uses(Tests\TestCase::class);

// ── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Build a PaymentRailRouter with concrete (but never-called) dependencies.
 * getSupportedRails() and getTransactionStatus() do not invoke any injected
 * service, so stub values are sufficient for the behavioural tests below.
 */
function makeRouter(): PaymentRailRouter
{
    return new PaymentRailRouter(
        routing: new IntelligentRoutingService(),
        ach: new AchService(new NachaFileGenerator(), new NachaFileParser()),
        fedwire: new FedwireService(),
        rtp: new RtpService(),
        fednow: new FedNowService(),
    );
}

// ── Structural / reflection tests ────────────────────────────────────────────

it('PaymentRailRouter class exists', function (): void {
    expect(class_exists(PaymentRailRouter::class))->toBeTrue();
});

it('PaymentRailRouter has route method', function (): void {
    $reflection = new ReflectionClass(PaymentRailRouter::class);
    expect($reflection->hasMethod('route'))->toBeTrue();
});

it('route method has correct parameter count', function (): void {
    $reflection = new ReflectionClass(PaymentRailRouter::class);
    $method = $reflection->getMethod('route');

    // userId, amount, currency, country, urgency, beneficiary
    expect($method->getNumberOfParameters())->toBe(6);
    expect($method->getNumberOfRequiredParameters())->toBe(6);
});

it('PaymentRailRouter has getSupportedRails method', function (): void {
    $reflection = new ReflectionClass(PaymentRailRouter::class);
    expect($reflection->hasMethod('getSupportedRails'))->toBeTrue();
});

it('getSupportedRails accepts one parameter', function (): void {
    $reflection = new ReflectionClass(PaymentRailRouter::class);
    $method = $reflection->getMethod('getSupportedRails');

    expect($method->getNumberOfParameters())->toBe(1);
    expect($method->getNumberOfRequiredParameters())->toBe(1);
});

it('PaymentRailRouter has getTransactionStatus method', function (): void {
    $reflection = new ReflectionClass(PaymentRailRouter::class);
    expect($reflection->hasMethod('getTransactionStatus'))->toBeTrue();
});

it('getTransactionStatus accepts one parameter', function (): void {
    $reflection = new ReflectionClass(PaymentRailRouter::class);
    $method = $reflection->getMethod('getTransactionStatus');

    expect($method->getNumberOfParameters())->toBe(1);
    expect($method->getNumberOfRequiredParameters())->toBe(1);
});

// ── Behavioural tests ────────────────────────────────────────────────────────

it('getSupportedRails returns ach, fedwire, rtp, fednow for US', function (): void {
    $rails = makeRouter()->getSupportedRails('US');

    expect($rails)->toContain('ach');
    expect($rails)->toContain('fedwire');
    expect($rails)->toContain('rtp');
    expect($rails)->toContain('fednow');
});

it('getSupportedRails returns sepa and sepa_instant for DE', function (): void {
    $rails = makeRouter()->getSupportedRails('DE');

    expect($rails)->toContain('sepa');
    expect($rails)->toContain('sepa_instant');
});

it('getSupportedRails returns swift for non-US non-EU countries', function (): void {
    $rails = makeRouter()->getSupportedRails('JP');

    expect($rails)->toContain('swift');
});

it('getSupportedRails is case-insensitive', function (): void {
    $router = makeRouter();

    expect($router->getSupportedRails('us'))->toContain('ach');
    expect($router->getSupportedRails('de'))->toContain('sepa');
    expect($router->getSupportedRails('jp'))->toContain('swift');
});

it('getSupportedRails returns sepa and sepa_instant for other EU countries', function (): void {
    $router = makeRouter();

    foreach (['FR', 'IT', 'ES', 'NL', 'BE'] as $country) {
        $rails = $router->getSupportedRails($country);
        expect(in_array('sepa', $rails, true))->toBeTrue("Expected sepa for {$country}");
        expect(in_array('sepa_instant', $rails, true))->toBeTrue("Expected sepa_instant for {$country}");
    }
});

it('getTransactionStatus returns null for a non-existent transaction', function (): void {
    $result = makeRouter()->getTransactionStatus('00000000-0000-0000-0000-000000000000');

    expect($result)->toBeNull();
});
