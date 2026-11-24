<?php

declare(strict_types=1);

/*
 * The account deletion page is linked from the Google Play listing. On
 * zelta.app it names the operating company as the developer, describes the
 * steps the app really has (Zelta users have no password) and says what
 * account deletion deletes and keeps. The FinAegis demo site keeps FinAegis
 * as the developer and shows no card text.
 */

describe('Zelta account deletion page', function (): void {
    beforeEach(function (): void {
        config([
            'brand.name'             => 'Zelta',
            'brand.show_promo_pages' => false,
            'brand.legal_entity'     => 'Aegis brightsmark capital, MB',
        ]);
    });

    it('names the operating company as the developer', function (): void {
        $response = $this->get('/legal/delete-account');
        $response->assertOk();
        $response->assertSee('Its developer is <strong>Aegis brightsmark capital, MB</strong>.', false);
        $response->assertDontSee('Developer: <strong>FinAegis</strong>', false);
    });

    it('describes the steps the app has, without a password', function (): void {
        $response = $this->get('/legal/delete-account');
        $response->assertOk();
        $response->assertSee('Open the <strong>Profile</strong> tab.', false);
        $response->assertSee('Scroll to the bottom. Below <strong>Sign Out</strong>, tap <strong>Delete Account</strong>.', false);
        $response->assertDontSee('Auto-Lock');
        $response->assertSee('After the deletion, you cannot open this wallet in the app again.');
        $response->assertSee('The deletion cannot be undone.');
        $response->assertDontSee('password');
        $response->assertDontSee('Password');
    });

    it('describes what is deleted and kept without the removed shard model', function (): void {
        $response = $this->get('/legal/delete-account');
        $response->assertOk();
        $response->assertDontSee('shard');
        $response->assertDontSee('Shard');
        $response->assertSee('We replace your name and email address with anonymous values.');
        $response->assertSee('We keep the records of money you moved with Zelta.');
        $response->assertSee('we stop watching them for new transactions.');
        $response->assertSee('We keep a coded form of your sign-in identifier');
        $response->assertSee('If you applied for a card, we erase the details you gave for it.');
    });
});

describe('FinAegis demo account deletion page', function (): void {
    beforeEach(function (): void {
        config([
            'brand.name'             => 'FinAegis',
            'brand.show_promo_pages' => true,
            'brand.legal_entity'     => 'FinAegis',
        ]);
    });

    it('keeps FinAegis as the developer and shows no card text', function (): void {
        $response = $this->get('/legal/delete-account');
        $response->assertOk();
        $response->assertSee('Its developer is <strong>FinAegis</strong>.', false);
        $response->assertDontSee('applied for a card');
    });
});
