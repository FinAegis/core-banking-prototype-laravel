<?php

use App\Filament\Admin\Resources\AccountResource\Pages\EditAccount;
use Filament\Resources\Pages\EditRecord;

it('extends EditRecord', function () {
    $reflection = new ReflectionClass(EditAccount::class);
    expect($reflection->getParentClass()->getName())->toBe(EditRecord::class);
});

it('has correct resource', function () {
    $reflection = new ReflectionClass(EditAccount::class);
    $property = $reflection->getProperty('resource');

    expect($property->getValue())->toBe(App\Filament\Admin\Resources\AccountResource::class);
});

it('can be instantiated', function () {
    expect(new EditAccount())->toBeInstanceOf(EditAccount::class);
});
