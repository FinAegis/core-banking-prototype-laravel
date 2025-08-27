<?php

declare(strict_types=1);

use JustSteveKing\DataObjects\Contracts\DataObjectContract;
use ReflectionClass;
use Tests\Domain\Account\DataObjects\TestDataObject;

test('can create from array', function () {
    $data = ['name' => 'test', 'value' => 42];

    $object = TestDataObject::fromArray($data);

    expect($object)->toBeInstanceOf(TestDataObject::class);
    expect($object->name)->toBe('test');
    expect($object->value)->toBe(42);
});

test('can convert to array', function () {
    $object = new TestDataObject('test', 42);

    $array = $object->toArray();

    expect($array)->toBe([
        'name'  => 'test',
        'value' => 42,
    ]);
});

test('implements DataObjectContract', function () {
    expect(TestDataObject::class)->toImplement(DataObjectContract::class);
});

test('is readonly', function () {
    $reflection = new ReflectionClass(TestDataObject::class);
    expect($reflection->isReadOnly())->toBeTrue();
});
