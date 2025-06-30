<?php

use App\Domain\Payment\Repositories\PaymentWithdrawalRepository;
use App\Models\PaymentWithdrawal;
use Spatie\EventSourcing\AggregateRoots\Exceptions\InvalidEloquentStoredEventModel;

it('can be instantiated with valid model', function () {
    $repository = new PaymentWithdrawalRepository();
    
    expect($repository)->toBeInstanceOf(PaymentWithdrawalRepository::class);
});

it('throws exception for invalid stored event model', function () {
    expect(function () {
        new PaymentWithdrawalRepository(storedEventModel: stdClass::class);
    })->toThrow(InvalidEloquentStoredEventModel::class);
});

it('uses PaymentWithdrawal model by default', function () {
    $repository = new PaymentWithdrawalRepository();
    
    // Use reflection to access the protected property
    $reflection = new ReflectionClass($repository);
    $property = $reflection->getProperty('storedEventModel');
    $property->setAccessible(true);
    
    expect($property->getValue($repository))->toBe(PaymentWithdrawal::class);
});

it('can store and retrieve events', function () {
    $repository = new PaymentWithdrawalRepository();
    $aggregateUuid = Str::uuid()->toString();
    
    // Create a test event
    $eventData = [
        'aggregate_uuid' => $aggregateUuid,
        'aggregate_version' => 1,
        'event_version' => 1,
        'event_class' => 'withdrawal_initiated',
        'event_properties' => json_encode([
            'accountUuid' => Str::uuid()->toString(),
            'amount' => 5000,
            'currency' => 'USD',
            'reference' => 'WD-123',
            'bankAccountNumber' => '****1234',
            'bankRoutingNumber' => '123456789',
            'bankAccountName' => 'John Doe',
            'metadata' => []
        ]),
        'meta_data' => json_encode([
            'aggregate_uuid' => $aggregateUuid
        ]),
        'created_at' => now()
    ];
    
    // Store the event
    PaymentWithdrawal::create($eventData);
    
    // Retrieve events for the aggregate
    $events = PaymentWithdrawal::where('aggregate_uuid', $aggregateUuid)->get();
    
    expect($events)->toHaveCount(1);
    expect($events->first()->event_class)->toBe('withdrawal_initiated');
});