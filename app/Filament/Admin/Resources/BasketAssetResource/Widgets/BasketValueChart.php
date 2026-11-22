<?php

namespace App\Filament\Admin\Resources\BasketAssetResource\Widgets;

use App\Domain\Basket\Models\BasketAsset;
use App\Domain\Basket\Models\BasketValue;
use Filament\Widgets\ChartWidget;

class BasketValueChart extends ChartWidget
{
    protected static ?string $heading = 'Basket Value History';

    protected static ?string $pollingInterval = '60s';

    public ?BasketAsset $record = null;

    protected function getData(): array
    {
        if (! $this->record) {
            return [
                'datasets' => [],
                'labels'   => [],
            ];
        }

        $days = 30;
        $endDate = now();
        $startDate = now()->subDays($days);

        $values = BasketValue::where('basket_asset_code', $this->record->code)
            ->where('calculated_at', '>=', $startDate)
            ->where('calculated_at', '<=', $endDate)
            ->orderBy('calculated_at')
            ->get();

        // Fill in missing days with the last known value
        $labels = [];
        $data = [];
        $lastValue = null;

        for ($i = 0; $i <= $days; $i++) {
            $date = $startDate->copy()->addDays($i);
            $labels[] = $date->format('M j');

            $dayValue = $values->first(
                function ($value) use ($date) {
                    return $value->calculated_at->isSameDay($date);
                }
            );

            if ($dayValue) {
                $lastValue = $dayValue->value;
            }

            $data[] = $lastValue;
        }

        return [
            'datasets' => [
                [
                    'label'           => 'Value (USD)',
                    'data'            => $data,
                    'borderColor'     => 'rgb(75, 192, 192)',
                    'backgroundColor' => 'rgba(75, 192, 192, 0.1)',
                    'tension'         => 0.1,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => false,
                    'ticks'       => [
                        'callback' => "function(value) { return '$' + value.toFixed(2); }",
                    ],
                ],
            ],
        ];
    }
}
