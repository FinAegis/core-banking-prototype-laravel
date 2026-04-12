<?php

declare(strict_types=1);

use App\Domain\Ramp\Services\StripeBridgeService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'services.stripe.secret'                => 'sk_test_fake_key',
        'services.stripe.bridge_webhook_secret' => 'whsec_test_fake',
    ]);
});

it('fetches a Stripe onramp session via getSession()', function () {
    Http::fake([
        'api.stripe.com/v1/crypto/onramp_sessions/cos_test_abc123' => Http::response([
            'id'                 => 'cos_test_abc123',
            'status'             => 'fulfilled',
            'source_amount'      => '100.00',
            'destination_amount' => '98.50000000',
            'destination_currency' => 'usdc',
        ], 200),
    ]);

    $service = new StripeBridgeService();
    $result = $service->getSession('cos_test_abc123');

    expect($result)
        ->toHaveKeys(['status', 'destination_amount', 'raw'])
        ->and($result['status'])->toBe('fulfilled')
        ->and($result['destination_amount'])->toBe('98.50000000');
});

it('throws RuntimeException when Stripe returns 404 for getSession()', function () {
    Http::fake([
        'api.stripe.com/v1/crypto/onramp_sessions/cos_missing' => Http::response([
            'error' => ['message' => 'No such session', 'type' => 'invalid_request_error'],
        ], 404),
    ]);

    $service = new StripeBridgeService();
    $service->getSession('cos_missing');
})->throws(RuntimeException::class);
