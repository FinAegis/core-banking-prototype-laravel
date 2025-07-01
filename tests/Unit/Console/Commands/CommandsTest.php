<?php

declare(strict_types=1);
use Tests\UnitTestCase;

uses(UnitTestCase::class);

use App\Console\Commands\CacheWarmup;
use App\Console\Commands\CreateSnapshot;

it('cache warmup command has correct signature', function () {
    $command = new CacheWarmup();
    
    expect($command->getName())->toBe('cache:warmup');
    expect($command->getDescription())->toContain('Warm up');
});

it('create snapshot command has correct signature', function () {
    $command = new CreateSnapshot();
    
    expect($command->getName())->toBe('snapshot:create');
    expect($command->getDescription())->toContain('Create snapshots');
});

it('commands extend laravel command class', function () {
    $cacheCommand = new CacheWarmup();
    $snapshotCommand = new CreateSnapshot();
    
    expect($cacheCommand)->toBeInstanceOf(Illuminate\Console\Command::class);
    expect($snapshotCommand)->toBeInstanceOf(Illuminate\Console\Command::class);
});

it('commands have proper visibility', function () {
    $cacheCommand = new CacheWarmup();
    $snapshotCommand = new CreateSnapshot();
    
    expect($cacheCommand->isHidden())->toBeFalse();
    expect($snapshotCommand->isHidden())->toBeFalse();
});

it('commands have proper names and descriptions', function () {
    $cacheCommand = new CacheWarmup();
    $snapshotCommand = new CreateSnapshot();
    
    expect($cacheCommand->getName())->not->toBeEmpty();
    expect($snapshotCommand->getName())->not->toBeEmpty();
    expect($cacheCommand->getDescription())->not->toBeEmpty();
    expect($snapshotCommand->getDescription())->not->toBeEmpty();
});