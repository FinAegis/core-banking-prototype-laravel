<?php

use App\Domain\Payment\Repositories\PaymentDepositRepository;
use App\Models\PaymentDeposit;
use Spatie\EventSourcing\AggregateRoots\Exceptions\InvalidEloquentStoredEventModel;

it('can be instantiated with valid model', function () {
    $repository = new PaymentDepositRepository();
    
    expect($repository)->toBeInstanceOf(PaymentDepositRepository::class);
});

it('throws exception for invalid stored event model', function () {
    expect(function () {
        new PaymentDepositRepository(storedEventModel: stdClass::class);
    })->toThrow(InvalidEloquentStoredEventModel::class);
});

it('uses PaymentDeposit model by default', function () {
    $repository = new PaymentDepositRepository();
    
    // Use reflection to access the protected property
    $reflection = new ReflectionClass($repository);
    $property = $reflection->getProperty('storedEventModel');
    $property->setAccessible(true);
    
    expect($property->getValue($repository))->toBe(PaymentDeposit::class);
});

it('can store and retrieve events', function () {
    $repository = new PaymentDepositRepository();
    $aggregateUuid = Str::uuid()->toString();
    
    // Create a test event
    $eventData = [
        'aggregate_uuid' => $aggregateUuid,
        'aggregate_version' => 1,
        'event_version' => 1,
        'event_class' => 'deposit_initiated',
        'event_properties' => json_encode([
            'accountUuid' => Str::uuid()->toString(),
            'amount' => 10000,
            'currency' => 'USD',
            'reference' => 'TEST-123',
            'externalReference' => 'pi_test_123',
            'paymentMethod' => 'card',
            'paymentMethodType' => 'visa',
            'metadata' => []
        ]),
        'meta_data' => json_encode([
            'aggregate_uuid' => $aggregateUuid
        ]),
        'created_at' => now()
    ];
    
    // Store the event
    PaymentDeposit::create($eventData);
    
    // Retrieve events for the aggregate
    $events = PaymentDeposit::where('aggregate_uuid', $aggregateUuid)->get();
    
    expect($events)->toHaveCount(1);
    expect($events->first()->event_class)->toBe('deposit_initiated');
});