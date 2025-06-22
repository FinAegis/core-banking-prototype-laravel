<?php

namespace App\Filament\Admin\Widgets;

use App\Models\BasketAsset;
use App\Models\BasketPerformance;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Illuminate\Support\Carbon;

class BasketPerformanceChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected static ?string $heading = 'Basket Performance';
    protected static ?int $sort = 3;
    protected static ?string $maxHeight = '400px';

    protected int|string|array $columnSpan = 'full';

    public ?string $filter = 'month';

    protected function getData(): array
    {
        $basketCode = $this->filters['basket'] ?? 'GCU';
        $periodType = $this->filters['period'] ?? 'day';
        $limit = match ($periodType) {
            'hour' => 24,
            'day' => 30,
            'week' => 12,
            'month' => 12,
            default => 30,
        };

        $basket = BasketAsset::where('code', $basketCode)->first();
        
        if (!$basket) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $performances = $basket->performances()
            ->where('period_type', $periodType)
            ->orderBy('period_end', 'desc')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values();

        $labels = $performances->map(fn ($p) => $p->period_end->format('M j'));
        $returns = $performances->map(fn ($p) => $p->return_percentage);
        $volatility = $performances->map(fn ($p) => $p->volatility ?? 0);

        return [
            'datasets' => [
                [
                    'label' => 'Return %',
                    'data' => $returns->toArray(),
                    'borderColor' => 'rgb(34, 197, 94)',
                    'backgroundColor' => 'rgba(34, 197, 94, 0.1)',
                    'tension' => 0.1,
                    'fill' => true,
                ],
                [
                    'label' => 'Volatility %',
                    'data' => $volatility->toArray(),
                    'borderColor' => 'rgb(251, 146, 60)',
                    'backgroundColor' => 'rgba(251, 146, 60, 0.1)',
                    'tension' => 0.1,
                    'fill' => true,
                    'yAxisID' => 'y1',
                ],
            ],
            'labels' => $labels->toArray(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'top',
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'display' => true,
                    'title' => [
                        'display' => true,
                        'text' => 'Period',
                    ],
                ],
                'y' => [
                    'display' => true,
                    'position' => 'left',
                    'title' => [
                        'display' => true,
                        'text' => 'Return %',
                    ],
                ],
                'y1' => [
                    'display' => true,
                    'position' => 'right',
                    'title' => [
                        'display' => true,
                        'text' => 'Volatility %',
                    ],
                    'grid' => [
                        'drawOnChartArea' => false,
                    ],
                ],
            ],
            'interaction' => [
                'mode' => 'nearest',
                'axis' => 'x',
                'intersect' => false,
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getFilters(): ?array
    {
        return [
            'basket' => BasketAsset::active()->pluck('name', 'code')->toArray(),
            'period' => [
                'hour' => 'Hourly',
                'day' => 'Daily',
                'week' => 'Weekly',
                'month' => 'Monthly',
            ],
        ];
    }

    public static function canView(): bool
    {
        return auth()->user()?->can('view_basket_performance') ?? true;
    }
}