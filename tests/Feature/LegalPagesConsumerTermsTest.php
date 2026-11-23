<?php

declare(strict_types=1);

/*
 * The Zelta Terms follow EU consumer law: Lithuanian courts and the
 * consumer's own courts instead of binding arbitration and a class action
 * waiver, no termination "for any reason", and no limit on liability that
 * the law does not allow. The Privacy Policy names a data protection
 * contact (no Data Protection Officer is appointed) and the right to
 * complain to a supervisory authority.
 */

describe('Zelta Terms and consumer law', function (): void {
    beforeEach(function (): void {
        config(['brand.name' => 'zelta', 'brand.show_promo_pages' => false]);
    });

    it('says how a card top-up works without Zelta holding or converting the money', function (): void {
        $response = $this->get('/legal/terms');
        $response->assertOk();
        $response->assertSee('On your instruction, which the app passes to the partner, the partner converts it to US dollars and loads your card.');
        $response->assertSee('Zelta does not take possession of the money at any point and does not carry out the conversion.');
    });

    it('sends disputes to Lithuanian courts and keeps the consumer rights', function (): void {
        $response = $this->get('/legal/terms');
        $response->assertOk();
        $response->assertSee('These Terms are governed by the laws of the Republic of Lithuania.');
        $response->assertSee('State Consumer Rights Protection Authority');
        $response->assertSee('We reply within 14 days.');
        $response->assertDontSee('Binding Arbitration');
        $response->assertDontSee('Class Action Waiver');
    });

    it('does not let us close an account for any reason', function (): void {
        $response = $this->get('/legal/terms');
        $response->assertOk();
        $response->assertDontSee('for any reason whatsoever');
        $response->assertSee('In other cases we give you at least 30 days&rsquo; notice.', false);
    });

    it('gives notice of material changes and lets the user leave', function (): void {
        $response = $this->get('/legal/terms');
        $response->assertOk();
        $response->assertSee('We give at least 30 days&rsquo; notice of a material change.', false);
        $response->assertDontSee('we will try to provide');
    });

    it('keeps the liability the law does not allow us to limit', function (): void {
        $response = $this->get('/legal/terms');
        $response->assertOk();
        $response->assertSee('Nothing in these Terms limits or excludes liability');
    });
});

describe('Zelta Privacy Policy contacts', function (): void {
    beforeEach(function (): void {
        config(['brand.name' => 'zelta', 'brand.show_promo_pages' => false]);
    });

    it('names a data protection contact, not an officer that does not exist', function (): void {
        $response = $this->get('/legal/privacy');
        $response->assertOk();
        $response->assertSee('Data protection contact');
        $response->assertSee('We have not appointed a Data Protection Officer.');
        $response->assertDontSee('contact our Data Protection Officer');
    });

    it('tells the user they can complain to the supervisory authority', function (): void {
        $response = $this->get('/legal/privacy');
        $response->assertOk();
        $response->assertSee('State Data Protection Inspectorate');
    });
});
