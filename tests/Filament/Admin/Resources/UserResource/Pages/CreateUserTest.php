<?php

use App\Filament\Admin\Resources\UserResource\Pages\CreateUser;
use Filament\Resources\Pages\CreateRecord;

it('extends CreateRecord', function () {
    $reflection = new ReflectionClass(CreateUser::class);
    expect($reflection->getParentClass()->getName())->toBe(CreateRecord::class);
});

it('has correct resource', function () {
    $reflection = new ReflectionClass(CreateUser::class);
    $property = $reflection->getProperty('resource');

    expect($property->getValue())->toBe(App\Filament\Admin\Resources\UserResource::class);
});

it('can be instantiated', function () {
    expect(new CreateUser())->toBeInstanceOf(CreateUser::class);
});
