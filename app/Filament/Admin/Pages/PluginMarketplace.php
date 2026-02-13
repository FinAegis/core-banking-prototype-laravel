<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Domain\Shared\Models\Plugin;
use App\Infrastructure\Plugins\PluginManager;
use App\Infrastructure\Plugins\PluginSecurityScanner;
use Exception;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Url;

class PluginMarketplace extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'System';

    protected static ?int $navigationSort = 12;

    protected static ?string $title = 'Plugin Marketplace';

    protected static string $view = 'filament.admin.pages.plugin-marketplace';

    #[Url]
    public string $search = '';

    #[Url]
    public string $statusFilter = '';

    /** @var array<string, array{safe: bool, issues: array<array{file: string, line: int, type: string, code: string}>}> */
    public array $scanResults = [];

    private ?PluginManager $pluginManager = null;

    private ?PluginSecurityScanner $securityScanner = null;

    public function getPluginManager(): PluginManager
    {
        if ($this->pluginManager === null) {
            $this->pluginManager = app(PluginManager::class);
        }

        return $this->pluginManager;
    }

    public function getSecurityScanner(): PluginSecurityScanner
    {
        if ($this->securityScanner === null) {
            $this->securityScanner = app(PluginSecurityScanner::class);
        }

        return $this->securityScanner;
    }

    /**
     * Get all plugins filtered by current search and status criteria.
     *
     * @return Collection<int, Plugin>
     */
    public function getPluginsProperty(): Collection
    {
        $plugins = $this->getPluginManager()->list();

        if ($this->search !== '') {
            $search = mb_strtolower($this->search);
            $plugins = $plugins->filter(
                fn (Plugin $plugin) => str_contains(mb_strtolower($plugin->name), $search)
                    || str_contains(mb_strtolower((string) $plugin->display_name), $search)
                    || str_contains(mb_strtolower((string) $plugin->description), $search)
                    || str_contains(mb_strtolower((string) $plugin->vendor), $search)
            );
        }

        if ($this->statusFilter !== '') {
            $plugins = $plugins->filter(
                fn (Plugin $plugin) => $plugin->status === $this->statusFilter
            );
        }

        return $plugins;
    }

    /**
     * Get summary statistics for the plugin overview.
     *
     * @return array<string, int>
     */
    public function getStatsProperty(): array
    {
        $plugins = $this->getPluginManager()->list();

        return [
            'total'    => $plugins->count(),
            'active'   => $plugins->filter(fn (Plugin $p) => $p->isActive())->count(),
            'inactive' => $plugins->filter(fn (Plugin $p) => $p->isInactive())->count(),
            'failed'   => $plugins->filter(fn (Plugin $p) => $p->isFailed())->count(),
        ];
    }

    /**
     * Enable a plugin.
     */
    public function enablePlugin(string $vendor, string $name): void
    {
        try {
            $result = $this->getPluginManager()->enable($vendor, $name);

            if ($result['success']) {
                Notification::make()
                    ->title('Plugin Enabled')
                    ->body($result['message'])
                    ->success()
                    ->send();

                Log::info('Plugin enabled via admin panel', [
                    'plugin' => "{$vendor}/{$name}",
                    'user'   => auth()->user()->email ?? 'system',
                ]);
            } else {
                Notification::make()
                    ->title('Enable Failed')
                    ->body($result['message'])
                    ->danger()
                    ->send();
            }
        } catch (Exception $e) {
            Notification::make()
                ->title('Error')
                ->body("Failed to enable plugin: {$e->getMessage()}")
                ->danger()
                ->send();

            Log::error('Plugin enable failed', [
                'plugin' => "{$vendor}/{$name}",
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Disable a plugin.
     */
    public function disablePlugin(string $vendor, string $name): void
    {
        try {
            $result = $this->getPluginManager()->disable($vendor, $name);

            if ($result['success']) {
                Notification::make()
                    ->title('Plugin Disabled')
                    ->body($result['message'])
                    ->success()
                    ->send();

                Log::info('Plugin disabled via admin panel', [
                    'plugin' => "{$vendor}/{$name}",
                    'user'   => auth()->user()->email ?? 'system',
                ]);
            } else {
                Notification::make()
                    ->title('Disable Failed')
                    ->body($result['message'])
                    ->danger()
                    ->send();
            }
        } catch (Exception $e) {
            Notification::make()
                ->title('Error')
                ->body("Failed to disable plugin: {$e->getMessage()}")
                ->danger()
                ->send();

            Log::error('Plugin disable failed', [
                'plugin' => "{$vendor}/{$name}",
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Run security scan on a plugin.
     */
    public function scanPlugin(string $vendor, string $name, string $path): void
    {
        try {
            $scanner = $this->getSecurityScanner();
            $result = $scanner->scan($path);

            $this->scanResults["{$vendor}/{$name}"] = $result;

            if ($result['safe']) {
                Notification::make()
                    ->title('Security Scan Passed')
                    ->body("Plugin {$vendor}/{$name} passed all security checks.")
                    ->success()
                    ->duration(6000)
                    ->send();
            } else {
                $summary = $scanner->summarize($result['issues']);
                $issueCount = count($result['issues']);
                $types = implode(', ', array_keys($summary));

                Notification::make()
                    ->title('Security Issues Found')
                    ->body("{$issueCount} issue(s) found in {$vendor}/{$name}: {$types}")
                    ->warning()
                    ->duration(10000)
                    ->send();
            }

            Log::info('Plugin security scan via admin panel', [
                'plugin' => "{$vendor}/{$name}",
                'safe'   => $result['safe'],
                'issues' => count($result['issues']),
                'user'   => auth()->user()->email ?? 'system',
            ]);
        } catch (Exception $e) {
            Notification::make()
                ->title('Scan Error')
                ->body("Failed to scan plugin: {$e->getMessage()}")
                ->danger()
                ->send();

            Log::error('Plugin security scan failed', [
                'plugin' => "{$vendor}/{$name}",
                'error'  => $e->getMessage(),
            ]);
        }
    }

    /**
     * Discover new plugins from the filesystem.
     */
    public function discoverPlugins(): void
    {
        try {
            $result = $this->getPluginManager()->discover();

            Notification::make()
                ->title('Plugin Discovery Complete')
                ->body("Discovered {$result['discovered']} plugin(s), {$result['new']} new.")
                ->success()
                ->send();

            Log::info('Plugin discovery via admin panel', [
                'discovered' => $result['discovered'],
                'new'        => $result['new'],
                'user'       => auth()->user()->email ?? 'system',
            ]);
        } catch (Exception $e) {
            Notification::make()
                ->title('Discovery Error')
                ->body("Failed to discover plugins: {$e->getMessage()}")
                ->danger()
                ->send();

            Log::error('Plugin discovery failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Reset all filters to defaults.
     */
    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = '';
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('discover')
                ->label('Discover Plugins')
                ->icon('heroicon-o-magnifying-glass')
                ->color('gray')
                ->action(fn () => $this->discoverPlugins()),
        ];
    }
}
