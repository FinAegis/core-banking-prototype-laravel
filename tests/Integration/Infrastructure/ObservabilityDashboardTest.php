<?php

declare(strict_types=1);

use App\Domain\Monitoring\Events\Broadcasting\MonitoringMetricsUpdated;
use App\Filament\Admin\Pages\EventStoreDashboard;
use App\Filament\Admin\Widgets\AggregateHealthWidget;
use App\Filament\Admin\Widgets\DomainHealthWidget;
use App\Filament\Admin\Widgets\EventStoreStatsWidget;
use App\Filament\Admin\Widgets\EventStoreThroughputWidget;
use App\Filament\Admin\Widgets\SystemMetricsWidget;

uses(Tests\TestCase::class);

describe('Observability Dashboard Integration', function () {
    it('EventStoreDashboard page class exists and is properly configured', function () {
        $reflection = new ReflectionClass(EventStoreDashboard::class);

        expect($reflection->hasMethod('getHeaderWidgets'))->toBeTrue();

        $navIcon = $reflection->getProperty('navigationIcon');
        expect($navIcon->getValue())->toBe('heroicon-o-circle-stack');

        $navGroup = $reflection->getProperty('navigationGroup');
        expect($navGroup->getValue())->toBe('System');
    });

    it('all event store widgets exist and extend correct base classes', function () {
        $statsWidget = new ReflectionClass(EventStoreStatsWidget::class);
        $throughputWidget = new ReflectionClass(EventStoreThroughputWidget::class);
        $aggregateWidget = new ReflectionClass(AggregateHealthWidget::class);
        $systemWidget = new ReflectionClass(SystemMetricsWidget::class);
        $domainWidget = new ReflectionClass(DomainHealthWidget::class);

        expect($statsWidget->isSubclassOf(Filament\Widgets\StatsOverviewWidget::class))->toBeTrue();
        expect($throughputWidget->isSubclassOf(Filament\Widgets\ChartWidget::class))->toBeTrue();
        expect($aggregateWidget->isSubclassOf(Filament\Widgets\StatsOverviewWidget::class))->toBeTrue();
        expect($systemWidget->isSubclassOf(Filament\Widgets\StatsOverviewWidget::class))->toBeTrue();
        expect($domainWidget->isSubclassOf(Filament\Widgets\StatsOverviewWidget::class))->toBeTrue();
    });

    it('monitoring WebSocket channel is configured', function () {
        $wsConfig = config('websocket.channels');

        expect($wsConfig)->toHaveKey('monitoring');
        expect($wsConfig['monitoring'])->toHaveKey('events');
        expect($wsConfig['monitoring']['events'])->toContain('metrics.updated');
    });

    it('MonitoringMetricsUpdated broadcast event is properly structured', function () {
        $event = new MonitoringMetricsUpdated([
            'cpu'    => 45.2,
            'memory' => 67.8,
        ]);

        expect($event->broadcastOn())->toBeArray();
        expect($event->broadcastOn()[0])->toBeInstanceOf(Illuminate\Broadcasting\Channel::class);
        expect($event->broadcastAs())->toBe('metrics.updated');
        expect($event->broadcastWith())->toHaveKey('metrics');
        expect($event->broadcastWith())->toHaveKey('timestamp');
        expect($event->broadcastWith()['metrics'])->toHaveKey('cpu');
        expect($event->broadcastWith()['metrics'])->toHaveKey('memory');
    });

    it('event store dashboard view file exists', function () {
        $viewPath = resource_path('views/filament/admin/pages/event-store-dashboard.blade.php');

        expect(file_exists($viewPath))->toBeTrue();
    });
});
