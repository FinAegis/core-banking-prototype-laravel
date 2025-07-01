<?php

declare(strict_types=1);
use Tests\UnitTestCase;

uses(UnitTestCase::class);

use App\Domain\Account\DataObjects\AccountUuid;
use Illuminate\Support\Str;

it('can create account uuid from string', function () {
    $uuidString = Str::uuid()->toString();
    $accountUuid = new AccountUuid($uuidString);
    
    expect($accountUuid->getUuid())->toBe($uuidString);
});

it('can create account uuid with specific value', function () {
    $uuidString = '12345678-1234-1234-1234-123456789012';
    $accountUuid = new AccountUuid($uuidString);
    
    expect($accountUuid->getUuid())->toBe($uuidString);
});

it('can create new instance with different uuid', function () {
    $originalUuid = Str::uuid()->toString();
    $newUuid = Str::uuid()->toString();
    
    $accountUuid = new AccountUuid($originalUuid);
    $newAccountUuid = $accountUuid->withUuid($newUuid);
    
    expect($accountUuid->getUuid())->toBe($originalUuid);
    expect($newAccountUuid->getUuid())->toBe($newUuid);
    expect($accountUuid)->not->toBe($newAccountUuid);
});

it('can convert to array', function () {
    $uuidString = Str::uuid()->toString();
    $accountUuid = new AccountUuid($uuidString);
    
    $array = $accountUuid->toArray();
    
    expect($array)->toHaveKey('uuid');
    expect($array['uuid'])->toBe($uuidString);
});

it('creates consistent account uuid objects', function () {
    $uuidString = Str::uuid()->toString();
    $accountUuid1 = new AccountUuid($uuidString);
    $accountUuid2 = new AccountUuid($uuidString);
    
    expect($accountUuid1->getUuid())->toBe($accountUuid2->getUuid());
});