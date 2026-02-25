<?php

declare(strict_types=1);

use App\Domain\Custodian\Connectors\FlutterwaveConnector;

uses(Tests\TestCase::class);

beforeEach(function () {
    $this->connector = new FlutterwaveConnector([
        'name'           => 'Flutterwave',
        'base_url'       => 'https://api.flutterwave.com/v3',
        'secret_key'     => 'FLWSECK_TEST-sandbox',
        'public_key'     => 'FLWPUBK_TEST-sandbox',
        'encryption_key' => 'FLWENCKEY_TEST-sandbox',
        'timeout'        => 30,
    ]);
});

describe('FlutterwaveConnector', function () {
    it('can be instantiated with config', function () {
        expect($this->connector)->toBeInstanceOf(FlutterwaveConnector::class);
        expect($this->connector->getName())->toBe('Flutterwave');
    });

    it('returns supported African and international assets', function () {
        $assets = $this->connector->getSupportedAssets();

        expect($assets)->toContain('NGN');
        expect($assets)->toContain('GHS');
        expect($assets)->toContain('KES');
        expect($assets)->toContain('ZAR');
        expect($assets)->toContain('XOF');
        expect($assets)->toContain('XAF');
        expect($assets)->toContain('TZS');
        expect($assets)->toContain('UGX');
        expect($assets)->toContain('USD');
        expect($assets)->toContain('EUR');
        expect($assets)->toContain('GBP');
    });

    it('cancellation always returns false', function () {
        expect($this->connector->cancelTransaction('flw_12345'))->toBeFalse();
    });

    it('extends BaseCustodianConnector', function () {
        $reflection = new ReflectionClass($this->connector);
        $parent = $reflection->getParentClass();
        assert($parent !== false);
        expect($parent->getName())
            ->toBe('App\Domain\Custodian\Connectors\BaseCustodianConnector');
    });

    it('implements ICustodianConnector interface', function () {
        $reflection = new ReflectionClass($this->connector);
        $interfaces = $reflection->getInterfaceNames();
        expect($interfaces)->toContain('App\Domain\Custodian\Contracts\ICustodianConnector');
    });

    it('has bearer token authorization in headers', function () {
        $reflection = new ReflectionClass($this->connector);
        $method = $reflection->getMethod('getHeaders');
        $method->setAccessible(true);
        $headers = $method->invoke($this->connector);

        expect($headers['Authorization'])->toBe('Bearer FLWSECK_TEST-sandbox');
        expect($headers['Content-Type'])->toBe('application/json');
        expect($headers['Accept'])->toBe('application/json');
    });
});
