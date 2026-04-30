<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Domain\Monitoring\Services\EventStoreService;
use App\Filament\Admin\Traits\WidgetRespectsModuleVisibility;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Cache;

class EventStoreThroughputWidget extends ChartWidget
{
    use WidgetRespectsModuleVisibility;

    protected static ?string $adminModule = 'System';

    protected static ?string $heading = 'Event Throughput (Events/Minute)';

    protected static ?string $pollingInterval = '10s';

    protected static ?int $sort = 2;

    protected static ?string $maxHeight = '300px';

    protected function getData(): array
    {
        $data = Cache::remember('event_store_throughput_widget', 10, function () {
            $service = app(EventStoreService::class);

            return $service->getEventThroughput('stored_events', 60);
        });

        $labels = array_keys($data);
        $values = array_values($data);

        // Show at most last 30 data points
        if (count($labels) > 30) {
            $labels = array_slice($labels, -30);
            $values = array_slice($values, -30);
        }

        // Shorten labels to just time portion
        $labels = array_map(function (string $label) {
            $parts = explode(' ', $label);

            return $parts[1] ?? $label;
        }, $labels);

        return [
            'datasets' => [
                [
                    'label'           => 'Events/min',
                    'data'            => $values,
                    'borderColor'     => '#3b82f6',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill'            => true,
                    'tension'         => 0.4,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
