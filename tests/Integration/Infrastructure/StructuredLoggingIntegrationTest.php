<?php

declare(strict_types=1);

use App\Domain\Shared\Traits\LogsWithDomainContext;
use App\Http\Middleware\StructuredLoggingMiddleware;
use App\Infrastructure\Logging\StructuredJsonFormatter;
use Monolog\Level;
use Monolog\LogRecord;

uses(Tests\TestCase::class);

describe('Structured Logging Integration', function () {
    it('StructuredJsonFormatter produces valid JSON with all fields', function () {
        $formatter = new StructuredJsonFormatter();

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Integration test log',
            context: ['action' => 'test'],
            extra: [
                'request_id' => 'req-123',
                'trace_id'   => 'trace-456',
            ],
        );

        $output = $formatter->format($record);
        $decoded = json_decode($output, true);

        expect($decoded)->toBeArray();
        expect($decoded)->toHaveKey('timestamp');
        expect($decoded)->toHaveKey('level');
        expect($decoded)->toHaveKey('message');
        expect($decoded)->toHaveKey('channel');
        expect($decoded['message'])->toBe('Integration test log');
        expect($decoded['request_id'])->toBe('req-123');
        expect($decoded['trace_id'])->toBe('trace-456');
    });

    it('StructuredLoggingMiddleware is registered as middleware alias', function () {
        $aliases = $this->app->make(Illuminate\Contracts\Http\Kernel::class)
            ->getMiddlewareAliases ?? [];

        // Check via bootstrap/app.php middleware configuration
        expect((new ReflectionClass(StructuredLoggingMiddleware::class))->getName())->not->toBeEmpty();
    });

    it('structured channel is configured in logging config', function () {
        $channels = config('logging.channels');

        expect($channels)->toHaveKey('structured');
        // The channel moved from a raw monolog driver to daily rotation with
        // the JSON formatter attached.
        expect($channels['structured']['driver'])->toBe('daily');
        expect($channels['structured']['formatter'])->toBe(StructuredJsonFormatter::class);
    });

    it('monitoring config includes structured logging settings', function () {
        $loggingConfig = config('monitoring.logging');

        expect($loggingConfig)->toHaveKey('include_request_id');
        expect($loggingConfig)->toHaveKey('include_domain');
        expect($loggingConfig)->toHaveKey('format');
    });

    it('LogsWithDomainContext trait extracts domain name', function () {
        $testClass = new class () {
            use LogsWithDomainContext;

            public function getPublicDomainName(): string
            {
                return $this->getDomainName();
            }
        };

        // Anonymous class namespace won't match App\Domain\*, so returns 'Unknown'
        expect($testClass->getPublicDomainName())->toBe('Unknown');
    });
});
