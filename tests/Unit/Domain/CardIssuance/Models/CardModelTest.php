<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\Cardholder;

uses(Tests\TestCase::class);

it('creates a Card model with fillable attributes', function (): void {
    $card = new Card([
        'user_id'           => 'user-123',
        'cardholder_id'     => 'ch-456',
        'issuer_card_token' => 'card_rain_789',
        'issuer'            => 'rain',
        'last4'             => '4242',
        'network'           => 'visa',
        'status'            => 'active',
        'currency'          => 'USD',
        'label'             => 'Travel Card',
        'spend_limit_cents' => 500000,
    ]);

    expect($card->user_id)->toBe('user-123');
    expect($card->issuer)->toBe('rain');
    expect($card->last4)->toBe('4242');
    expect($card->status)->toBe('active');
    expect($card->label)->toBe('Travel Card');
    expect($card->spend_limit_cents)->toBe(500000);
});

it('checks active and frozen states', function (): void {
    $card = new Card(['status' => 'active']);
    expect($card->isActive())->toBeTrue();
    expect($card->isFrozen())->toBeFalse();

    $card->status = 'frozen';
    expect($card->isActive())->toBeFalse();
    expect($card->isFrozen())->toBeTrue();
});

it('returns masked card number', function (): void {
    $card = new Card(['last4' => '5678']);
    expect($card->getMaskedNumber())->toBe('**** **** **** 5678');
});

it('creates a Cardholder model with fillable attributes', function (): void {
    $holder = new Cardholder([
        'user_id'    => 'user-123',
        'first_name' => 'John',
        'last_name'  => 'Doe',
        'email'      => 'john@example.com',
        'phone'      => '+1234567890',
        'kyc_status' => 'verified',
    ]);

    expect($holder->first_name)->toBe('John');
    expect($holder->getFullName())->toBe('John Doe');
});

it('checks verified status', function (): void {
    $holder = new Cardholder([
        'kyc_status' => 'pending',
    ]);
    expect($holder->isVerified())->toBeFalse();

    $holder->kyc_status = 'verified';
    $holder->verified_at = now();
    expect($holder->isVerified())->toBeTrue();
});

it('builds shipping address string', function (): void {
    $holder = new Cardholder([
        'shipping_address_line1' => '123 Main St',
        'shipping_city'          => 'New York',
        'shipping_state'         => 'NY',
        'shipping_postal_code'   => '10001',
        'shipping_country'       => 'US',
    ]);

    expect($holder->getShippingAddress())->toBe('123 Main St, New York, NY, 10001, US');
});
