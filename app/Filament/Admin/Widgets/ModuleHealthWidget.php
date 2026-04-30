<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Filament\Admin\Traits\WidgetRespectsModuleVisibility;
use App\Infrastructure\Domain\DomainManager;
use App\Infrastructure\Domain\Enums\DomainStatus;
use App\Infrastructure\Domain\Enums\DomainType;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class ModuleHealthWidget extends BaseWidget
{
    use WidgetRespectsModuleVisibility;

    protected static ?string $adminModule = 'System';

    protected static ?string $pollingInterval = '60s';

    protected static ?int $sort = 3;

    protected function getStats(): array
    {
        $data = Cache::remember('module_health_widget_stats', 60, function () {
            return $this->computeStats();
        });

        return $this->buildStats($data);
    }

    /**
     * Compute raw statistics from the domain system.
     *
     * @return array<string, mixed>
     */
    private function computeStats(): array
    {
        $domainManager = app(DomainManager::class);

        // Count all domain directories (total modules, regardless of manifest)
        $basePath = base_path('app/Domain');
        $totalModules = File::isDirectory($basePath)
            ? count(File::directories($basePath))
            : 0;

        // Load manifests and domain info
        $manifests = $domainManager->loadAllManifests();
        $domains = $domainManager->getAvailableDomains();

        $withManifests = count($manifests);

        // Count by status
        $disabledCount = $domains->filter(
            fn ($domain) => $domain->status === DomainStatus::DISABLED,
        )->count();

        $missingDepsCount = $domains->filter(
            fn ($domain) => $domain->status === DomainStatus::MISSING_DEPS,
        )->count();

        // Count by type
        $typeCounts = [];
        foreach (DomainType::cases() as $type) {
            $typeCounts[$type->value] = $domains->filter(
                fn ($domain) => $domain->type === $type,
            )->count();
        }

        return [
            'total_modules'  => $totalModules,
            'with_manifests' => $withManifests,
            'disabled'       => $disabledCount,
            'missing_deps'   => $missingDepsCount,
            'type_counts'    => $typeCounts,
        ];
    }

    /**
     * Build Filament Stat objects from computed data.
     *
     * @param  array<string, mixed> $data
     * @return array<Stat>
     */
    private function buildStats(array $data): array
    {
        $totalModules = (int) $data['total_modules'];
        $withManifests = (int) $data['with_manifests'];
        $disabled = (int) $data['disabled'];
        $missingDeps = (int) $data['missing_deps'];
        /** @var array<string, int> $typeCounts */
        $typeCounts = $data['type_counts'];

        // Stat 1: Total Modules
        $totalStat = Stat::make('Total Modules', (string) $totalModules)
            ->description('Domain directories discovered')
            ->descriptionIcon('heroicon-m-puzzle-piece')
            ->color('primary');

        // Stat 2: With Manifests
        $manifestCoverage = $totalModules > 0
            ? (int) round(($withManifests / $totalModules) * 100)
            : 0;

        $manifestColor = $manifestCoverage >= 90 ? 'success' : ($manifestCoverage >= 70 ? 'warning' : 'danger');

        $manifestStat = Stat::make('With Manifests', (string) $withManifests)
            ->description("{$manifestCoverage}% coverage")
            ->descriptionIcon('heroicon-m-document-check')
            ->color($manifestColor);

        // Stat 3: Disabled
        $disabledColor = $disabled === 0 ? 'success' : 'warning';
        $disabledDescription = $missingDeps > 0
            ? "{$missingDeps} with missing dependencies"
            : 'All active modules healthy';

        $disabledStat = Stat::make('Disabled', (string) $disabled)
            ->description($disabledDescription)
            ->descriptionIcon($disabled > 0 ? 'heroicon-m-pause-circle' : 'heroicon-m-check-circle')
            ->color($missingDeps > 0 ? 'danger' : $disabledColor);

        // Stat 4: Module Types breakdown
        $typeParts = [];
        foreach ($typeCounts as $type => $count) {
            if ($count > 0) {
                $typeParts[] = "{$count} " . ucfirst($type);
            }
        }

        $typeBreakdown = implode(', ', $typeParts) ?: 'No modules';

        $typeStat = Stat::make('Module Types', $typeBreakdown)
            ->description('Distribution by domain type')
            ->descriptionIcon('heroicon-m-squares-2x2')
            ->color('info');

        return [$totalStat, $manifestStat, $disabledStat, $typeStat];
    }
}
