<?php

declare(strict_types=1);

use App\Domain\Banking\Services\BankTransferService;
use Mockery\MockInterface;

// ---------------------------------------------------------------
// Transfer-update endpoint — signature verification
// ---------------------------------------------------------------

it('rejects transfer-update when X-Webhook-Signature header is missing', function (): void {
    $this->postJson('/api/webhooks/bank/paysera/transfer-update', [
        'transfer_id' => 'bt_123',
        'status'      => 'completed',
    ])->assertUnauthorized()
        ->assertJsonPath('error', 'Invalid signature.');
});

it('rejects transfer-update when HMAC signature does not match', function (): void {
    $secret = 'real-webhook-secret-abc';
    config(['services.banking.webhooks.paysera.secret' => $secret]);

    $payload = [
        'transfer_id' => 'bt_sig_test',
        'status'      => 'completed',
    ];

    $this->postJson(
        '/api/webhooks/bank/paysera/transfer-update',
        $payload,
        ['X-Webhook-Signature' => 'bad-signature-value'],
    )->assertUnauthorized()
        ->assertJsonPath('error', 'Invalid signature.');
});

it('accepts transfer-update with valid HMAC signature', function (): void {
    $secret = 'valid-webhook-secret';
    config(['services.banking.webhooks.stripe.secret' => $secret]);

    $payload = [
        'transfer_id' => 'bt_hmac_ok',
        'status'      => 'succeeded',
        'message'     => 'Payment received',
    ];

    $signature = hash_hmac('sha256', (string) json_encode($payload), $secret);

    /** @var BankTransferService&MockInterface $mock */
    $mock = Mockery::mock(BankTransferService::class);
    $mock->shouldReceive('advanceStatus')
        ->once()
        ->with('bt_hmac_ok', 'completed', 'Payment received')
        ->andReturn(true);
    app()->instance(BankTransferService::class, $mock);

    $this->postJson(
        '/api/webhooks/bank/stripe/transfer-update',
        $payload,
        ['X-Webhook-Signature' => $signature],
    )->assertStatus(202)
        ->assertJsonPath('status', 'accepted')
        ->assertJsonPath('applied', true);
});

it('falls back to global webhook secret when provider secret is empty', function (): void {
    config(['services.banking.webhooks.newbank.secret' => '']);
    config(['services.banking.webhooks.secret' => 'global-secret-xyz']);

    $payload = [
        'transfer_id' => 'bt_global_secret',
        'status'      => 'processing',
    ];

    $signature = hash_hmac('sha256', (string) json_encode($payload), 'global-secret-xyz');

    /** @var BankTransferService&MockInterface $mock */
    $mock = Mockery::mock(BankTransferService::class);
    $mock->shouldReceive('advanceStatus')
        ->once()
        ->with('bt_global_secret', 'processing', 'Webhook update from newbank')
        ->andReturn(true);
    app()->instance(BankTransferService::class, $mock);

    $this->postJson(
        '/api/webhooks/bank/newbank/transfer-update',
        $payload,
        ['X-Webhook-Signature' => $signature],
    )->assertStatus(202);
});

// ---------------------------------------------------------------
// Transfer-update endpoint — payload validation
// ---------------------------------------------------------------

it('rejects transfer-update with empty payload', function (): void {
    config(['services.banking.webhooks.test.secret' => '']);

    $this->postJson(
        '/api/webhooks/bank/test/transfer-update',
        [],
        ['X-Webhook-Signature' => 'any-sig'],
    )->assertStatus(400)
        ->assertJsonPath('error', 'Empty payload.');
});

it('rejects transfer-update when transfer_id is missing', function (): void {
    $secret = 'field-test-secret';
    config(['services.banking.webhooks.test.secret' => $secret]);

    $payload = ['status' => 'completed'];
    $signature = hash_hmac('sha256', (string) json_encode($payload), $secret);

    $this->postJson(
        '/api/webhooks/bank/test/transfer-update',
        $payload,
        ['X-Webhook-Signature' => $signature],
    )->assertStatus(400)
        ->assertJsonPath('error', 'Missing transfer_id or status.');
});

it('rejects transfer-update when status is missing', function (): void {
    $secret = 'field-test-secret';
    config(['services.banking.webhooks.test.secret' => $secret]);

    $payload = ['transfer_id' => 'bt_no_status'];
    $signature = hash_hmac('sha256', (string) json_encode($payload), $secret);

    $this->postJson(
        '/api/webhooks/bank/test/transfer-update',
        $payload,
        ['X-Webhook-Signature' => $signature],
    )->assertStatus(400)
        ->assertJsonPath('error', 'Missing transfer_id or status.');
});

// ---------------------------------------------------------------
// Transfer-update endpoint — status mapping
// ---------------------------------------------------------------

it('maps provider status "succeeded" to "completed"', function (): void {
    $secret = 'map-secret';
    config(['services.banking.webhooks.test.secret' => $secret]);

    $payload = ['transfer_id' => 'bt_map1', 'status' => 'succeeded'];
    $signature = hash_hmac('sha256', (string) json_encode($payload), $secret);

    /** @var BankTransferService&MockInterface $mock */
    $mock = Mockery::mock(BankTransferService::class);
    $mock->shouldReceive('advanceStatus')
        ->once()
        ->with('bt_map1', 'completed', 'Webhook update from test')
        ->andReturn(true);
    app()->instance(BankTransferService::class, $mock);

    $this->postJson(
        '/api/webhooks/bank/test/transfer-update',
        $payload,
        ['X-Webhook-Signature' => $signature],
    )->assertStatus(202);
});

