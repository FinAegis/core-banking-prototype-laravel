<?php

use App\Domain\Payment\Workflow\Activities\FailWithdrawalActivity;
use App\Models\PaymentWithdrawal;
use Illuminate\Support\Str;

it('can fail a withdrawal through activity', function () {
    $withdrawalUuid = Str::uuid()->toString();
    $reason = 'Invalid bank account';
    
    // Create a withdrawal event first
    PaymentWithdrawal::create([
        'aggregate_uuid' => $withdrawalUuid,
        'aggregate_version' => 1,
        'event_version' => 1,
        'event_class' => 'withdrawal_initiated',
        'event_properties' => json_encode([
            'accountUuid' => Str::uuid()->toString(),
            'amount' => 5000,
            'currency' => 'USD',
            'reference' => 'WD-123',
            'bankAccountNumber' => '****1234',
            'bankRoutingNumber' => '123456789',
            'bankAccountName' => 'John Doe',
            'metadata' => []
        ]),
        'meta_data' => json_encode([
            'aggregate_uuid' => $withdrawalUuid
        ]),
        'created_at' => now()
    ]);
    
    $input = [
        'withdrawal_uuid' => $withdrawalUuid,
        'reason' => $reason
    ];
    
    $activity = new class extends FailWithdrawalActivity {
        public function __construct() {
            // Override constructor
        }
    };
    
    $result = $activity->execute($input);
    
    expect($result)->toHaveKey('withdrawal_uuid');
    expect($result)->toHaveKey('status');
    expect($result)->toHaveKey('reason');
    expect($result['withdrawal_uuid'])->toBe($withdrawalUuid);
    expect($result['status'])->toBe('failed');
    expect($result['reason'])->toBe($reason);
    
    // Verify the event was recorded
    $events = PaymentWithdrawal::where('aggregate_uuid', $withdrawalUuid)
        ->where('event_class', 'withdrawal_failed')
        ->get();
    
    expect($events)->toHaveCount(1);
});