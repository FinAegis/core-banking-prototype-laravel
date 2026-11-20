<?php

declare(strict_types=1);

use App\Domain\Monitoring\Events\Broadcasting\MonitoringMetricsUpdated;
use App\Filament\Admin\Widgets\AggregateHealthWidget;
use App\Filament\Admin\Widgets\EventStoreStatsWidget;
use App\Filament\Admin\Widgets\EventStoreThroughputWidget;
use App\Filament\Admin\Widgets\SystemMetricsWidget;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\StatsOverviewWidget;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

describe('EventStoreStatsWidget', function () {
    it('extends StatsOverviewWidget', function () {
        $reflection = new ReflectionClass(EventStoreStatsWidget::class);
        expect($reflection->isSubclassOf(StatsOverviewWidget::class))->toBeTrue();
    });

    it('has 30s polling interval', function () {
        $reflection = new ReflectionClass(EventStoreStatsWidget::class);
        $property = $reflection->getProperty('pollingInterval');
        expect($property->getValue())->toBe('30s');
    });

    it('has getStats method', function () {
        expect((new ReflectionClass(EventStoreStatsWidget::class))->hasMethod('getStats'))->toBeTrue();
    });

    it('has sort order 1', function () {
        $reflection = new ReflectionClass(EventStoreStatsWidget::class);
        $property = $reflection->getProperty('sort');
        expect($property->getValue())->toBe(1);
    });
});

describe('EventStoreThroughputWidget', function () {
    it('extends ChartWidget', function () {
        $reflection = new ReflectionClass(EventStoreThroughputWidget::class);
        expect($reflection->isSubclassOf(ChartWidget::class))->toBeTrue();
    });

    it('has 10s polling interval', function () {
        $reflection = new ReflectionClass(EventStoreThroughputWidget::class);
        $property = $reflection->getProperty('pollingInterval');
        expect($property->getValue())->toBe('10s');
    });

    it('has correct heading', function () {
        $reflection = new ReflectionClass(EventStoreThroughputWidget::class);
        $property = $reflection->getProperty('heading');
        expect($property->getValue())->toBe('Event Throughput (Events/Minute)');
    });

    it('has getData method', function () {
        expect((new ReflectionClass(EventStoreThroughputWidget::class))->hasMethod('getData'))->toBeTrue();
    });

    it('has getType method', function () {
        expect((new ReflectionClass(EventStoreThroughputWidget::class))->hasMethod('getType'))->toBeTrue();
    });
});

describe('AggregateHealthWidget', function () {
    it('extends StatsOverviewWidget', function () {
        $reflection = new ReflectionClass(AggregateHealthWidget::class);
        expect($reflection->isSubclassOf(StatsOverviewWidget::class))->toBeTrue();
    });

    it('has 60s polling interval', function () {
        $reflection = new ReflectionClass(AggregateHealthWidget::class);
        $property = $reflection->getProperty('pollingInterval');
        expect($property->getValue())->toBe('60s');
    });

    it('has getStats method', function () {
        expect((new ReflectionClass(AggregateHealthWidget::class))->hasMethod('getStats'))->toBeTrue();
    });
});

describe('SystemMetricsWidget', function () {
    it('extends StatsOverviewWidget', function () {
        $reflection = new ReflectionClass(SystemMetricsWidget::class);
        expect($reflection->isSubclassOf(StatsOverviewWidget::class))->toBeTrue();
    });

    it('has 10s polling interval', function () {
        $reflection = new ReflectionClass(SystemMetricsWidget::class);
        $property = $reflection->getProperty('pollingInterval');
        expect($property->getValue())->toBe('10s');
    });

    it('has getStats method', function () {
        expect((new ReflectionClass(SystemMetricsWidget::class))->hasMethod('getStats'))->toBeTrue();
    });
});

describe('MonitoringMetricsUpdated', function () {
    it('implements ShouldBroadcast', function () {
        $reflection = new ReflectionClass(MonitoringMetricsUpdated::class);
        expect($reflection->implementsInterface(ShouldBroadcast::class))->toBeTrue();
    });

    it('has metrics and source properties', function () {
        $event = new MonitoringMetricsUpdated(['cpu' => 45.0], 'test');
        expect($event->metrics)->toBe(['cpu' => 45.0]);
        expect($event->source)->toBe('test');
    });

    it('has broadcastOn method', function () {
        $event = new MonitoringMetricsUpdated(['test' => 1]);
        $channels = $event->broadcastOn();
        expect($channels)->toBeArray();
        expect($channels[0])->toBeInstanceOf(Channel::class);
        expect($channels[0]->name)->toBe('monitoring');
    });

    it('broadcasts as metrics.updated', function () {
        $event = new MonitoringMetricsUpdated(['test' => 1]);
        expect($event->broadcastAs())->toBe('metrics.updated');
    });

    it('includes metrics in broadcast data', function () {
        $metrics = ['cpu' => 45.0, 'memory' => 78.5];
        $event = new MonitoringMetricsUpdated($metrics, 'test-source');
        $data = $event->broadcastWith();
        expect($data)->toHaveKey('metrics');
        expect($data)->toHaveKey('source');
        expect($data)->toHaveKey('timestamp');
        expect($data['metrics'])->toBe($metrics);
        expect($data['source'])->toBe('test-source');
    });

    it('defaults source to system', function () {
        $event = new MonitoringMetricsUpdated(['test' => 1]);
        expect($event->source)->toBe('system');
    });
});
