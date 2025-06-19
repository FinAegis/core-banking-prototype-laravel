<x-filament-panels::page>
    <div class="space-y-4">
        <div class="text-2xl font-bold tracking-tight">
            Welcome to FinAegis Dashboard
        </div>
        <div class="text-sm text-gray-600 dark:text-gray-400">
            Enterprise-grade core banking platform
        </div>
    </div>
    
    @livewire(\App\Filament\Admin\Resources\AccountResource\Widgets\AccountStatsOverview::class)
</x-filament-panels::page>