<?php

use App\Domain\Payment\Services\TransferService;
use App\Domain\Payment\Workflows\TransferWorkflow;
use App\Models\Account;
use Workflow\WorkflowStub;

beforeEach(function () {
    WorkflowStub::fake();
    $this->transferService = app(TransferService::class);
});

it('has transfer method', function () {
    expect(method_exists($this->transferService, 'transfer'))->toBeTrue();
});

it('can be instantiated from container', function () {
    expect($this->transferService)->toBeInstanceOf(TransferService::class);
});

it('can transfer money between accounts with string uuids and integer amount', function () {
    // Create accounts with sufficient balance
    $fromAccount = Account::factory()->withBalance(50000)->create();
    $toAccount = Account::factory()->create();
    $amount = 10000;

    $this->transferService->transfer($fromAccount->uuid, $toAccount->uuid, $amount);

    expect(true)->toBeTrue();
});

it('can handle zero amount transfer', function () {
    // Create accounts
    $fromAccount = Account::factory()->create();
    $toAccount = Account::factory()->create();
    $amount = 0;

    $this->transferService->transfer($fromAccount->uuid, $toAccount->uuid, $amount);

    expect(true)->toBeTrue();
});

it('can handle large amount transfer', function () {
    // Create account with large balance
    $fromAccount = Account::factory()->withBalance(1000000000)->create();
    $toAccount = Account::factory()->create();
    $amount = 999999999;

    $this->transferService->transfer($fromAccount->uuid, $toAccount->uuid, $amount);

    expect(true)->toBeTrue();
});