<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Platform Header --}}
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-sm p-6">
            <div class="flex items-start justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white">
                        FinAegis Platform
                    </h1>
                    <p class="mt-2 text-base text-gray-600 dark:text-gray-400">
                        Enterprise-grade core banking platform powering revolutionary financial products
                    </p>
                </div>
                @if(config('app.gcu_enabled'))
                <div class="text-right">
                    <div class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-emerald-100 text-emerald-800 dark:bg-emerald-800 dark:text-emerald-100">
                        <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                        GCU Implementation Active
                    </div>
                </div>
                @endif
            </div>

            {{-- Platform Features --}}
            <div class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">Multi-Asset Support</p>
                        <p class="text-sm text-gray-500">Fiat, Crypto, Commodities</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">Event Sourcing</p>
                        <p class="text-sm text-gray-500">Complete Audit Trail</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">High Performance</p>
                        <p class="text-sm text-gray-500">10,000+ TPS</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">Democratic Governance</p>
                        <p class="text-sm text-gray-500">Voting & Polls</p>
                    </div>
                </div>
            </div>
        </div>

        {{-- GCU Implementation Details (if enabled) --}}
        @if(config('app.gcu_enabled'))
        <div class="bg-gradient-to-r from-emerald-50 to-teal-50 dark:from-emerald-900/20 dark:to-teal-900/20 rounded-lg p-6 border border-emerald-200 dark:border-emerald-700">
            <div class="flex items-center mb-4">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                    Global Currency Unit (GCU) Implementation
                </h2>
                <span class="ml-3 text-2xl">{{ config('app.gcu_basket_symbol', 'Ǥ') }}</span>
            </div>
            <p class="text-sm text-gray-700 dark:text-gray-300 mb-4">
                {{ config('app.gcu_basket_description', 'Democratic global currency backed by real banks') }}
            </p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4">
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Implementation Type</h3>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">User-Controlled Currency</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4">
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Bank Partners</h3>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">5 Integrated Banks</p>
                </div>
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4">
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Governance Model</h3>
                    <p class="text-lg font-semibold text-gray-900 dark:text-white">Monthly Voting</p>
                </div>
            </div>
        </div>
        @endif
    </div>
    
    @livewire(\App\Filament\Admin\Resources\AccountResource\Widgets\AccountStatsOverview::class)
</x-filament-panels::page>