<?php

declare(strict_types=1);

use App\Domain\Monitoring\Services\ProjectorHealthService;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('ProjectorHealthService', function () {
    it('can be instantiated', function () {
        $service = new ProjectorHealthService();

        expect($service)->toBeInstanceOf(ProjectorHealthService::class);
    });

    it('returns health status structure', function () {
        $service = new ProjectorHealthService();
        $status = $service->getAllProjectorStatus();

        expect($status)->toHaveKeys([
            'total_projectors',
            'healthy',
            'stale',
            'failed',
            'projectors',
            'checked_at',
        ]);
        expect($status['projectors'])->toBeArray();
    });

    it('detects stale projectors returns collection', function () {
        $service = new ProjectorHealthService();
        $stale = $service->detectStaleProjectors();

        expect($stale)->toBeInstanceOf(Illuminate\Support\Collection::class);
    });
});

describe('ProjectorHealthCheckCommand', function () {
    it('has correct command signature', function () {
        $command = new App\Console\Commands\ProjectorHealthCheckCommand();
        $definition = $command->getDefinition();

        expect($definition->hasOption('domain'))->toBeTrue();
        expect($definition->hasOption('stale-only'))->toBeTrue();
    });
});
