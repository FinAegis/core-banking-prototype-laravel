<?php

declare(strict_types=1);

/*
 * The Zelta legal pages describe the virtual card pilot and name the
 * operator with its registry details. The FinAegis demo site does not
 * offer cards, so it never shows the card text.
 */

describe('Zelta legal pages', function (): void {
    beforeEach(function (): void {
        config([
            'brand.name'               => 'zelta',
            'brand.show_promo_pages'   => false,
            'brand.legal_entity'       => 'Aegis brightsmark capital, MB',
            'brand.legal_company_code' => '307576279',
            'brand.legal_vat_code'     => 'LT100020614313',
            'brand.legal_address'      => 'Sembų g. 6, Buivydiškės, LT-14166 Vilnius District Municipality, Lithuania',
        ]);
    });

    it('describes the virtual card in the Terms', function (): void {
        $response = $this->get('/legal/terms');
        $response->assertOk();
        $response->assertSee('4.4 Virtual Cards');
        $response->assertSee('The card is issued and run by a licensed card-issuing partner');
        $response->assertSee('It is not available to citizens or residents of the United States.');
        $response->assertSee('Send at least the minimum amount shown in the app. A smaller amount is not loaded to your card automatically.');
        $response->assertSee('A card that still holds money cannot be cancelled in the app.');
        $response->assertSee('Never share this code with anyone.');
    });

    it('says in the Privacy Policy what card data goes to the card partner', function (): void {
        $response = $this->get('/legal/privacy');
        $response->assertOk();
        $response->assertSee('Card application details');
        $response->assertSee('Our licensed card-issuing partner');
        $response->assertSee('We do not receive or store your full card number or security code.');
    });

    it('names the operator with its registry details on both pages', function (string $path): void {
        $response = $this->get($path);
        $response->assertOk();
        $response->assertSee('Aegis brightsmark capital, MB');
        $response->assertSee('Company code: 307576279');
        $response->assertSee('VAT code: LT100020614313');
        $response->assertSee('Sembų g. 6, Buivydiškės, LT-14166 Vilnius District Municipality, Lithuania');
    })->with(['terms' => '/legal/terms', 'privacy' => '/legal/privacy']);

    it('falls back to the jurisdiction when no address is set', function (): void {
        config(['brand.legal_address' => '', 'brand.legal_company_code' => '', 'brand.legal_jurisdiction' => 'Lithuania']);
        $response = $this->get('/legal/terms');
        $response->assertOk();
        $response->assertSee('Address: Lithuania', false);
        $response->assertDontSee('Company code:');
    });
});

describe('FinAegis demo legal pages', function (): void {
    beforeEach(function (): void {
        config([
            'brand.name'             => 'FinAegis',
            'brand.show_promo_pages' => true,
        ]);
    });

    it('shows no card text', function (string $path): void {
        $response = $this->get($path);
        $response->assertOk();
        $response->assertDontSee('Virtual Cards');
        $response->assertDontSee('card-issuing partner');
    })->with(['terms' => '/legal/terms', 'privacy' => '/legal/privacy']);
});
