<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Domain\Custodian\Services\CustodianHealthMonitor;
use App\Filament\Admin\Traits\WidgetRespectsModuleVisibility;
use Filament\Widgets\Widget;

class BankHealthMonitorWidget extends Widget
{
    use WidgetRespectsModuleVisibility;

    protected static ?string $adminModule = 'Banking';

    protected static string $view = 'filament.admin.widgets.bank-health-monitor-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 3;

    public ?array $healthData = null;

    protected function getViewData(): array
    {
        $healthMonitor = app(CustodianHealthMonitor::class);
        $this->healthData = $healthMonitor->getAllCustodiansHealth();

        return [
            'healthData' => $this->healthData,
            'lastUpdate' => now()->format('Y-m-d H:i:s'),
        ];
    }

    public function getPollingInterval(): ?string
    {
        return '10s'; // Poll every 10 seconds for real-time updates
    }
}
