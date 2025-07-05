<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Liquidity Pools') }}
            </h2>
            <div class="text-sm text-gray-600 dark:text-gray-400">
                Total Value Locked: <span class="font-semibold">${{ number_format($marketData['total_tvl'], 0) }}</span>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Market Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Total Value Locked</p>
                    <p class="text-2xl font-bold">${{ number_format($marketData['total_tvl'] / 1000000, 1) }}M</p>
                    <p class="text-sm {{ $marketData['tvl_24h_change'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $marketData['tvl_24h_change'] >= 0 ? '+' : '' }}{{ number_format($marketData['tvl_24h_change'], 2) }}%
                    </p>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">24h Volume</p>
                    <p class="text-2xl font-bold">${{ number_format($marketData['total_volume_24h'] / 1000000, 1) }}M</p>
                    <p class="text-sm {{ $marketData['volume_24h_change'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                        {{ $marketData['volume_24h_change'] >= 0 ? '+' : '' }}{{ number_format($marketData['volume_24h_change'], 2) }}%
                    </p>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">24h Fees</p>
                    <p class="text-2xl font-bold">${{ number_format($marketData['total_fees_24h'], 0) }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Average APY</p>
                    <p class="text-2xl font-bold text-green-600">{{ number_format($marketData['avg_apy'], 2) }}%</p>
                </div>
            </div>

            <!-- Your Positions -->
            @if($userLiquidity->isNotEmpty())
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Your Liquidity Positions</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Pool
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Your Share
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Value
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            P&L
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($userLiquidity as $position)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <span class="font-medium">{{ $position['base_currency'] }}/{{ $position['quote_currency'] }}</span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                {{ number_format($position['share_percentage'] * 100, 2) }}%
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                ${{ number_format($position['value_usd'], 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <span class="{{ $position['pnl'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                    {{ $position['pnl'] >= 0 ? '+' : '' }}${{ number_format(abs($position['pnl']), 2) }}
                                                    ({{ $position['pnl_percentage'] >= 0 ? '+' : '' }}{{ number_format($position['pnl_percentage'], 2) }}%)
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                                <a href="{{ route('liquidity.show', $position['pool_id']) }}" 
                                                   class="text-indigo-600 hover:text-indigo-900 mr-3">Manage</a>
                                                <a href="{{ route('liquidity.remove', $position['pool_id']) }}" 
                                                   class="text-red-600 hover:text-red-900">Remove</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            <!-- All Pools -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Available Pools</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($pools as $pool)
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-lg transition">
                                <div class="flex justify-between items-start mb-3">
                                    <div>
                                        <h4 class="font-semibold text-lg">{{ $pool['base_currency'] }}/{{ $pool['quote_currency'] }}</h4>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Fee: {{ $pool['fee_rate'] * 100 }}%</p>
                                    </div>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                        Active
                                    </span>
                                </div>
                                
                                <div class="space-y-2 mb-4">
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600 dark:text-gray-400">TVL</span>
                                        <span class="font-medium">${{ number_format($pool['tvl'] / 1000000, 2) }}M</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600 dark:text-gray-400">24h Volume</span>
                                        <span class="font-medium">${{ number_format($pool['volume_24h'] / 1000, 0) }}k</span>
                                    </div>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600 dark:text-gray-400">APY</span>
                                        <span class="font-medium text-green-600">{{ number_format($pool['apy'], 2) }}%</span>
                                    </div>
                                </div>
                                
                                <div class="flex space-x-2">
                                    <a href="{{ route('liquidity.show', $pool['id']) }}" 
                                       class="flex-1 text-center px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                                        View Details
                                    </a>
                                    <a href="{{ route('liquidity.create', $pool['id']) }}" 
                                       class="flex-1 text-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                                        Add Liquidity
                                    </a>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>