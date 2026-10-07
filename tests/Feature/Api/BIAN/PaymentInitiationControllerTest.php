<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

it('rejects payment with insufficient funds', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $payerAccount = Account::factory()->forUser($user)->withBalance(100)->create();
    $payeeAccount = Account::factory()->create();

    $response = $this->postJson('/api/bian/payment-initiation/initiate', [
        'payerReference' => $payerAccount->uuid,
        'payeeReference' => $payeeAccount->uuid,
        'paymentAmount'  => 500,
        'paymentType'    => 'internal',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('paymentInitiationTransaction.paymentStatus', 'rejected')
        ->assertJsonPath('paymentInitiationTransaction.statusReason', 'Insufficient funds');
});

it('can schedule a future payment', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $payerAccount = Account::factory()->forUser($user)->withBalance(1000)->create();
    $payeeAccount = Account::factory()->create();

    $response = $this->postJson('/api/bian/payment-initiation/initiate', [
        'payerReference' => $payerAccount->uuid,
        'payeeReference' => $payeeAccount->uuid,
        'paymentAmount'  => 300,
        'paymentType'    => 'scheduled',
        'valueDate'      => now()->addDays(7)->toDateString(),
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('paymentInitiationTransaction.paymentStatus', 'scheduled')
        ->assertJsonPath('paymentInitiationTransaction.paymentSchedule.valueDate', now()->addDays(7)->toDateString());
});

it('can update a payment transaction', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);
    $crReferenceId = Str::uuid()->toString();

    $response = $this->putJson("/api/bian/payment-initiation/{$crReferenceId}/update", [
        'paymentStatus' => 'cancelled',
        'statusReason'  => 'Customer requested cancellation',
    ]);

    $response->assertStatus(200)
        ->assertJsonPath('paymentInitiationTransaction.updateAction', 'cancelled')
        ->assertJsonPath('paymentInitiationTransaction.updateStatus', 'successful');
});

it('can execute a payment transaction', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);
    $crReferenceId = Str::uuid()->toString();

    $response = $this->postJson("/api/bian/payment-initiation/{$crReferenceId}/execute", [
        'executionMode' => 'immediate',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'paymentExecutionRecord' => [
                'crReferenceId',
                'executionMode',
                'executionStatus',
                'executionDateTime',
            ],
        ])
        ->assertJsonPath('paymentExecutionRecord.executionStatus', 'completed');
});

it('can request payment status', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);
    $crReferenceId = Str::uuid()->toString();

    $response = $this->postJson("/api/bian/payment-initiation/{$crReferenceId}/payment-status/request");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'paymentStatusRecord' => [
                'crReferenceId',
                'bqReferenceId',
                'paymentStatus',
                'statusCheckDateTime',
                'eventCount',
            ],
        ]);
});

it('can retrieve payment history', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);
    $account = Account::factory()->forUser($user)->create();

    $response = $this->getJson("/api/bian/payment-initiation/{$account->uuid}/payment-history/retrieve");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'paymentHistoryRecord' => [
                'accountReference',
                'bqReferenceId',
                'historyPeriod' => [
                    'fromDate',
                    'toDate',
                ],
                'payments',
                'paymentCount',
                'retrievalDateTime',
            ],
        ]);
});

it('validates required fields for payment initiation', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $response = $this->postJson('/api/bian/payment-initiation/initiate', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['payerReference', 'payeeReference', 'paymentAmount', 'paymentType']);
});

it('prevents payment to same account', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);
    $account = Account::factory()->forUser($user)->withBalance(1000)->create();

    $response = $this->postJson('/api/bian/payment-initiation/initiate', [
        'payerReference' => $account->uuid,
        'payeeReference' => $account->uuid,
        'paymentAmount'  => 100,
        'paymentType'    => 'internal',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['payeeReference']);
});

it('requires authentication for all endpoints', function () {
    $uuid = Str::uuid()->toString();

    $this->postJson('/api/bian/payment-initiation/initiate')->assertStatus(401);
    $this->getJson("/api/bian/payment-initiation/{$uuid}/retrieve")->assertStatus(401);
    $this->putJson("/api/bian/payment-initiation/{$uuid}/update")->assertStatus(401);
    $this->postJson("/api/bian/payment-initiation/{$uuid}/execute")->assertStatus(401);
    $this->postJson("/api/bian/payment-initiation/{$uuid}/payment-status/request")->assertStatus(401);
    $this->getJson("/api/bian/payment-initiation/{$uuid}/payment-history/retrieve")->assertStatus(401);
});

it('refuses a payment from an account the caller does not own', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);

    $payerAccount = Account::factory()->withBalance(1000)->create();
    $payeeAccount = Account::factory()->forUser($user)->create();

    $this->postJson('/api/bian/payment-initiation/initiate', [
        'payerReference' => $payerAccount->uuid,
        'payeeReference' => $payeeAccount->uuid,
        'paymentAmount'  => 300,
        'paymentType'    => 'internal',
    ])->assertStatus(403)->assertJsonPath('error', 'FORBIDDEN');
});

it('refuses the payment history of an account the caller does not own', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['read', 'write', 'delete']);
    $account = Account::factory()->create();

    $this->getJson("/api/bian/payment-initiation/{$account->uuid}/payment-history/retrieve")
        ->assertStatus(403);
});
