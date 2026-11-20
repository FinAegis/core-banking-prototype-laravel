<?php

use App\Domain\Account\Models\Transfer;
use App\Domain\Account\Repositories\TransferRepository;
use Spatie\EventSourcing\AggregateRoots\Exceptions\InvalidEloquentStoredEventModel;
use Spatie\EventSourcing\StoredEvents\Repositories\EloquentStoredEventRepository;

beforeEach(function () {
    $this->repository = new TransferRepository();
});

it('can be instantiated', function () {
    expect($this->repository)->toBeInstanceOf(TransferRepository::class);
});

it('extends EloquentStoredEventRepository', function () {
    expect($this->repository)->toBeInstanceOf(EloquentStoredEventRepository::class);
});

it('uses Transfer model by default', function () {
    $reflection = new ReflectionClass($this->repository);
    $property = $reflection->getProperty('storedEventModel');

    expect($property->getValue($this->repository))->toBe(Transfer::class);
});

it('can be constructed with custom model', function () {
    $repository = new TransferRepository(Transfer::class);

    expect($repository)->toBeInstanceOf(TransferRepository::class);
});

it('throws exception for invalid model', function () {
    expect(function () {
        new TransferRepository(stdClass::class);
    })->toThrow(InvalidEloquentStoredEventModel::class);
});

it('validates model extends EloquentStoredEvent', function () {
    $exception = null;

    try {
        new TransferRepository(stdClass::class);
    } catch (InvalidEloquentStoredEventModel $e) {
        $exception = $e;
    }

    expect($exception)->not->toBeNull();
    expect($exception->getMessage())->toContain('must extend EloquentStoredEvent');
});
