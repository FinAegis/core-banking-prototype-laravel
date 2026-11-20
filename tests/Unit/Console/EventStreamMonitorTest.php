<?php

declare(strict_types=1);

use App\Console\Commands\EventStreamMonitorCommand;

describe('EventStreamMonitorCommand', function () {
    it('class exists', function () {
        expect((new ReflectionClass(EventStreamMonitorCommand::class))->getName())->not->toBeEmpty();
    });

    it('has the expected command name constant', function () {
        $reflection = new ReflectionClass(EventStreamMonitorCommand::class);
        $signature = $reflection->getProperty('signature');

        // Get default value from property
        $defaultValue = $reflection->getDefaultProperties()['signature'] ?? '';
        expect($defaultValue)->toContain('event-stream:monitor');
    });
});
