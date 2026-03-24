<?php

declare(strict_types=1);

use function Pest\Laravel\get;

describe('Agent Protocol Feature Page', function (): void {
    it('renders the agent protocol page', function (): void {
        $response = get('/features/agent-protocol');

        $response->assertOk();
        $response->assertSee('Agent Protocol');
    });

    it('displays DID authentication section', function (): void {
        $response = get('/features/agent-protocol');

        $response->assertOk();
        $response->assertSee('DID Identity');
        $response->assertSee('DID Challenge-Response');
    });

    it('displays A2A messaging section', function (): void {
        $response = get('/features/agent-protocol');

        $response->assertOk();
        $response->assertSee('A2A Messaging');
    });

    it('displays escrow section', function (): void {
        $response = get('/features/agent-protocol');

        $response->assertOk();
        $response->assertSee('Escrow');
        $response->assertSee('Dispute Resolution');
    });

    it('displays reputation system', function (): void {
        $response = get('/features/agent-protocol');

        $response->assertOk();
        $response->assertSee('Reputation');
        $response->assertSee('50/100');
    });

    it('displays AP2 mandate types', function (): void {
        $response = get('/features/agent-protocol');

        $response->assertOk();
        $response->assertSee('Cart Mandate');
        $response->assertSee('Intent Mandate');
        $response->assertSee('Payment Mandate');
    });

    it('displays KYC tiers', function (): void {
        $response = get('/features/agent-protocol');

        $response->assertOk();
        $response->assertSee('Basic');
        $response->assertSee('Enhanced');
        $response->assertSee('Full');
    });

    it('displays event-sourced aggregates', function (): void {
        $response = get('/features/agent-protocol');

        $response->assertOk();
        $response->assertSee('Agent Identity');
        $response->assertSee('Agent Wallet');
        $response->assertSee('Mandates');
    });

    it('includes SEO meta tags', function (): void {
        $response = get('/features/agent-protocol');

        $response->assertOk();
        $response->assertSee('Autonomous Agent Commerce', false);
    });
});
