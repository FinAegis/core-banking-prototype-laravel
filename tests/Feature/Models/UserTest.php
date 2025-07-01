<?php

use App\Models\User;
// No need for manual imports - Pest.php handles TestCase and RefreshDatabase for Feature tests

it('can get route key name', function () {
    $user = new User();
    
    expect($user->getRouteKeyName())->toBe('uuid');
});

it('can instantiate user model', function () {
    $user = new User();
    
    expect($user)->toBeInstanceOf(User::class);
});

it('has correct fillable attributes', function () {
    $user = new User();
    
    expect($user->getFillable())->toContain('name');
    expect($user->getFillable())->toContain('email');
    expect($user->getFillable())->toContain('password');
});

it('has correct hidden attributes', function () {
    $user = new User();
    
    expect($user->getHidden())->toContain('password');
    expect($user->getHidden())->toContain('remember_token');
    expect($user->getHidden())->toContain('two_factor_recovery_codes');
    expect($user->getHidden())->toContain('two_factor_secret');
});

it('has correct appended attributes', function () {
    $user = new User();
    
    expect($user->getAppends())->toContain('profile_photo_url');
});

it('uses uuid for unique ids', function () {
    $user = new User();
    
    expect($user->uniqueIds())->toBe(['uuid']);
});

it('has correct casts', function () {
    $user = new User();
    
    $casts = $user->getCasts();
    expect($casts)->toHaveKey('email_verified_at');
    expect($casts)->toHaveKey('password');
    expect($casts['password'])->toBe('hashed');
});

it('can check if user can access panel', function () {
    $adminUser = User::factory()->withAdminRole()->create();
    $regularUser = User::factory()->create(); // Uses default private role
    
    $panel = app(\Filament\Panel::class);
    
    expect($adminUser->canAccessPanel($panel))->toBeTrue();
    expect($regularUser->canAccessPanel($panel))->toBeFalse();
});