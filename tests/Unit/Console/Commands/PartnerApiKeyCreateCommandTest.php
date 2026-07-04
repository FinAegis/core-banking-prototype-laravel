<?php

declare(strict_types=1);

use App\Console\Commands\PartnerApiKeyCreateCommand;
use Tests\UnitTestCase;

uses(UnitTestCase::class);

it('command class exists and extends Illuminate Command', function (): void {
    $command = new PartnerApiKeyCreateCommand();

    expect($command)->toBeInstanceOf(Illuminate\Console\Command::class);
});

it('has correct command name partner:api-key:create', function (): void {
    $command = new PartnerApiKeyCreateCommand();

    expect($command->getName())->toBe('partner:api-key:create');
});

it('signature definition contains partner:api-key:create {partner}', function (): void {
    $command = new PartnerApiKeyCreateCommand();
    $ref = new ReflectionClass($command);
    $prop = $ref->getProperty('signature');
    $prop->setAccessible(true);
    $signature = (string) $prop->getValue($command);

    expect($signature)->toContain('partner:api-key:create {partner}');
});

it('has a non-empty description', function (): void {
    $command = new PartnerApiKeyCreateCommand();

    expect($command->getDescription())->not->toBeEmpty();
});

it('is not hidden', function (): void {
    $command = new PartnerApiKeyCreateCommand();

    expect($command->isHidden())->toBeFalse();
});
