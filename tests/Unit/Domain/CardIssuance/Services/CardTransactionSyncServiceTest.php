<?php

declare(strict_types=1);

use App\Domain\CardIssuance\Contracts\CardIssuerInterface;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Services\CardTransactionSyncService;
use App\Models\User;
use Illuminate\Support\Facades\DB;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    $this->issuer = Mockery::mock(CardIssuerInterface::class);
    $this->service = new CardTransactionSyncService($this->issuer);
    $this->testUser = User::factory()->create();
    $this->testCardholder = Cardholder::create([
        'user_id'    => $this->testUser->id,
        'first_name' => 'Test',
        'last_name'  => 'User',
        'kyc_status' => 'verified',
    ]);
});

it('processes transaction.created webhook', function (): void {
    // Create a card in DB
    DB::table('cards')->insert([
        'id'                => 'card-uuid-1',
        'user_id'           => $this->testUser->id,
        'cardholder_id'     => $this->testCardholder->id,
        'issuer_card_token' => 'rain_card_123',
        'issuer'            => 'rain',
        'last4'             => '4242',
        'network'           => 'visa',
        'status'            => 'active',
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);

    $result = $this->service->processWebhook([
        'event_type' => 'transaction.created',
        'data'       => [
            'id'                     => 'tx_001',
            'card_id'                => 'rain_card_123',
            'merchant_name'          => 'Coffee Shop',
            'merchant_category_code' => '5812',
            'amount'                 => 550,
            'currency'               => 'USD',
            'status'                 => 'pending',
            'created_at'             => '2026-03-18T12:00:00Z',
        ],
    ]);

    expect($result['synced'])->toBeTrue();
    expect($result['transaction_id'])->toBe('tx_001');

    $tx = DB::table('card_transactions')->where('external_id', 'tx_001')->first();
    expect($tx)->not->toBeNull();
    expect($tx->merchant_name)->toBe('Coffee Shop');
    expect($tx->amount_cents)->toBe(550);
    expect($tx->status)->toBe('pending');
});

it('processes transaction.settled webhook', function (): void {
    DB::table('cards')->insert([
        'id'                => 'card-uuid-2',
        'user_id'           => $this->testUser->id,
        'cardholder_id'     => $this->testCardholder->id,
        'issuer_card_token' => 'rain_card_456',
        'issuer'            => 'rain',
        'last4'             => '9876',
        'network'           => 'visa',
        'status'            => 'active',
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);

    // First create the transaction
    $this->service->processWebhook([
        'event_type' => 'transaction.created',
        'data'       => [
            'id'            => 'tx_002',
            'card_id'       => 'rain_card_456',
            'merchant_name' => 'Amazon',
            'amount'        => 2999,
            'currency'      => 'USD',
            'status'        => 'pending',
        ],
    ]);

    // Then settle it
    $result = $this->service->processWebhook([
        'event_type' => 'transaction.settled',
        'data'       => [
            'id'           => 'tx_002',
            'final_amount' => 2999,
        ],
    ]);

    expect($result['synced'])->toBeTrue();

    $tx = DB::table('card_transactions')->where('external_id', 'tx_002')->first();
    expect($tx->status)->toBe('settled');
});

it('returns false for unknown card token', function (): void {
    $result = $this->service->processWebhook([
        'event_type' => 'transaction.created',
        'data'       => [
            'id'      => 'tx_orphan',
            'card_id' => 'nonexistent_card',
            'amount'  => 100,
        ],
    ]);

    expect($result['synced'])->toBeFalse();
    expect($result['transaction_id'])->toBeNull();
});

it('handles transaction.declined webhook', function (): void {
    DB::table('cards')->insert([
        'id'                => 'card-uuid-3',
        'user_id'           => $this->testUser->id,
        'cardholder_id'     => $this->testCardholder->id,
        'issuer_card_token' => 'rain_card_789',
        'issuer'            => 'rain',
        'last4'             => '1111',
        'network'           => 'visa',
        'status'            => 'active',
        'created_at'        => now(),
        'updated_at'        => now(),
    ]);

    $result = $this->service->processWebhook([
        'event_type' => 'transaction.declined',
        'data'       => [
            'id'            => 'tx_declined',
            'card_id'       => 'rain_card_789',
            'merchant_name' => 'Sketchy Shop',
            'amount'        => 99999,
            'currency'      => 'USD',
        ],
    ]);

    expect($result['synced'])->toBeTrue();

    $tx = DB::table('card_transactions')->where('external_id', 'tx_declined')->first();
    expect($tx->status)->toBe('declined');
});

it('returns false for unknown event type', function (): void {
    $result = $this->service->processWebhook([
        'event_type' => 'card.activated',
        'data'       => ['id' => 'something'],
    ]);

    expect($result['synced'])->toBeFalse();
});
