<?php

declare(strict_types=1);

/*
 * Regulatory status disclaimers (copy kit: docs/REGULATORY-CLAIMS.md).
 *
 * zelta.app (production, SHOW_PROMO_PAGES=false) must render the Zelta
 * disclaimer (Z1) and never the FinAegis software disclaimer (F1);
 * finaegis.org (demo, SHOW_PROMO_PAGES=true) must render F1.
 *
 * In the testing environment "/" serves the marketing welcome page, so the
 * Zelta landing is exercised through "/app", which renders the same view
 * that "/" serves on production.
 */

describe('zelta.app (promo pages off, Zelta brand)', function (): void {
    beforeEach(function (): void {
        config([
            'brand.name'             => 'zelta',
            'brand.show_promo_pages' => false,
        ]);
    });

    it('shows Z1 and not F1 on the Zelta landing, Terms and Connect pages', function (string $path): void {
        $response = $this->get($path);

        $response->assertOk();
        $response->assertSee('Zelta is non-custodial wallet software');
        $response->assertDontSee('FinAegis is open-source software. Using it does not make a deployment compliant');
    })->with([
        'landing' => '/app',
        'terms'   => '/legal/terms',
        'connect' => '/connect',
    ]);
});

describe('finaegis.org (promo pages on, FinAegis brand)', function (): void {
    beforeEach(function (): void {
        config([
            'brand.name'             => 'FinAegis',
            'brand.show_promo_pages' => true,
        ]);
    });

    it('shows F1 on the Terms and Connect pages', function (string $path): void {
        $response = $this->get($path);

        $response->assertOk();
        $response->assertSee('FinAegis is open-source software. Using it does not make a deployment compliant');
    })->with([
        'terms'   => '/legal/terms',
        'connect' => '/connect',
    ]);
});
