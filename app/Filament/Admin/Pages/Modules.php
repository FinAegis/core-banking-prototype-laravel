<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Infrastructure\Domain\DataObjects\DomainInfo;
use App\Infrastructure\Domain\DomainManager;
use App\Infrastructure\Domain\Enums\DomainStatus;
use Exception;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Url;

class Modules extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-puzzle-piece';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 10;

    protected static ?string $title = 'Module Management';

    protected static string $view = 'filament.admin.pages.modules';

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    #[Url]
    public string $typeFilter = '';

    private ?DomainManager $domainManager = null;

    public function getDomainManager(): DomainManager
    {
        if ($this->domainManager === null) {
            $this->domainManager = app(DomainManager::class);
        }

        return $this->domainManager;
    }

    /**
     * Get all modules filtered by current search and status criteria.
     *
     * @return Collection<int, DomainInfo>
     */
    public function getModulesProperty(): Collection
    {
        $domains = $this->getDomainManager()->getAvailableDomains();

        if ($this->search !== '') {
            $search = mb_strtolower($this->search);
            $domains = $domains->filter(
                fn (DomainInfo $info) => str_contains(mb_strtolower($info->name), $search)
                    || str_contains(mb_strtolower($info->displayName), $search)
                    || str_contains(mb_strtolower($info->description), $search)
            );
        }

        if ($this->statusFilter !== '') {
            $status = DomainStatus::tryFrom($this->statusFilter);
            if ($status !== null) {
                $domains = $domains->filter(
                    fn (DomainInfo $info) => $info->status === $status
                );
            }
        }

        if ($this->typeFilter !== '') {
            $domains = $domains->filter(
                fn (DomainInfo $info) => $info->type->value === $this->typeFilter
            );
        }

        return $domains->sortBy('displayName')->values();
    }

    /**
     * Get summary statistics for the module overview.
     *
     * @return array<string, int>
     */
    public function getStatsProperty(): array
    {
        $domains = $this->getDomainManager()->getAvailableDomains();

        return [
            'total'     => $domains->count(),
            'installed' => $domains->filter(fn (DomainInfo $info) => $info->status === DomainStatus::INSTALLED)->count(),
            'available' => $domains->filter(fn (DomainInfo $info) => $info->status === DomainStatus::AVAILABLE)->count(),
            'disabled'  => $domains->filter(fn (DomainInfo $info) => $info->status === DomainStatus::DISABLED)->count(),
            'core'      => $domains->filter(fn (DomainInfo $info) => $info->type->isRequired())->count(),
            'optional'  => $domains->filter(fn (DomainInfo $info) => ! $info->type->isRequired())->count(),
        ];
    }

    /**
     * Check if a domain has declared routes.
     */
    public function hasRoutes(DomainInfo $info): bool
    {
        $manifests = $this->getDomainManager()->loadAllManifests();

        if (! isset($manifests[$info->name])) {
            return false;
        }

        return $manifests[$info->name]->getPath('routes') !== null;
    }

    /**
     * Enable a disabled domain.
     */
    public function enableModule(string $domain): void
    {
        if (! $this->runtimeManagementAllowed()) {
            return;
        }

        try {
            $result = $this->getDomainManager()->enable($domain);

            if ($result->success) {
                Notification::make()
                    ->title('Module Enabled')
                    ->body("{$domain} is now enabled.")
                    ->success()
                    ->send();

                Log::info('Module enabled via admin panel', [
                    'domain'   => $domain,
                    'user'     => auth()->user()->email ?? 'system',
                    'warnings' => $result->warnings,
                ]);
            } else {
                Notification::make()
                    ->title('Enable Failed')
                    ->body(implode('. ', $result->errors))
                    ->danger()
                    ->send();
            }
        } catch (Exception $e) {
            Notification::make()
                ->title('Error')
                ->body("The module could not be enabled. {$e->getMessage()}")
                ->danger()
                ->send();

            Log::error('Module enable failed', [
                'domain' => $domain,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Disable an active domain.
     */
    public function disableModule(string $domain): void
    {
        if (! $this->runtimeManagementAllowed()) {
            return;
        }

        try {
            $result = $this->getDomainManager()->disable($domain);

            if ($result->success) {
                Notification::make()
                    ->title('Module Disabled')
                    ->body("{$domain} is now disabled. Its database tables and data are kept.")
                    ->success()
                    ->send();

                Log::info('Module disabled via admin panel', [
                    'domain'   => $domain,
                    'user'     => auth()->user()->email ?? 'system',
                    'warnings' => $result->warnings,
                ]);
            } else {
                Notification::make()
                    ->title('Disable Failed')
                    ->body(implode('. ', $result->errors))
                    ->danger()
                    ->send();
            }
        } catch (Exception $e) {
            Notification::make()
                ->title('Error')
                ->body("The module could not be disabled. {$e->getMessage()}")
                ->danger()
                ->send();

            Log::error('Module disable failed', [
                'domain' => $domain,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Verify a domain's health and configuration.
     */
    public function verifyModule(string $domain): void
    {
        try {
            $result = $this->getDomainManager()->verify($domain);

            if ($result->valid) {
                $totalChecks = count($result->checks);
                $message = "{$result->getPassedCount()} of {$totalChecks} checks passed.";

                if (! empty($result->warnings)) {
                    $message .= ' Warnings: ' . implode(', ', $result->warnings);
                }

                Notification::make()
                    ->title('Verification Passed')
                    ->body($message)
                    ->success()
                    ->duration(8000)
                    ->send();
            } else {
                $totalChecks = count($result->checks);
                $message = "Only {$result->getPassedCount()} of {$totalChecks} checks passed.";

                if (! empty($result->errors)) {
                    $message .= ' Errors: ' . implode(', ', $result->errors);
                }

                Notification::make()
                    ->title('Verification Failed')
                    ->body($message)
                    ->danger()
                    ->duration(10000)
                    ->send();
            }

            Log::info('Module verified via admin panel', [
                'domain' => $domain,
                'valid'  => $result->valid,
                'passed' => $result->getPassedCount(),
                'failed' => $result->getFailedCount(),
                'user'   => auth()->user()->email ?? 'system',
            ]);
        } catch (Exception $e) {
            Notification::make()
                ->title('Error')
                ->body("The module could not be verified. {$e->getMessage()}")
                ->danger()
                ->send();

            Log::error('Module verification failed', [
                'domain' => $domain,
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Clear the domain manager cache and refresh the page.
     */
    public function refreshModules(): void
    {
        $this->getDomainManager()->clearCache();

        Notification::make()
            ->title('Cache Cleared')
            ->body('Module cache has been refreshed.')
            ->success()
            ->send();
    }

    /**
     * Runtime module changes write the domain_manager_disabled cache key, so
     * they need ADMIN_RUNTIME_MANAGEMENT_ENABLED; otherwise show a notice.
     */
    private function runtimeManagementAllowed(): bool
    {
        if ((bool) config('modules.runtime_management_enabled')) {
            return true;
        }

        Notification::make()
            ->title('Not Available')
            ->body('The ADMIN_RUNTIME_MANAGEMENT_ENABLED setting turns off module changes from the admin panel. Change MODULES_DISABLED in the environment instead.')
            ->warning()
            ->send();

        return false;
    }

    /**
     * Reset all filters to defaults.
     */
    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
        $this->typeFilter = '';
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('refresh')
                ->label('Refresh Cache')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->refreshModules()),
        ];
    }
}
