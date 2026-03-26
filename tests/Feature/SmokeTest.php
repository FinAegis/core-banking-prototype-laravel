<?php

declare(strict_types=1);

describe('Smoke Tests — Critical Pages', function (): void {
    it('loads the homepage', function (): void {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Zelta');
    });

    it('loads the features index', function (): void {
        $response = $this->get('/features');

        $response->assertOk();
        $response->assertSee('Domain Modules');
    });

    it('loads the zelta cli feature page', function (): void {
        $response = $this->get('/features/zelta-cli');

        $response->assertOk();
        $response->assertSee('Zelta');
        $response->assertSee('CLI');
    });

    it('loads the developers portal', function (): void {
        $response = $this->get('/developers');

        $response->assertOk();
    });
});

describe('Smoke Tests — API Endpoints', function (): void {
    it('returns x402 protocol status', function (): void {
        $response = $this->getJson('/api/v1/x402/status');

        $response->assertOk();
        $response->assertJsonFragment(['protocol' => 'x402']);
    });

    it('returns x402 well-known configuration', function (): void {
        $response = $this->getJson('/api/.well-known/x402-configuration');

        $response->assertOk();
        $response->assertJsonFragment(['x402_version' => 2]);
    });

    it('returns mpp well-known configuration', function (): void {
        $response = $this->getJson('/api/.well-known/mpp-configuration');

        $response->assertOk();
        $response->assertJsonStructure(['mpp_version', 'issuer', 'supported_rails']);
    });
});