it('maps provider status "error" to "failed"', function (): void {
    $secret = 'map-secret';
    config(['services.banking.webhooks.test.secret' => $secret]);

    $payload = ['transfer_id' => 'bt_map2', 'status' => 'error'];
    $signature = hash_hmac('sha256', (string) json_encode($payload), $secret);

    /** @var BankTransferService&MockInterface $mock */
    $mock = Mockery::mock(BankTransferService::class);
    $mock->shouldReceive('advanceStatus')
        ->once()
        ->with('bt_map2', 'failed', 'Webhook update from test')
        ->andReturn(true);
    app()->instance(BankTransferService::class, $mock);

    $this->postJson(
        '/api/webhooks/bank/test/transfer-update',
        $payload,
        ['X-Webhook-Signature' => $signature],
    )->assertStatus(202);
});

it('maps provider status "canceled" to "cancelled"', function (): void {
    $secret = 'map-secret';
    config(['services.banking.webhooks.test.secret' => $secret]);

    $payload = ['transfer_id' => 'bt_map3', 'status' => 'canceled'];
    $signature = hash_hmac('sha256', (string) json_encode($payload), $secret);

    /** @var BankTransferService&MockInterface $mock */
    $mock = Mockery::mock(BankTransferService::class);
    $mock->shouldReceive('advanceStatus')
        ->once()
        ->with('bt_map3', 'cancelled', 'Webhook update from test')
        ->andReturn(true);
    app()->instance(BankTransferService::class, $mock);

    $this->postJson(
        '/api/webhooks/bank/test/transfer-update',
        $payload,
        ['X-Webhook-Signature' => $signature],
    )->assertStatus(202);
});

it('passes through unknown statuses unmapped', function (): void {
    $secret = 'map-secret';
    config(['services.banking.webhooks.test.secret' => $secret]);

    $payload = ['transfer_id' => 'bt_map4', 'status' => 'custom_state'];
    $signature = hash_hmac('sha256', (string) json_encode($payload), $secret);

    /** @var BankTransferService&MockInterface $mock */
    $mock = Mockery::mock(BankTransferService::class);
    $mock->shouldReceive('advanceStatus')
        ->once()
        ->with('bt_map4', 'custom_state', 'Webhook update from test')
        ->andReturn(false);
    app()->instance(BankTransferService::class, $mock);

    $this->postJson(
        '/api/webhooks/bank/test/transfer-update',
        $payload,
        ['X-Webhook-Signature' => $signature],
    )->assertStatus(202)
        ->assertJsonPath('applied', false);
});

it('reads alternative field names: id, state, reason', function (): void {
    $secret = 'alt-fields-secret';
    config(['services.banking.webhooks.alt.secret' => $secret]);

    $payload = [
        'id'     => 'bt_alt_fields',
        'state'  => 'success',
        'reason' => 'Alt-field webhook',
    ];
    $signature = hash_hmac('sha256', (string) json_encode($payload), $secret);

    /** @var BankTransferService&MockInterface $mock */
    $mock = Mockery::mock(BankTransferService::class);
    $mock->shouldReceive('advanceStatus')
        ->once()
        ->with('bt_alt_fields', 'completed', 'Alt-field webhook')
        ->andReturn(true);
    app()->instance(BankTransferService::class, $mock);

    $this->postJson(
        '/api/webhooks/bank/alt/transfer-update',
        $payload,
        ['X-Webhook-Signature' => $signature],
    )->assertStatus(202)
        ->assertJsonPath('applied', true);
});

// ---------------------------------------------------------------
// Account-update endpoint
// ---------------------------------------------------------------

it('rejects account-update when signature is missing', function (): void {
    $this->postJson('/api/webhooks/bank/paysera/account-update', [
        'event_type' => 'account.updated',
        'account_id' => 'acc_123',
    ])->assertUnauthorized();
});

it('rejects account-update with bad HMAC signature', function (): void {
    $secret = 'account-webhook-secret';
    config(['services.banking.webhooks.paysera.secret' => $secret]);

    $this->postJson(
        '/api/webhooks/bank/paysera/account-update',
        ['event_type' => 'account.updated', 'account_id' => 'acc_456'],
        ['X-Webhook-Signature' => 'wrong-hash'],
    )->assertUnauthorized();
});

it('rejects account-update with empty payload', function (): void {
    config(['services.banking.webhooks.test.secret' => '']);

    $this->postJson(
        '/api/webhooks/bank/test/account-update',
        [],
        ['X-Webhook-Signature' => 'any-sig'],
    )->assertStatus(400)
        ->assertJsonPath('error', 'Empty payload.');
});

it('accepts account-update with valid HMAC and returns 202', function (): void {
    $secret = 'account-secret';
    config(['services.banking.webhooks.paysera.secret' => $secret]);

    $payload = [
        'event_type' => 'account.updated',
        'account_id' => 'acc_789',
        'status'     => 'active',
    ];
    $signature = hash_hmac('sha256', (string) json_encode($payload), $secret);

    $this->postJson(
        '/api/webhooks/bank/paysera/account-update',
        $payload,
        ['X-Webhook-Signature' => $signature],
    )->assertStatus(202)
        ->assertJsonPath('status', 'accepted');
});
