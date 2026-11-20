<?php

use App\Filament\Admin\Resources\AccountResource\Pages\CreateAccount;
use Filament\Resources\Pages\CreateRecord;

it('extends CreateRecord', function () {
    $reflection = new ReflectionClass(CreateAccount::class);
    expect($reflection->getParentClass()->getName())->toBe(CreateRecord::class);
});

it('has correct resource', function () {
    $reflection = new ReflectionClass(CreateAccount::class);
    $property = $reflection->getProperty('resource');

    expect($property->getValue())->toBe(App\Filament\Admin\Resources\AccountResource::class);
});

it('can be instantiated', function () {
    expect(new CreateAccount())->toBeInstanceOf(CreateAccount::class);
});
