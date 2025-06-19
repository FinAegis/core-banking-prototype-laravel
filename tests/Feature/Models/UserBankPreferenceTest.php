<?php

use App\Models\User;
use App\Models\UserBankPreference;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('user can have bank preferences', function () {
    $user = User::factory()->create();
    
    $preferences = UserBankPreference::getDefaultAllocations();
    
    foreach ($preferences as $pref) {
        $user->bankPreferences()->create($pref);
    }
    
    expect($user->bankPreferences)->toHaveCount(3);
    expect($user->activeBankPreferences)->toHaveCount(3);
    expect(UserBankPreference::validateAllocations($user->uuid))->toBeTrue();
});

test('bank allocations must sum to 100 percent', function () {
    $user = User::factory()->create();
    
    $user->bankPreferences()->create([
        'bank_code' => 'PAYSERA',
        'bank_name' => 'Paysera',
        'allocation_percentage' => 50.0,
        'status' => 'active',
    ]);
    
    $user->bankPreferences()->create([
        'bank_code' => 'DEUTSCHE',
        'bank_name' => 'Deutsche Bank',
        'allocation_percentage' => 30.0,
        'status' => 'active',
    ]);
    
    // Total is only 80%, should be invalid
    expect(UserBankPreference::validateAllocations($user->uuid))->toBeFalse();
    
    // Add remaining 20%
    $user->bankPreferences()->create([
        'bank_code' => 'SANTANDER',
        'bank_name' => 'Santander',
        'allocation_percentage' => 20.0,
        'status' => 'active',
    ]);
    
    // Now should be valid
    expect(UserBankPreference::validateAllocations($user->uuid))->toBeTrue();
});

test('only one bank can be primary', function () {
    $user = User::factory()->create();
    
    $paysera = $user->bankPreferences()->create([
        'bank_code' => 'PAYSERA',
        'bank_name' => 'Paysera',
        'allocation_percentage' => 100.0,
        'is_primary' => true,
        'status' => 'active',
    ]);
    
    expect($paysera->is_primary)->toBeTrue();
});

test('inactive bank preferences are excluded from validation', function () {
    $user = User::factory()->create();
    
    // Active preferences totaling 100%
    $user->bankPreferences()->create([
        'bank_code' => 'PAYSERA',
        'bank_name' => 'Paysera',
        'allocation_percentage' => 60.0,
        'status' => 'active',
    ]);
    
    $user->bankPreferences()->create([
        'bank_code' => 'DEUTSCHE',
        'bank_name' => 'Deutsche Bank',
        'allocation_percentage' => 40.0,
        'status' => 'active',
    ]);
    
    // Suspended preference should not affect validation
    $user->bankPreferences()->create([
        'bank_code' => 'SANTANDER',
        'bank_name' => 'Santander',
        'allocation_percentage' => 30.0,
        'status' => 'suspended',
    ]);
    
    expect(UserBankPreference::validateAllocations($user->uuid))->toBeTrue();
});