<x-filament-panels::page>
    @if(config('app.gcu_enabled'))
        {{-- GCU Dashboard --}}
        <div class="space-y-6">
            <div class="bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-emerald-900/20 dark:to-teal-900/20 rounded-lg p-6 border border-emerald-200 dark:border-emerald-700">
                <div class="flex items-start justify-between">
                    <div>
                        <div class="flex items-center">
                            <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                                {{ config('app.gcu_basket_name', 'Global Currency Unit') }}
                            </h1>
                            <span class="ml-3 text-3xl">{{ config('app.gcu_basket_symbol', 'Ǥ') }}</span>
                        </div>
                        <p class="mt-2 text-base text-gray-700 dark:text-gray-300">
                            {{ config('app.gcu_basket_description', 'Democratic global currency backed by real banks') }}
                        </p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Powered by FinAegis Platform
                        </p>
                    </div>
                </div>
                
                <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-4">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Currency Type</h3>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">User-Controlled</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-4">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Bank Partners</h3>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">5 Integrated Banks</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-4">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Governance</h3>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">Monthly Voting</p>
                    </div>
                </div>
            </div>
        </div>
    @else
        {{-- FinAegis Platform Dashboard --}}
        <div class="space-y-4">
            <div class="text-2xl font-bold tracking-tight">
                Welcome to FinAegis Dashboard
            </div>
            <div class="text-sm text-gray-600 dark:text-gray-400">
                Enterprise-grade core banking platform
            </div>
        </div>
    @endif
    
    @livewire(\App\Filament\Admin\Resources\AccountResource\Widgets\AccountStatsOverview::class)
</x-filament-panels::page>