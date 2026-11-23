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
                            {{ config('app.gcu_basket_description', 'This is a software demonstration of a currency basket. It is not issued, offered or sold to anyone, and it has no monetary value.') }}
                        </p>
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            The demonstration runs on the {{ config('brand.name', 'Zelta') }} platform.
                        </p>
                    </div>
                </div>
                
                <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-4">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Status</h3>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">Demonstration Only</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-4">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Bank Partners</h3>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">None</p>
                    </div>
                    <div class="bg-white dark:bg-gray-800 rounded-lg p-4">
                        <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Governance</h3>
                        <p class="text-lg font-semibold text-gray-900 dark:text-white">Monthly Voting</p>
                    </div>
                </div>
            </div>
        </div>
    @else
        {{-- {{ config('brand.name', 'Zelta') }} Platform Dashboard --}}
        <div class="space-y-4">
            <div class="text-2xl font-bold tracking-tight">
                Welcome to the {{ config('brand.name', 'Zelta') }} admin panel
            </div>
            <div class="text-sm text-gray-600 dark:text-gray-400">
                Staff use this panel to look after users, accounts, payments and the systems that run them.
            </div>
        </div>
    @endif
    
    @livewire(\App\Filament\Admin\Resources\AccountResource\Widgets\AccountStatsOverview::class)

    @if(\App\Filament\Admin\Widgets\SubscriptionRevenueStatsWidget::canView())
        @livewire(\App\Filament\Admin\Widgets\SubscriptionRevenueStatsWidget::class)
    @endif
</x-filament-panels::page>