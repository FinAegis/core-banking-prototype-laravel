<?php

use App\Domain\Payment\Workflow\Activities\FailDepositActivity;
use App\Models\PaymentDeposit;
use Illuminate\Support\Str;

it('can fail a deposit through activity', function () {
    $depositUuid = Str::uuid()->toString();
    $reason = 'Card declined';
    
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
        'reason' => $reason
    ];
    
    $activity = new class extends FailDepositActivity {
        public function __construct() {
            // Override constructor
        }
    };
    
    $result = $activity->execute($input);
    
    expect($result)->toHaveKey('deposit_uuid');
    expect($result)->toHaveKey('status');
    expect($result)->toHaveKey('reason');
    expect($result['deposit_uuid'])->toBe($depositUuid);
    expect($result['status'])->toBe('failed');
    expect($result['reason'])->toBe($reason);
    
    // Verify the event was recorded
    $events = PaymentDeposit::where('aggregate_uuid', $depositUuid)
        ->where('event_class', 'deposit_failed')
        ->get();
    
    expect($events)->toHaveCount(1);
});

it('throws exception when trying to fail non-existent deposit', function () {
    $depositUuid = Str::uuid()->toString();
    $reason = 'Card declined';
    
    $input = [
        'deposit_uuid' => $depositUuid,
        'reason' => $reason
    ];
    
    expect(function () use ($input) {
        $activity = new class extends FailDepositActivity {
            public function __construct() {
                // Override constructor
            }
        };
        $activity->execute($input);
    })->toThrow(Exception::class);
});