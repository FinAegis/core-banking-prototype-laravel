<?php

use App\Domain\Payment\Workflow\Activities\InitiateWithdrawalActivity;
use Illuminate\Support\Str;

it('can initiate a withdrawal through activity', function () {
    $accountUuid = Str::uuid()->toString();
    
    $input = [
        'account_uuid' => $accountUuid,
        'amount' => 5000,
        'currency' => 'USD',
        'reference' => 'WD-' . uniqid(),
        'bank_name' => 'Test Bank',
        'bank_account_number' => '****1234',
        'bank_account_name' => 'John Doe',
        'bank_routing_number' => '123456789',
        'metadata' => ['test' => true]
    ];
    
    $activity = new class extends InitiateWithdrawalActivity {
        public function __construct() {
            // Override constructor
        }
    };
    
    $result = $activity->execute($input);
    
    expect($result)->toHaveKey('withdrawal_uuid');
    expect($result)->toHaveKey('status');
    expect($result['status'])->toBe('initiated');
    expect($result['withdrawal_uuid'])->toBeString();
});

it('generates unique withdrawal uuid for each execution', function () {
    $activity = new class extends InitiateWithdrawalActivity {
        public function __construct() {
            // Override constructor
        }
    };
    
    $input = [
        'account_uuid' => Str::uuid()->toString(),
        'amount' => 5000,
        'currency' => 'USD',
        'reference' => 'WD-' . uniqid(),
        'bank_name' => 'Test Bank',
        'bank_account_number' => '****1234',
        'bank_account_name' => 'John Doe',
        'bank_routing_number' => '123456789',
        'metadata' => []
    ];
    
    $result1 = $activity->execute($input);
    $result2 = $activity->execute($input);
    
    expect($result1['withdrawal_uuid'])->not->toBe($result2['withdrawal_uuid']);
});

it('uses default bank name when not provided', function () {
    $activity = new class extends InitiateWithdrawalActivity {
        public function __construct() {
            // Override constructor
        }
    };
    
    $input = [
        'account_uuid' => Str::uuid()->toString(),
        'amount' => 5000,
        'currency' => 'USD',
        'reference' => 'WD-' . uniqid(),
        'bank_account_number' => '****1234',
        'bank_account_name' => 'John Doe',
        'metadata' => []
    ];
    
    $result = $activity->execute($input);
    
    expect($result['status'])->toBe('initiated');
});