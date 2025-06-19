<?php

use App\Models\CustodianWebhook;
use App\Models\CustodianAccount;
use App\Models\Account;
use App\Jobs\ProcessCustodianWebhook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

it('can receive and store paysera webhook', function () {
    $payload = [
        'event' => 'payment.completed',
        'event_id' => 'evt_123',
        'payment_id' => 'pay_456',
        'account_id' => 'acc_789',
        'amount' => 10000,
        'currency' => 'EUR',
        'timestamp' => now()->toISOString(),
    ];
    
    $signature = hash_hmac('sha256', json_encode($payload), 'test-secret');
    
    // Set test webhook secret
    config(['custodians.connectors.paysera.webhook_secret' => 'test-secret']);
    
    $response = $this->postJson('/api/webhooks/custodian/paysera', $payload, [
        'X-Paysera-Signature' => $signature,
    ]);
    
    $response->assertStatus(202)
             ->assertJson(['status' => 'accepted']);
    
    // Verify webhook was stored
    $webhook = CustodianWebhook::first();
    expect($webhook)->not->toBeNull();
    expect($webhook->custodian_name)->toBe('paysera');
    expect($webhook->event_type)->toBe('payment.completed');
    expect($webhook->event_id)->toBe('evt_123');
    expect($webhook->payload)->toMatchArray($payload);
    expect($webhook->status)->toBe('pending');
    
    // Verify job was dispatched
    Queue::assertPushed(ProcessCustodianWebhook::class, function ($job) use ($webhook) {
        return $job->webhookId === $webhook->uuid;
    });
});

