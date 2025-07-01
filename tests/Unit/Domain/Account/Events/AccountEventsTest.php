<?php

declare(strict_types=1);
use Tests\UnitTestCase;

uses(UnitTestCase::class);

use App\Domain\Account\Events\AccountCreated;
use App\Domain\Account\Events\AccountDeleted;
use App\Domain\Account\Events\AccountFrozen;
use App\Domain\Account\Events\AccountUnfrozen;
use App\Domain\Account\DataObjects\Account;
use App\Domain\Account\DataObjects\AccountUuid;
use Illuminate\Support\Str;

it('can create account created event', function () {
    $accountUuid = new AccountUuid(Str::uuid()->toString());
    $account = new Account('Test Account', Str::uuid()->toString(), $accountUuid->getUuid());
    
    $event = new AccountCreated($account);
    
    expect($event->account)->toBe($account);
    expect($event->queue)->toBe('ledger');
});

it('can create account deleted event', function () {
    $event = new AccountDeleted();
    
    expect($event->queue)->toBe('ledger');
});

it('can create account frozen event with reason', function () {
    $reason = 'Suspicious activity detected';
    
    $event = new AccountFrozen($reason);
    
    expect($event->reason)->toBe($reason);
    expect($event->authorizedBy)->toBeNull();
    expect($event->queue)->toBe('ledger');
});

it('can create account frozen event with reason and authorized by', function () {
    $reason = 'Compliance investigation';
    $authorizedBy = 'admin@example.com';
    
    $event = new AccountFrozen($reason, $authorizedBy);
    
    expect($event->reason)->toBe($reason);
    expect($event->authorizedBy)->toBe($authorizedBy);
    expect($event->queue)->toBe('ledger');
});

it('can create account unfrozen event', function () {
    $reason = 'Investigation completed';
    
    $event = new AccountUnfrozen($reason);
    
    expect($event->reason)->toBe($reason);
    expect($event->authorizedBy)->toBeNull();
    expect($event->queue)->toBe('ledger');
});

it('can create account unfrozen event with authorized by', function () {
    $reason = 'Manual review passed';
    $authorizedBy = 'manager@example.com';
    
    $event = new AccountUnfrozen($reason, $authorizedBy);
    
    expect($event->reason)->toBe($reason);
    expect($event->authorizedBy)->toBe($authorizedBy);
    expect($event->queue)->toBe('ledger');
});

it('events extend ShouldBeStored', function () {
    $account = new Account('Test Account', Str::uuid()->toString(), Str::uuid()->toString());
    $event = new AccountCreated($account);
    
    expect($event)->toBeInstanceOf(Spatie\EventSourcing\StoredEvents\ShouldBeStored::class);
});