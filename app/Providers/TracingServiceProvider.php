<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Monitoring\Sagas\DistributedTracingSaga;
use App\Domain\Monitoring\Services\TracingService;
use App\Http\Middleware\TracingMiddleware;
use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SemConv\ResourceAttributes;
use Spatie\EventSourcing\Facades\Projectionist;

class TracingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register OpenTelemetry tracer
        $this->app->singleton(TracerInterface::class, function ($app) {
            if (! config('monitoring.tracing.enabled', false)) {
                // Return a no-op tracer if tracing is disabled
                return \OpenTelemetry\API\Trace\NoopTracer::getInstance();
            }

            // Create resource info
            $resource = ResourceInfoFactory::emptyResource()->merge(
                ResourceInfo::create(Attributes::create([
                    ResourceAttributes::SERVICE_NAME    => config('app.name', 'finaegis'),
                    ResourceAttributes::SERVICE_VERSION => config('app.version', '1.0.0'),
                    'deployment.environment'            => config('app.env', 'production'),
                ]))
            );

            // Create OTLP exporter if configured
            $endpoint = config('monitoring.tracing.otlp_endpoint');
            if ($endpoint) {
                $transport = (new OtlpHttpTransportFactory())->create(
                    $endpoint,
                    'application/x-protobuf',
                    config('monitoring.tracing.otlp_headers', [])
                );

                $exporter = new SpanExporter($transport);
                $processor = new SimpleSpanProcessor($exporter);

                $tracerProvider = TracerProvider::builder()
                    ->addSpanProcessor($processor)
                    ->setResource($resource)
                    ->build();
            } else {
                // Use default in-memory provider for testing
                $tracerProvider = TracerProvider::builder()
                    ->setResource($resource)
                    ->build();
            }

            return $tracerProvider->getTracer(
                config('app.name', 'finaegis'),
                config('app.version', '1.0.0')
            );
        });

        // Register tracing service
        $this->app->singleton(TracingService::class, function ($app) {
            return new TracingService(
                $app->make(TracerInterface::class)
            );
        });

        // Register tracing middleware
        $this->app->singleton(TracingMiddleware::class, function ($app) {
            return new TracingMiddleware(
                $app->make(TracingService::class)
            );
        });

        // Register distributed tracing saga
        $this->app->singleton(DistributedTracingSaga::class);
    }

    public function boot(): void
    {
        // Register event handlers for the saga
        Projectionist::addReactor(DistributedTracingSaga::class);

        // Add configuration
        $this->publishes([
            __DIR__ . '/../../config/monitoring.php' => config_path('monitoring.php'),
        ], 'monitoring-config');

        // Register middleware alias
        $this->app['router']->aliasMiddleware('tracing', TracingMiddleware::class);
    }
}
