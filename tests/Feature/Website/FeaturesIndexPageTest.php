<?php

declare(strict_types=1);

use function Pest\Laravel\get;

describe('Features Index Page', function (): void {
    it('renders the features index page', function (): void {
        $response = get('/features');

        $response->assertOk();
        $response->assertSee('Every Banking Capability.');
    });

    it('lists AI Framework feature card', function (): void {
        $response = get('/features');

        $response->assertOk();
        $response->assertSee('AI Framework');
        $response->assertSee(count((array) config('mcp.tools')) . ' Tools');
    });

    it('lists Agent Protocol feature card', function (): void {
        $response = get('/features');

        $response->assertOk();
        $response->assertSee('Agent Protocol (AP2)');
    });

    it('lists Machine Payments feature card', function (): void {
        $response = get('/features');

        $response->assertOk();
        $response->assertSee('Machine Payments');
    });

    it('lists x402 Protocol feature card', function (): void {
        $response = get('/features');

        $response->assertOk();
        $response->assertSee('x402 Protocol');
    });

    it('returns 404 for invalid feature', function (): void {
        $response = get('/features/nonexistent-feature');

        $response->assertNotFound();
    });
});
