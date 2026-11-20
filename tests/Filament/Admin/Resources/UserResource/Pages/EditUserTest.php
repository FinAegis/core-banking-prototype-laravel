<?php

use App\Filament\Admin\Resources\UserResource\Pages\EditUser;
use Filament\Resources\Pages\EditRecord;

it('extends EditRecord', function () {
    $reflection = new ReflectionClass(EditUser::class);
    expect($reflection->getParentClass()->getName())->toBe(EditRecord::class);
});

it('has correct resource', function () {
    $reflection = new ReflectionClass(EditUser::class);
    $property = $reflection->getProperty('resource');

    expect($property->getValue())->toBe(App\Filament\Admin\Resources\UserResource::class);
});

it('can be instantiated', function () {
    expect(new EditUser())->toBeInstanceOf(EditUser::class);
});
