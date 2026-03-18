<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Adapters\RainCardIssuerAdapter;
use App\Domain\CardIssuance\Enums\CardNetwork;
use App\Domain\CardIssuance\Enums\CardStatus;
use App\Domain\CardIssuance\Enums\WalletType;
use Illuminate\Support\Facades\Http;

uses(Tests\TestCase::class);

function makeRainAdapter(): RainCardIssuerAdapter
{
    return new RainCardIssuerAdapter([
        'base_url'   => 'https://api.raincards.xyz/v1',
        'api_key'    => 'test-api-key',
        'program_id' => 'prog_test123',
    ]);
}

it('returns rain as the issuer name', function (): void {
    expect(makeRainAdapter()->getName())->toBe('rain');
});

it('throws when missing required config', function (): void {
    new RainCardIssuerAdapter(['base_url' => 'https://api.test']);
})->throws(RuntimeException::class, 'api_key and program_id');

it('creates a card via Rain API', function (): void {
    Http::fake([
        'api.raincards.xyz/v1/cards' => Http::response([
            'data' => [
                'id'        => 'card_rain_123',
                'last4'     => '4242',
                'network'   => 'visa',
                'status'    => 'active',
                'exp_month' => '12',
                'exp_year'  => '2028',
            ],
        ], 201),
    ]);

    $adapter = makeRainAdapter();
    $card = $adapter->createCard('user-1', 'John Doe', [], CardNetwork::VISA, 'My Card');

    expect($card->cardToken)->toBe('card_rain_123');
    expect($card->last4)->toBe('4242');
    expect($card->network)->toBe(CardNetwork::VISA);
    expect($card->status)->toBe(CardStatus::ACTIVE);
    expect($card->label)->toBe('My Card');
    expect($card->metadata)->toHaveKey('rain_card_id');
});

it('freezes a card via PATCH', function (): void {
    Http::fake([
        'api.raincards.xyz/v1/cards/card_123' => Http::response(null, 200),
    ]);

    expect(makeRainAdapter()->freezeCard('card_123'))->toBeTrue();
});

it('unfreezes a card via PATCH', function (): void {
    Http::fake([
        'api.raincards.xyz/v1/cards/card_123' => Http::response(null, 200),
    ]);

    expect(makeRainAdapter()->unfreezeCard('card_123'))->toBeTrue();
});

it('cancels a card via POST', function (): void {
    Http::fake([
        'api.raincards.xyz/v1/cards/card_123/cancel' => Http::response(null, 200),
    ]);

    expect(makeRainAdapter()->cancelCard('card_123', 'Lost card'))->toBeTrue();
});

it('retrieves a card by token', function (): void {
    Http::fake([
        'api.raincards.xyz/v1/cards/card_456' => Http::response([
            'data' => [
                'id'              => 'card_456',
                'last4'           => '9876',
                'network'         => 'mastercard',
                'status'          => 'frozen',
                'cardholder_name' => 'Jane Smith',
                'expiration_date' => '2029-06-01',
            ],
        ]),
    ]);

    $card = makeRainAdapter()->getCard('card_456');

    expect($card)->not->toBeNull();
    expect($card->last4)->toBe('9876');
    expect($card->network)->toBe(CardNetwork::MASTERCARD);
    expect($card->status)->toBe(CardStatus::FROZEN);
});

it('returns null for non-existent card', function (): void {
    Http::fake([
        'api.raincards.xyz/v1/cards/card_missing' => Http::response(null, 404),
    ]);

    expect(makeRainAdapter()->getCard('card_missing'))->toBeNull();
});

it('lists user cards', function (): void {
    Http::fake([
        'api.raincards.xyz/v1/cards*' => Http::response([
            'data' => [
                ['id' => 'c1', 'last4' => '1111', 'status' => 'active', 'network' => 'visa'],
                ['id' => 'c2', 'last4' => '2222', 'status' => 'frozen', 'network' => 'visa'],
                ['id' => 'c3', 'last4' => '3333', 'status' => 'cancelled', 'network' => 'visa'],
            ],
        ]),
    ]);

    $cards = makeRainAdapter()->listUserCards('user-1');

    // Cancelled cards are excluded
    expect($cards)->toHaveCount(2);
    expect($cards[0]->last4)->toBe('1111');
    expect($cards[1]->last4)->toBe('2222');
});

it('fetches transactions with pagination', function (): void {
    Http::fake([
        'api.raincards.xyz/v1/transactions*' => Http::response([
            'data' => [
                [
                    'id'                     => 'tx_1',
                    'merchant_name'          => 'Coffee Shop',
                    'merchant_category_code' => '5812',
                    'amount'                 => 450,
                    'currency'               => 'USD',
                    'status'                 => 'settled',
                    'created_at'             => '2026-03-15T10:00:00Z',
                ],
            ],
            'has_more' => true,
        ]),
    ]);

    $result = makeRainAdapter()->getTransactions('card_123', 10);

    expect($result['transactions'])->toHaveCount(1);
    expect($result['transactions'][0]->merchantName)->toBe('Coffee Shop');
    expect($result['transactions'][0]->amountCents)->toBe(450);
    expect($result['next_cursor'])->toBe('tx_1');
});

it('requests provisioning data for Apple Pay', function (): void {
    Http::fake([
        'api.raincards.xyz/v1/cards/card_789/provision' => Http::response([
            'data' => [
                'encrypted_pass_data'  => 'enc_data_here',
                'activation_data'      => 'act_data',
                'ephemeral_public_key' => 'eph_key',
                'certificate_chain'    => ['cert1', 'cert2'],
            ],
        ]),
    ]);

    $provisioning = makeRainAdapter()->getProvisioningData(
        'card_789',
        WalletType::APPLE_PAY,
        'device_001',
    );

    expect($provisioning->cardId)->toBe('card_789');
    expect($provisioning->walletType)->toBe(WalletType::APPLE_PAY);
    expect($provisioning->encryptedPassData)->toBe('enc_data_here');
});

it('maps active Rain status', function (): void {
    Http::fake(['api.raincards.xyz/*' => Http::response(['data' => ['id' => 'c', 'last4' => '0000', 'status' => 'active']])]);
    expect(makeRainAdapter()->getCard('c')->status)->toBe(CardStatus::ACTIVE);
});

it('maps frozen Rain status', function (): void {
    Http::fake(['api.raincards.xyz/*' => Http::response(['data' => ['id' => 'c', 'last4' => '0000', 'status' => 'frozen']])]);
    expect(makeRainAdapter()->getCard('c')->status)->toBe(CardStatus::FROZEN);
});

it('maps cancelled Rain status', function (): void {
    Http::fake(['api.raincards.xyz/*' => Http::response(['data' => ['id' => 'c', 'last4' => '0000', 'status' => 'cancelled']])]);
    expect(makeRainAdapter()->getCard('c')->status)->toBe(CardStatus::CANCELLED);
});

it('maps expired Rain status', function (): void {
    Http::fake(['api.raincards.xyz/*' => Http::response(['data' => ['id' => 'c', 'last4' => '0000', 'status' => 'expired']])]);
    expect(makeRainAdapter()->getCard('c')->status)->toBe(CardStatus::EXPIRED);
});

it('maps unknown Rain status to pending', function (): void {
    Http::fake(['api.raincards.xyz/*' => Http::response(['data' => ['id' => 'c', 'last4' => '0000', 'status' => 'unknown']])]);
    expect(makeRainAdapter()->getCard('c')->status)->toBe(CardStatus::PENDING);
});
