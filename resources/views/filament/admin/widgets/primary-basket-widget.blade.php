<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center justify-between">
                <span>GCU Basket Composition</span>
                @if (!$this->getBasketData()['exists'])
                    <x-filament::badge color="warning">Not Configured</x-filament::badge>
                @else
                    <x-filament::badge color="success">Active</x-filament::badge>
                @endif
            </div>
        </x-slot>

        <div class="space-y-4">
            @if (!$this->getBasketData()['exists'])
                <div class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                    The GCU basket is not set up yet. The proposed composition is shown below.
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach ($this->getBasketData()['currencies'] as $currency)
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

            <div class="mt-6 text-sm text-gray-600 dark:text-gray-400">
                <p>The Global Currency Unit is a software demonstration of a basket of major currencies and gold. It is not issued, offered or sold to anyone, and it has no monetary value.</p>
                <p class="mt-2">In the demonstration, users vote each month on how the basket is made up.</p>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>