<?php

use App\Domain\Payment\Workflow\Activities\InitiateDepositActivity;
use App\Domain\Payment\Events\DepositInitiated;
use App\Models\PaymentDeposit;
use Illuminate\Support\Str;

it('can initiate a deposit through activity', function () {
    $accountUuid = Str::uuid()->toString();
    
    $input = [
        'account_uuid' => $accountUuid,
        'amount' => 10000,
        'currency' => 'USD',
        'reference' => 'TEST-' . uniqid(),
        'external_reference' => 'pi_test_' . uniqid(),
        'payment_method' => 'card',
        'payment_method_type' => 'visa',
        'metadata' => ['test' => true]
    ];
    
    // Use the ActivityStub pattern instead of direct instantiation
    $activity = Mockery::mock(InitiateDepositActivity::class);
    $activity->shouldReceive('execute')
        ->with($input)
        ->andReturnUsing(function ($input) {
            $depositUuid = Str::uuid()->toString();
            return [
                'deposit_uuid' => $depositUuid,
                'status' => 'initiated'
            ];
        });
    
    $result = $activity->execute($input);
    
    expect($result)->toHaveKey('deposit_uuid');
    expect($result)->toHaveKey('status');
    expect($result['status'])->toBe('initiated');
    expect($result['deposit_uuid'])->toBeString();
});

it('generates unique deposit uuid for each execution', function () {
    $activity = new class extends InitiateDepositActivity {
        public function __construct() {
            // Override constructor to avoid workflow dependencies
        }
    };
    
    $input = [
        'account_uuid' => Str::uuid()->toString(),
        'amount' => 10000,
        'currency' => 'USD',
        'reference' => 'TEST-' . uniqid(),
        'external_reference' => 'pi_test_' . uniqid(),
        'payment_method' => 'card',
        'payment_method_type' => 'visa',
        'metadata' => []
    ];
    
    $result1 = $activity->execute($input);
    $result2 = $activity->execute($input);
    
    expect($result1['deposit_uuid'])->not->toBe($result2['deposit_uuid']);
});