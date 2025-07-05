<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $pool['base_currency'] }}/{{ $pool['quote_currency'] }} Pool
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                    Fee Tier: {{ $pool['fee_rate'] * 100 }}%
                </p>
            </div>
            <div class="flex space-x-2">
                <a href="{{ route('liquidity.create', $pool['id']) }}" 
                   class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                    Add Liquidity
                </a>
                @if($userPosition)
                    <a href="{{ route('liquidity.remove', $pool['id']) }}" 
                       class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                        Remove Liquidity
                    </a>
                @endif
                <a href="{{ route('liquidity.index') }}" 
                   class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                    Back to Pools
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Pool Metrics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Total Value Locked</p>
                    <p class="text-2xl font-bold">${{ number_format($metrics['tvl'] / 1000000, 2) }}M</p>
                    <p class="text-sm {{ $metrics['tvl_24h_change'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $metrics['tvl_24h_change'] >= 0 ? '+' : '' }}{{ number_format($metrics['tvl_24h_change'], 2) }}%
                    </p>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">24h Volume</p>
                    <p class="text-2xl font-bold">${{ number_format($metrics['volume_24h'] / 1000, 0) }}k</p>
                    <p class="text-sm {{ $metrics['volume_24h_change'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $metrics['volume_24h_change'] >= 0 ? '+' : '' }}{{ number_format($metrics['volume_24h_change'], 2) }}%
                    </p>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">24h Fees</p>
                    <p class="text-2xl font-bold">${{ number_format($metrics['fees_24h'], 0) }}</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ number_format($metrics['fee_apy'], 2) }}% APY
                    </p>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Current Price</p>
                    <p class="text-2xl font-bold">{{ number_format($metrics['current_price'], 4) }}</p>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ $pool['quote_currency'] }} per {{ $pool['base_currency'] }}
                    </p>
                </div>
            </div>

            <!-- Your Position -->
            @if($userPosition)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Your Position</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Pool Share</p>
                                <p class="text-xl font-semibold">{{ number_format($userPosition['share_percentage'] * 100, 4) }}%</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ number_format($userPosition['liquidity_tokens'], 0) }} LP tokens</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Current Value</p>
                                <p class="text-xl font-semibold">${{ number_format($userPosition['value_usd'], 2) }}</p>
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    {{ number_format($userPosition['base_amount'], 4) }} {{ $pool['base_currency'] }} + 
                                    {{ number_format($userPosition['quote_amount'], 2) }} {{ $pool['quote_currency'] }}
                                </p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">P&L</p>
                                <p class="text-xl font-semibold {{ $userPosition['pnl'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $userPosition['pnl'] >= 0 ? '+' : '' }}${{ number_format(abs($userPosition['pnl']), 2) }}
                                </p>
                                <p class="text-sm {{ $userPosition['pnl_percentage'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                    {{ $userPosition['pnl_percentage'] >= 0 ? '+' : '' }}{{ number_format($userPosition['pnl_percentage'], 2) }}%
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Pool Composition -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Pool Composition</h3>
                        <div class="space-y-4">
                            <div>
                                <div class="flex justify-between items-center mb-2">
                                    <span class="font-medium">{{ $pool['base_currency'] }}</span>
                                    <span class="text-sm text-gray-600">{{ number_format($metrics['base_reserve'], 4) }}</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-3">
                                    <div class="bg-indigo-600 h-3 rounded-full" style="width: 50%"></div>
                                </div>
                            </div>
                            <div>
                                <div class="flex justify-between items-center mb-2">
                                    <span class="font-medium">{{ $pool['quote_currency'] }}</span>
                                    <span class="text-sm text-gray-600">{{ number_format($metrics['quote_reserve'], 2) }}</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-3">
                                    <div class="bg-green-600 h-3 rounded-full" style="width: 50%"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Pool Statistics</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Total Liquidity Providers</span>
                                <span class="font-medium">{{ number_format($metrics['provider_count'], 0) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Total Transactions</span>
                                <span class="font-medium">{{ number_format($metrics['total_transactions'], 0) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Average Trade Size</span>
                                <span class="font-medium">${{ number_format($metrics['avg_trade_size'], 0) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600 dark:text-gray-400">Price Impact (1%)</span>
                                <span class="font-medium">{{ number_format($metrics['price_impact_1_percent'], 2) }}%</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Price Chart -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">24h Price History</h3>
                    <div class="h-64" id="priceChart">
                        <!-- Chart would be rendered here with Chart.js or similar -->
                        <div class="flex items-center justify-center h-full text-gray-500">
                            Price chart visualization
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // Initialize price chart with data
        const priceHistory = @json($priceHistory);
        // Chart.js or similar implementation would go here
    </script>
    @endpush
</x-app-layout>