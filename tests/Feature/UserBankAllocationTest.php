<?php

use App\Models\User;
use App\Models\UserBankPreference;
use App\Domain\Account\Services\BankAllocationService;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->service = app(BankAllocationService::class);
});

it('can set up default bank allocations for a new user', function () {
    $preferences = $this->service->setupDefaultAllocations($this->user);
    
    expect($preferences)->toHaveCount(3);
    expect($preferences->sum('allocation_percentage'))->toBe(100.0);
    expect($preferences->where('is_primary', true))->toHaveCount(1);
    expect((float)$preferences->where('bank_code', 'PAYSERA')->first()->allocation_percentage)->toBe(40.0);
    expect((float)$preferences->where('bank_code', 'DEUTSCHE')->first()->allocation_percentage)->toBe(30.0);
    expect((float)$preferences->where('bank_code', 'SANTANDER')->first()->allocation_percentage)->toBe(30.0);
});

it('validates that allocations sum to 100%', function () {
    $this->service->setupDefaultAllocations($this->user);
    
    expect(UserBankPreference::validateAllocations($this->user->uuid))->toBeTrue();
});

it('can calculate fund distribution across banks', function () {
    $this->service->setupDefaultAllocations($this->user);
    
    $distribution = UserBankPreference::calculateDistribution($this->user->uuid, 10000); // €100.00
    
    expect($distribution)->toHaveCount(3);
    expect($distribution[0]['amount'])->toBe(4000); // 40% to Paysera
    expect($distribution[1]['amount'])->toBe(3000); // 30% to Deutsche
    expect($distribution[2]['amount'])->toBe(3000); // 30% to Santander
    expect(array_sum(array_column($distribution, 'amount')))->toBe(10000);
});

it('handles rounding correctly in distribution calculation', function () {
    $this->service->setupDefaultAllocations($this->user);
    
    // Test with amount that doesn't divide evenly
    $distribution = UserBankPreference::calculateDistribution($this->user->uuid, 333); // €3.33
    
    $total = array_sum(array_column($distribution, 'amount'));
    expect($total)->toBe(333);
});

it('can update user bank allocations', function () {
    $this->service->setupDefaultAllocations($this->user);
    
    $newAllocations = [
        'PAYSERA' => 50,
        'REVOLUT' => 30,
        'WISE' => 20,
    ];
    
    $preferences = $this->service->updateAllocations($this->user, $newAllocations);
    
    expect($preferences)->toHaveCount(3);
    expect((float)$preferences->where('bank_code', 'PAYSERA')->first()->allocation_percentage)->toBe(50.0);
    expect((float)$preferences->where('bank_code', 'REVOLUT')->first()->allocation_percentage)->toBe(30.0);
    expect((float)$preferences->where('bank_code', 'WISE')->first()->allocation_percentage)->toBe(20.0);
    
    // Old banks should be removed
    $remainingBanks = $this->user->bankPreferences()->pluck('bank_code')->toArray();
    expect($remainingBanks)->not->toContain('DEUTSCHE', 'SANTANDER');
});

it('throws exception if allocations do not sum to 100%', function () {
    $invalidAllocations = [
        'PAYSERA' => 50,
        'REVOLUT' => 30,
        // Missing 20%
    ];
    
    expect(fn() => $this->service->updateAllocations($this->user, $invalidAllocations))
        ->toThrow(\Exception::class, 'Allocations must sum to 100%');
});

it('can add a new bank to existing allocation when under 100%', function () {
    // First create preferences that don't sum to 100%
    $this->user->bankPreferences()->create([
        'bank_code' => 'PAYSERA',
        'bank_name' => 'Paysera',
        'allocation_percentage' => 40,
        'is_primary' => true,
        'status' => 'active',
    ]);
    $this->user->bankPreferences()->create([
        'bank_code' => 'DEUTSCHE',
        'bank_name' => 'Deutsche Bank',
        'allocation_percentage' => 40,
        'is_primary' => false,
        'status' => 'active',
    ]);
    
    // Add new bank to complete 100%
    $newBank = $this->service->addBank($this->user, 'REVOLUT', 20);
    
    expect($newBank->bank_code)->toBe('REVOLUT');
    expect((float)$newBank->allocation_percentage)->toBe(20.0);
    expect(UserBankPreference::validateAllocations($this->user->uuid))->toBeTrue();
});

it('can set a bank as primary', function () {
    $this->service->setupDefaultAllocations($this->user);
    
    // Deutsche Bank should not be primary initially
    $deutsche = $this->user->bankPreferences()->where('bank_code', 'DEUTSCHE')->first();
    expect($deutsche->is_primary)->toBeFalse();
    
    // Set Deutsche as primary
    $this->service->setPrimaryBank($this->user, 'DEUTSCHE');
    
    $deutsche->refresh();
    expect($deutsche->is_primary)->toBeTrue();
    
    // Paysera should no longer be primary
    $paysera = $this->user->bankPreferences()->where('bank_code', 'PAYSERA')->first();
    expect($paysera->is_primary)->toBeFalse();
});

it('calculates total deposit insurance coverage', function () {
    $this->service->setupDefaultAllocations($this->user);
    
    $coverage = UserBankPreference::getTotalInsuranceCoverage($this->user->uuid);
    
    expect($coverage)->toBe(300000); // 3 banks × €100,000
});

it('checks if user has diversified allocation', function () {
    $this->service->setupDefaultAllocations($this->user);
    
    expect(UserBankPreference::isDiversified($this->user->uuid))->toBeTrue();
    
    // Update to non-diversified allocation
    $this->service->updateAllocations($this->user, [
        'PAYSERA' => 80, // Too concentrated
        'DEUTSCHE' => 20,
    ]);
    
    expect(UserBankPreference::isDiversified($this->user->uuid))->toBeFalse();
});

it('throws exception when removing primary bank', function () {
    $this->service->setupDefaultAllocations($this->user);
    
    expect(fn() => $this->service->removeBank($this->user, 'PAYSERA'))
        ->toThrow(\Exception::class, 'Cannot remove primary bank');
});

it('provides distribution summary for display', function () {
    $this->service->setupDefaultAllocations($this->user);
    
    $summary = $this->service->getDistributionSummary($this->user, 50000); // €500.00
    
    expect($summary)->toHaveKeys([
        'distribution',
        'total_amount',
        'total_insurance_coverage',
        'is_diversified',
        'bank_count',
    ]);
    
    expect($summary['total_amount'])->toBe(50000);
    expect($summary['bank_count'])->toBe(3);
    expect($summary['is_diversified'])->toBeTrue();
    expect($summary['total_insurance_coverage'])->toBe(300000);
});

it('handles errors gracefully in distribution summary', function () {
    // User with no bank preferences
    $newUser = User::factory()->create();
    
    $summary = $this->service->getDistributionSummary($newUser, 10000);
    
    expect($summary)->toHaveKey('error');
    expect($summary['distribution'])->toBeEmpty();
    expect($summary['bank_count'])->toBe(0);
});