<?php

namespace App\Filament\Admin\Resources\AccountResource\Widgets;

use App\Domain\Account\Models\Turnover;
use Filament\Widgets\ChartWidget;

class TurnoverTrendChart extends ChartWidget
{
    protected static ?string $heading = 'Turnover Flow Analysis';

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $pollingInterval = '60s';

    public ?string $filter = '6m';

    protected function getData(): array
    {
        $activeFilter = $this->filter;

        $data = $this->getTurnoverData($activeFilter);

        return [
            'datasets' => [
                [
                    'label'           => 'Debit (Outflows)',
                    'data'            => $data->pluck('debit'),
                    'backgroundColor' => 'rgba(239, 68, 68, 0.8)',
                    'borderColor'     => '#ef4444',
                    'borderWidth'     => 2,
                ],
                [
                    'label'           => 'Credit (Inflows)',
                    'data'            => $data->pluck('credit'),
                    'backgroundColor' => 'rgba(16, 185, 129, 0.8)',
                    'borderColor'     => '#10b981',
                    'borderWidth'     => 2,
                ],
                [
                    'label'           => 'Net Flow',
                    'data'            => $data->pluck('net'),
                    'type'            => 'line',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.2)',
                    'borderColor'     => '#3b82f6',
                    'borderWidth'     => 3,
                    'tension'         => 0.4,
                    'fill'            => true,
                ],
            ],
            'labels' => $data->pluck('month'),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks'       => [
                        'callback' => "function(value) { return '$' + value.toLocaleString(); }",
                    ],
                ],
            ],
            'plugins' => [
                'legend' => [
                    'display'  => true,
                    'position' => 'top',
                ],
                'tooltip' => [
                    'callbacks' => [
                        'label' => "function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== null) {
                                label += '$' + context.parsed.y.toLocaleString();
                            }
                            return label;
                        }",
                    ],
                ],
            ],
        ];
    }

    protected function getFilters(): ?array
    {
        return [
            '3m'  => 'Last 3 Months',
            '6m'  => 'Last 6 Months',
            '12m' => 'Last 12 Months',
            '24m' => 'Last 24 Months',
        ];
    }

    private function getTurnoverData(string $period)
    {
        $months = match ($period) {
            '3m'    => 3,
            '6m'    => 6,
            '12m'   => 12,
            '24m'   => 24,
            default => 6,
        };

        $endDate = now()->endOfMonth();
        $startDate = now()->subMonths($months - 1)->startOfMonth();

        // turnovers has one row per account and date (no year/month
        // columns): sum per date in SQL, then bucket the dates by month.
        $dailyTotals = Turnover::query()
            ->select('date')
            ->selectRaw('SUM(debit) as total_debit, SUM(credit) as total_credit')
            ->whereBetween('date', [$startDate->toDateString(), $endDate->toDateString()])
            ->groupBy('date')
            ->get();

        $monthlyTotals = [];
        foreach ($dailyTotals as $day) {
            $month = $day->date->format('Y-m');
            $monthlyTotals[$month] ??= ['debit' => 0, 'credit' => 0];
            $monthlyTotals[$month]['debit'] += (int) $day->getAttribute('total_debit');
            $monthlyTotals[$month]['credit'] += (int) $day->getAttribute('total_credit');
        }

        // Fill in missing months with zeros
        $data = collect();
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $totals = $monthlyTotals[$current->format('Y-m')] ?? ['debit' => 0, 'credit' => 0];

            $debit = $totals['debit'] / 100;
            $credit = $totals['credit'] / 100;

            $data->push(
                [
                    'month'  => $current->format('M Y'),
                    'debit'  => round($debit, 2),
                    'credit' => round($credit, 2),
                    'net'    => round($credit - $debit, 2),
                ]
            );

            $current->addMonth();
        }

        return $data;
    }
}
