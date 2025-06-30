<?php

use App\Domain\Payment\Workflow\Activities\CompleteDepositActivity;
use App\Domain\Payment\Events\DepositCompleted;
use App\Models\PaymentDeposit;
use Illuminate\Support\Str;

it('can complete a deposit through activity', function () {
    $depositUuid = Str::uuid()->toString();
    $transactionId = 'txn_' . uniqid();
    
    // Create a deposit event first
    PaymentDeposit::create([
        'aggregate_uuid' => $depositUuid,
        'aggregate_version' => 1,
        'event_version' => 1,
        'event_class' => 'deposit_initiated',
        'event_properties' => json_encode([
            'accountUuid' => Str::uuid()->toString(),
            'amount' => 10000,
            'currency' => 'USD',
            'reference' => 'TEST-123',
            'externalReference' => 'pi_test_123',
            'paymentMethod' => 'card',
            'paymentMethodType' => 'visa',
            'metadata' => []
        ]),
        'meta_data' => json_encode([
            'aggregate_uuid' => $depositUuid
        ]),
        'created_at' => now()
    ]);
    
    $input = [
        'deposit_uuid' => $depositUuid,
        'transaction_id' => $transactionId
    ];
    
    // Create a mock activity to test the logic
    $activity = new class extends CompleteDepositActivity {
        public function __construct() {
            // Override constructor to avoid workflow dependencies
        }
    };
    
    $result = $activity->execute($input);
    
    expect($result)->toHaveKey('deposit_uuid');
    expect($result)->toHaveKey('status');
    expect($result)->toHaveKey('transaction_id');
    expect($result['deposit_uuid'])->toBe($depositUuid);
    expect($result['status'])->toBe('completed');
    expect($result['transaction_id'])->toBe($transactionId);
    
    // Verify the event was recorded
    $events = PaymentDeposit::where('aggregate_uuid', $depositUuid)
        ->where('event_class', 'deposit_completed')
        ->get();
    
    expect($events)->toHaveCount(1);
});

it('returns completed status for non-existent deposit', function () {
    $depositUuid = Str::uuid()->toString();
    $transactionId = 'txn_' . uniqid();
    
    $input = [
        'deposit_uuid' => $depositUuid,
        'transaction_id' => $transactionId
    ];
    
    $activity = new class extends CompleteDepositActivity {
        public function __construct() {
            // Override constructor
        }
    };
    
    $result = $activity->execute($input);
    
    // Even for non-existent deposits, the activity returns success
    // This is because the aggregate retrieve method creates a new instance
    expect($result)->toHaveKey('deposit_uuid');
    expect($result)->toHaveKey('status');
    expect($result)->toHaveKey('transaction_id');
    expect($result['deposit_uuid'])->toBe($depositUuid);
    expect($result['status'])->toBe('completed');
    expect($result['transaction_id'])->toBe($transactionId);
});