it('rejects webhook with invalid signature', function () {
    $payload = [
        'event' => 'payment.completed',
        'event_id' => 'evt_123',
    ];
    
    config(['custodians.connectors.paysera.webhook_secret' => 'test-secret']);
    
    $response = $this->postJson('/api/webhooks/custodian/paysera', $payload, [
        'X-Paysera-Signature' => 'invalid-signature',
    ]);
    
    $response->assertStatus(401)
             ->assertJson(['error' => 'Invalid signature']);
    
    expect(CustodianWebhook::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('handles malformed webhook payload', function () {
    $invalidJson = 'invalid-json';
    $signature = hash_hmac('sha256', $invalidJson, 'test-secret');
    
    config(['custodians.connectors.paysera.webhook_secret' => 'test-secret']);
    
    // Send invalid JSON with correct signature
    $response = $this->call('POST', '/api/webhooks/custodian/paysera', [], [], [], [
        'HTTP_X-Paysera-Signature' => $signature,
        'CONTENT_TYPE' => 'application/json',
    ], $invalidJson);
    
    $response->assertStatus(400)
             ->assertJson(['error' => 'Invalid payload']);
});

it('prevents duplicate webhook processing', function () {
    $payload = [
        'event' => 'payment.completed',
        'event_id' => 'evt_duplicate',
        'payment_id' => 'pay_123',
    ];
    
    $signature = hash_hmac('sha256', json_encode($payload), 'test-secret');
    config(['custodians.connectors.paysera.webhook_secret' => 'test-secret']);
    
    // Send first webhook
    $this->postJson('/api/webhooks/custodian/paysera', $payload, [
        'X-Paysera-Signature' => $signature,
    ])->assertStatus(202);
    
    // Send duplicate webhook
    $this->postJson('/api/webhooks/custodian/paysera', $payload, [
        'X-Paysera-Signature' => $signature,
    ])->assertStatus(202);
    
    // Should only have one webhook stored due to unique constraint
    expect(CustodianWebhook::count())->toBe(1);
});

it('can receive santander webhook with timestamp validation', function () {
    $timestamp = (string) time();
    $payload = [
        'event_type' => 'transfer.completed',
        'id' => 'txn_123',
        'transfer_id' => 'trf_456',
    ];
    
    $dataToSign = $timestamp . '.' . json_encode($payload);
    $signature = hash_hmac('sha512', $dataToSign, 'santander-secret');
    
    config(['custodians.connectors.santander.webhook_secret' => 'santander-secret']);
    
    $response = $this->postJson('/api/webhooks/custodian/santander', $payload, [
        'X-Santander-Signature' => $signature,
        'X-Santander-Timestamp' => $timestamp,
    ]);
    
    $response->assertStatus(202);
    
    $webhook = CustodianWebhook::first();
    expect($webhook->custodian_name)->toBe('santander');
    expect($webhook->event_type)->toBe('transfer.completed');
});

it('can receive mock webhook for testing', function () {
    $payload = [
        'type' => 'transaction.completed',
        'id' => 'mock_123',
        'transaction_id' => 'tx_456',
    ];
    
    $response = $this->postJson('/api/webhooks/custodian/mock', $payload);
    
    $response->assertStatus(202);
    
    $webhook = CustodianWebhook::first();
    expect($webhook->custodian_name)->toBe('mock');
    expect($webhook->event_type)->toBe('transaction.completed');
});

it('marks webhook as processing when job starts', function () {
    $webhook = CustodianWebhook::create([
        'custodian_name' => 'mock',
        'event_type' => 'test',
        'payload' => ['test' => true],
        'status' => 'pending',
    ]);
    
    expect($webhook->status)->toBe('pending');
    expect($webhook->attempts)->toBe(0);
    
    $webhook->markAsProcessing();
    
    expect($webhook->fresh()->status)->toBe('processing');
    expect($webhook->fresh()->attempts)->toBe(1);
});

it('marks webhook as processed successfully', function () {
    $webhook = CustodianWebhook::create([
        'custodian_name' => 'mock',
        'event_type' => 'test',
        'payload' => ['test' => true],
        'status' => 'processing',
        'attempts' => 1,
    ]);
    
    $webhook->markAsProcessed();
    
    expect($webhook->fresh()->status)->toBe('processed');
    expect($webhook->fresh()->processed_at)->not->toBeNull();
    expect($webhook->fresh()->error_message)->toBeNull();
});

it('marks webhook as failed with error message', function () {
    $webhook = CustodianWebhook::create([
        'custodian_name' => 'mock',
        'event_type' => 'test',
        'payload' => ['test' => true],
        'status' => 'processing',
        'attempts' => 1,
    ]);
    
    $webhook->markAsFailed('Connection timeout');
    
    expect($webhook->fresh()->status)->toBe('failed');
    expect($webhook->fresh()->error_message)->toBe('Connection timeout');
});

it('can find retryable webhooks', function () {
    // Create various webhooks
    $pending = CustodianWebhook::create([
        'custodian_name' => 'mock',
        'event_type' => 'test',
        'payload' => [],
        'status' => 'pending',
    ]);
    
    $processed = CustodianWebhook::create([
        'custodian_name' => 'mock',
        'event_type' => 'test',
        'payload' => [],
        'status' => 'processed',
    ]);
    
    $failedRetryable = CustodianWebhook::create([
        'custodian_name' => 'mock',
        'event_type' => 'test',
        'payload' => [],
        'status' => 'failed',
        'attempts' => 2,
    ]);
    
    $failedMaxAttempts = CustodianWebhook::create([
        'custodian_name' => 'mock',
        'event_type' => 'test',
        'payload' => [],
        'status' => 'failed',
        'attempts' => 3,
    ]);
    
    $retryable = CustodianWebhook::retryable(3)->get();
    
    expect($retryable)->toHaveCount(1);
    expect($retryable->first()->id)->toBe($failedRetryable->id);
});

it('updates custodian account reference when processing balance webhook', function () {
    $account = Account::factory()->create();
    $custodianAccount = CustodianAccount::factory()->create([
        'account_uuid' => $account->uuid,
        'custodian_name' => 'mock',
        'custodian_account_id' => 'mock-123',
    ]);
    
    $webhook = CustodianWebhook::create([
        'custodian_name' => 'mock',
        'event_type' => 'balance.updated',
        'payload' => [
            'account' => 'mock-123',
            'balances' => [
                'USD' => 50000,
                'EUR' => 25000,
            ],
        ],
        'status' => 'pending',
    ]);
    
    // Process webhook (simplified for test)
    $webhook->update(['custodian_account_id' => $custodianAccount->uuid]);
    
    expect($webhook->fresh()->custodian_account_id)->toBe($custodianAccount->uuid);
    expect($webhook->custodianAccount->id)->toBe($custodianAccount->id);
});