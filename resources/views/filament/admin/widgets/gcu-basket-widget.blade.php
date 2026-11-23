<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between">
                <span>{{ $title ?? 'GCU Basket Composition' }}</span>
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
                    The {{ config('baskets.primary_name', 'GCU') }} basket is not set up yet. The proposed composition is shown below.
                </div>
            @else
                <div class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    {{ $description ?? 'This is how the Global Currency Unit basket is split between currencies today.' }}
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
                <p>The {{ config('baskets.primary_name', 'Global Currency Unit') }} is a software demonstration of a basket of major currencies and gold. It is not issued, offered or sold to anyone, and it has no monetary value.</p>
                <p class="mt-2">In the demonstration, users vote each month on how the basket is made up.</p>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>