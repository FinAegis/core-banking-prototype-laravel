<?php

declare(strict_types=1);

use App\Domain\Banking\Services\BankTransferService;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    config(['cache.default' => 'array']);
    Cache::flush();
    $this->service = new BankTransferService();
    $this->testUser = User::factory()->create();
});

it('initiates a SEPA instant transfer', function (): void {
    $result = $this->service->initiate([
        'user_uuid'       => $this->testUser->uuid,
        'from_bank_code'  => 'REVOLT',
        'from_account_id' => 'acc-001',
        'to_account_id'   => 'acc-002',
        'to_iban'         => 'DE89370400440532013000',
        'to_bank_code'    => 'DEUTDE',
        'amount'          => 500.00,
        'currency'        => 'EUR',
        'description'     => 'Rent payment',
    ]);

    expect($result['transfer_id'])->toStartWith('bt_');
    expect($result['status'])->toBe('initiated');
    expect($result['reference'])->toBeString()->not->toBeEmpty();
    expect($result['estimated_completion'])->toBeString();
});

it('initiates a SWIFT transfer for non-EUR', function (): void {
    $result = $this->service->initiate([
        'user_uuid'       => $this->testUser->uuid,
        'from_bank_code'  => 'REVOLT',
        'from_account_id' => 'acc-001',
        'to_account_id'   => 'acc-003',
        'to_bank_code'    => 'JPMORGAN',
        'amount'          => 10000.00,
        'currency'        => 'USD',
    ]);

    expect($result['transfer_id'])->toStartWith('bt_');
    expect($result['status'])->toBe('initiated');
});

it('gets transfer status from cache', function (): void {
    $result = $this->service->initiate([
        'user_uuid'       => $this->testUser->uuid,
        'from_bank_code'  => 'N26',
        'from_account_id' => 'acc-010',
        'to_account_id'   => 'acc-020',
        'to_iban'         => 'FR7630006000011234567890189',
        'amount'          => 250.00,
        'currency'        => 'EUR',
    ]);

    $status = $this->service->getStatus($result['transfer_id']);

    expect($status['transfer_id'])->toBe($result['transfer_id']);
    expect($status['status'])->toBe('initiated');
    expect($status['amount'])->toBe(250.0);
    expect($status['currency'])->toBe('EUR');
    expect($status['status_history'])->toHaveCount(1);
});

it('returns not_found for unknown transfer', function (): void {
    $status = $this->service->getStatus('bt_nonexistent');

    expect($status['status'])->toBe('not_found');
});

it('advances transfer status through valid transitions', function (): void {
    $result = $this->service->initiate([
        'user_uuid'       => $this->testUser->uuid,
        'from_bank_code'  => 'N26',
        'from_account_id' => 'acc-100',
        'to_account_id'   => 'acc-200',
        'to_iban'         => 'DE89370400440532013000',
        'amount'          => 100.00,
        'currency'        => 'EUR',
    ]);

    $transferId = $result['transfer_id'];

    expect($this->service->advanceStatus($transferId, 'pending', 'Submitted to bank'))->toBeTrue();
    expect($this->service->getStatus($transferId)['status'])->toBe('pending');

    expect($this->service->advanceStatus($transferId, 'processing', 'Bank processing'))->toBeTrue();
    expect($this->service->getStatus($transferId)['status'])->toBe('processing');

    expect($this->service->advanceStatus($transferId, 'completed', 'Funds delivered'))->toBeTrue();
    expect($this->service->getStatus($transferId)['status'])->toBe('completed');

    $history = $this->service->getStatus($transferId)['status_history'];
    expect($history)->toHaveCount(4); // initiated + pending + processing + completed
});

it('rejects invalid status transitions', function (): void {
    $result = $this->service->initiate([
        'user_uuid'       => $this->testUser->uuid,
        'from_bank_code'  => 'REVOLT',
        'from_account_id' => 'acc-300',
        'to_account_id'   => 'acc-400',
        'amount'          => 50.00,
        'currency'        => 'EUR',
    ]);

    // Can't go from initiated directly to completed
    expect($this->service->advanceStatus($result['transfer_id'], 'completed'))->toBeFalse();
});

it('cancels a transfer', function (): void {
    $result = $this->service->initiate([
        'user_uuid'       => $this->testUser->uuid,
        'from_bank_code'  => 'N26',
        'from_account_id' => 'acc-500',
        'to_account_id'   => 'acc-600',
        'amount'          => 75.00,
        'currency'        => 'EUR',
    ]);

    expect($this->service->cancel($result['transfer_id'], 'Changed my mind'))->toBeTrue();
    expect($this->service->getStatus($result['transfer_id'])['status'])->toBe('cancelled');
});

it('cannot cancel a processing transfer', function (): void {
    $result = $this->service->initiate([
        'user_uuid'       => $this->testUser->uuid,
        'from_bank_code'  => 'N26',
        'from_account_id' => 'acc-700',
        'to_account_id'   => 'acc-800',
        'amount'          => 200.00,
        'currency'        => 'EUR',
    ]);

    $this->service->advanceStatus($result['transfer_id'], 'pending');
    $this->service->advanceStatus($result['transfer_id'], 'processing');

    expect($this->service->cancel($result['transfer_id']))->toBeFalse();
});

it('lists transfers for a user', function (): void {
    $this->service->initiate([
        'user_uuid'       => $this->testUser->uuid,
        'from_bank_code'  => 'N26',
        'from_account_id' => 'acc-a',
        'to_account_id'   => 'acc-b',
        'amount'          => 100.00,
        'currency'        => 'EUR',
    ]);
    $this->service->initiate([
        'user_uuid'       => $this->testUser->uuid,
        'from_bank_code'  => 'N26',
        'from_account_id' => 'acc-c',
        'to_account_id'   => 'acc-d',
        'amount'          => 200.00,
        'currency'        => 'EUR',
    ]);

    $transfers = $this->service->listForUser($this->testUser->uuid);
    expect($transfers)->toHaveCount(2);
    $amounts = array_map(fn ($t) => (float) $t['amount'], $transfers);
    sort($amounts);
    expect($amounts)->toBe([100.0, 200.0]);
});
