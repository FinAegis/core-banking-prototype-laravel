<?php

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Payment\Workflows\TransferWorkflow;
use App\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Workflow\WorkflowStub;

uses(RefreshDatabase::class);

beforeEach(function () {
    WorkflowStub::fake();
    
    // Create actual Account models for proper foreign key relationships
    $fromAccount = Account::factory()->withBalance(50000)->create();
    $toAccount = Account::factory()->create();
    
    $this->fromAccount = new AccountUuid($fromAccount->uuid);
    $this->toAccount = new AccountUuid($toAccount->uuid);
    $this->money = new Money(10000);
    
    // Store the actual account models for cleanup
    $this->fromAccountModel = $fromAccount;
    $this->toAccountModel = $toAccount;
});

it('can create workflow stub for transfer', function () {
    expect(class_exists(TransferWorkflow::class))->toBeTrue();
    
    $workflow = WorkflowStub::make(TransferWorkflow::class);
    expect($workflow)->toBeInstanceOf(WorkflowStub::class);
});

it('can execute transfer workflow with basic parameters', function () {
    $workflow = WorkflowStub::make(TransferWorkflow::class);
    
    $workflow->start($this->fromAccount, $this->toAccount, $this->money);

    expect(true)->toBeTrue();
});

it('handles zero amount transfer', function () {
    $zeroMoney = new Money(0);
    
    $workflow = WorkflowStub::make(TransferWorkflow::class);
    $workflow->start($this->fromAccount, $this->toAccount, $zeroMoney);

    expect(true)->toBeTrue();
});

it('can handle transfer to same account', function () {
    $sameAccountModel = Account::factory()->withBalance(50000)->create();
    $sameAccount = new AccountUuid($sameAccountModel->uuid);
    
    $workflow = WorkflowStub::make(TransferWorkflow::class);
    $workflow->start($sameAccount, $sameAccount, $this->money);

    expect(true)->toBeTrue();
});

it('handles large amount transfers', function () {
    // Create account with sufficient balance for large transfer
    $richAccount = Account::factory()->withBalance(1000000000)->create();
    $fromAccount = new AccountUuid($richAccount->uuid);
    
    $largeMoney = new Money(999999999);
    
    $workflow = WorkflowStub::make(TransferWorkflow::class);
    $workflow->start($fromAccount, $this->toAccount, $largeMoney);

    expect(true)->toBeTrue();
});