<?php

namespace Tests\Domain\Account\DataObjects;

use App\Domain\Account\DataObjects\DataObject;

// Create a concrete implementation for testing
readonly class TestDataObject extends DataObject 
{
    public function __construct(
        public string $name,
        public int $value
    ) {}
    
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'value' => $this->value,
        ];
    }
}

it('can create from array', function () {
    $data = ['name' => 'test', 'value' => 42];
    
    $object = TestDataObject::fromArray($data);
    
    expect($object)->toBeInstanceOf(TestDataObject::class);
    expect($object->name)->toBe('test');
    expect($object->value)->toBe(42);
});

it('can convert to array', function () {
    $object = new TestDataObject('test', 42);
    
    $array = $object->toArray();
    
    expect($array)->toBe([
        'name' => 'test',
        'value' => 42,
    ]);
});

it('implements DataObjectContract', function () {
    expect(TestDataObject::class)->toImplement(JustSteveKing\DataObjects\Contracts\DataObjectContract::class);
});

it('is readonly', function () {
    $reflection = new ReflectionClass(TestDataObject::class);
    expect($reflection->isReadOnly())->toBeTrue();
});