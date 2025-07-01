<?php

declare(strict_types=1);

use App\Values\EventQueues;
use App\Values\UserRoles;
use Tests\UnitTestCase;

uses(UnitTestCase::class);

it('event queues enum has correct values', function () {
    expect(EventQueues::EVENTS->value)->toBe('events');
    expect(EventQueues::LEDGER->value)->toBe('ledger');
    expect(EventQueues::TRANSACTIONS->value)->toBe('transactions');
    expect(EventQueues::TRANSFERS->value)->toBe('transfers');
});

it('user roles enum has correct values', function () {
    expect(UserRoles::BUSINESS->value)->toBe('business');
    expect(UserRoles::PRIVATE->value)->toBe('private');
    expect(UserRoles::ADMIN->value)->toBe('admin');
});

it('can get all event queue values', function () {
    $cases = EventQueues::cases();
    
    expect($cases)->toHaveCount(4);
    expect(collect($cases)->pluck('value')->toArray())
        ->toBe(['events', 'ledger', 'transactions', 'transfers']);
});

it('can get all user role values', function () {
    $cases = UserRoles::cases();
    
    expect($cases)->toHaveCount(3);
    expect(collect($cases)->pluck('value')->toArray())
        ->toBe(['business', 'private', 'admin']);
});

it('enums are backed by strings', function () {
    expect(EventQueues::EVENTS)->toBeInstanceOf(BackedEnum::class);
    expect(UserRoles::BUSINESS)->toBeInstanceOf(BackedEnum::class);
});

it('enums have proper type', function () {
    expect(EventQueues::EVENTS->value)->toBeString();
    expect(UserRoles::BUSINESS->value)->toBeString();
});