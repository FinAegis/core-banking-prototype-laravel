<?php

use App\Domain\Account\Repositories\TransferSnapshotRepository;
use App\Domain\Account\Snapshots\TransferSnapshot;

it('extends EloquentSnapshotRepository', function () {
    $reflection = new ReflectionClass(TransferSnapshotRepository::class);
    expect($reflection->getParentClass()->getName())->toBe('Spatie\EventSourcing\Snapshots\EloquentSnapshotRepository');
});

it('can be instantiated with default snapshot model', function () {
    $repository = new TransferSnapshotRepository();

    $reflection = new ReflectionClass($repository);
    $property = $reflection->getProperty('snapshotModel');

    expect($property->getValue($repository))->toBe(TransferSnapshot::class);
});

it('can be instantiated with custom snapshot model', function () {
    $repository = new TransferSnapshotRepository(TransferSnapshot::class);

    $reflection = new ReflectionClass($repository);
    $property = $reflection->getProperty('snapshotModel');

    expect($property->getValue($repository))->toBe(TransferSnapshot::class);
});

it('throws exception for invalid snapshot model', function () {
    expect(fn () => new TransferSnapshotRepository('InvalidClass'))
        ->toThrow(Error::class);
});

it('is final class', function () {
    $reflection = new ReflectionClass(TransferSnapshotRepository::class);
    expect($reflection->isFinal())->toBeTrue();
});
