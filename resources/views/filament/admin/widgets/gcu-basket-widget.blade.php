<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between">
                <span>{{ $title ?? 'GCU Currency Basket Composition' }}</span>
                @if (!$exists)
                    <x-filament::badge color="warning">Not Configured</x-filament::badge>
                @else
                    <x-filament::badge color="success">Active</x-filament::badge>
                @endif
            </div>
        </x-slot>

        <div class="space-y-4">
            @if (!$exists)
                <div class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    The {{ config('baskets.primary_name', 'GCU') }} basket has not been configured yet. The proposed composition is:
                </div>
            @else
                <div class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    {{ $description ?? 'Current allocation of currencies in the Global Currency Unit basket' }}
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($currencies as $currency)
                    <div class="bg-gray-50 dark:bg-gray-800 rounded-lg p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="font-semibold text-lg">{{ $currency['code'] }}</div>
                                <div class="text-sm text-gray-600 dark:text-gray-400">{{ $currency['name'] }}</div>
                            </div>
                            <div class="text-2xl font-bold text-primary-600">
                                {{ number_format($currency['weight'], 0) }}%
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($exists)
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Symbol</div>
                        <div class="text-lg font-semibold">{{ $symbol ?? 'Ǥ' }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Type</div>
                        <div class="text-lg font-semibold">{{ $basket->type ?? 'Dynamic' }}</div>
                    </div>
                    <div>
                        <div class="text-sm text-gray-600 dark:text-gray-400">Last Rebalanced</div>
                        <div class="text-lg font-semibold">{{ $lastRebalanced ?? 'Never' }}</div>
                    </div>
                    @if ($nextRebalance)
                        <div>
                            <div class="text-sm text-gray-600 dark:text-gray-400">Next Rebalance</div>
                            <div class="text-lg font-semibold">{{ \Carbon\Carbon::parse($nextRebalance)->format('M j, Y') }}</div>
                        </div>
                    @endif
                </div>
            @endif

            <div class="mt-6 text-sm text-gray-600 dark:text-gray-400">
                <p>The {{ config('baskets.primary_name', 'Global Currency Unit') }} ({{ config('baskets.primary_code', 'GCU') }}) is backed by a diversified basket of major currencies and gold, providing stability and reducing single-currency risk.</p>
                <p class="mt-2">Users vote monthly on the basket composition, ensuring democratic control over the currency.</p>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>