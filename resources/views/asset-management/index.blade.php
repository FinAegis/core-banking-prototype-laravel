<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Asset Management') }}
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('asset-management.analytics') }}" 
                   class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                    Analytics
                </a>
                <a href="{{ route('asset-management.export') }}?format=csv" 
                   class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                    Export Portfolio
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Portfolio Summary -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Portfolio Summary</h3>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Total Value</p>
                            <p class="text-3xl font-bold">
                                ${{ number_format($portfolio['total_value'] / 100, 2) }}
                            </p>
                            <p class="text-sm {{ $portfolio['change'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $portfolio['change'] >= 0 ? '+' : '' }}${{ number_format(abs($portfolio['change']) / 100, 2) }}
                                ({{ $portfolio['change_percent'] >= 0 ? '+' : '' }}{{ number_format($portfolio['change_percent'], 2) }}%)
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Assets</p>
                            <p class="text-3xl font-bold">{{ $portfolio['asset_count'] }}</p>
                            <p class="text-sm text-gray-500">Across {{ $portfolio['currency_count'] }} currencies</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">24h Change</p>
                            <p class="text-3xl font-bold {{ $portfolio['change_percent'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $portfolio['change_percent'] >= 0 ? '+' : '' }}{{ number_format($portfolio['change_percent'], 2) }}%
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Accounts</p>
                            <p class="text-3xl font-bold">{{ $accounts->count() }}</p>
                            <p class="text-sm text-gray-500">Active accounts</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Asset Allocation -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Asset Allocation</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Pie Chart -->
                        <div>
                            <canvas id="allocationChart" height="300"></canvas>
                        </div>
                        
                        <!-- Allocation Table -->
                        <div>
                            <div class="space-y-2">
                                @foreach($allocation as $asset)
                                    <div class="flex items-center justify-between p-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded">
                                        <div class="flex items-center">
                                            <div class="w-4 h-4 rounded-full mr-3" style="background-color: {{ $asset['color'] }}"></div>
                                            <div>
                                                <p class="font-medium">{{ $asset['symbol'] }}</p>
                                                <p class="text-sm text-gray-500">{{ $asset['name'] }}</p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <p class="font-medium">${{ number_format($asset['value'] / 100, 2) }}</p>
                                            <p class="text-sm text-gray-500">{{ number_format($asset['percentage'], 1) }}%</p>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Asset Performance -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Asset Performance</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Asset
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Price
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        24h Change
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        7d Change
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        30d Change
                                    </th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($performance as $perf)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="font-medium">{{ $perf['symbol'] }}</div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            ${{ number_format($perf['price'] / 100, 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="{{ $perf['change_24h'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                {{ $perf['change_24h'] >= 0 ? '+' : '' }}{{ number_format($perf['change_24h'], 2) }}%
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="{{ $perf['change_7d'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                {{ $perf['change_7d'] >= 0 ? '+' : '' }}{{ number_format($perf['change_7d'], 2) }}%
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="{{ $perf['change_30d'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                {{ $perf['change_30d'] >= 0 ? '+' : '' }}{{ number_format($perf['change_30d'], 2) }}%
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right">
                                            @php
                                                $asset = $availableAssets->where('symbol', $perf['symbol'])->first();
                                            @endphp
                                            @if($asset)
                                                <a href="{{ route('asset-management.show', $asset) }}" 
                                                   class="text-indigo-600 hover:text-indigo-900">
                                                    View Details
                                                </a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Recent Transactions</h3>
                        <a href="{{ route('wallet.transactions') }}" class="text-indigo-600 hover:text-indigo-900 text-sm">
                            View All →
                        </a>
                    </div>
                    
                    @if($recentTransactions->count() > 0)
                        <div class="space-y-2">
                            @foreach($recentTransactions as $transaction)
                                <div class="flex items-center justify-between p-3 hover:bg-gray-50 dark:hover:bg-gray-700 rounded">
                                    <div class="flex items-center">
                                        <div class="p-2 rounded-full mr-3
                                            {{ str_contains($transaction['type'], 'deposit') ? 'bg-green-100' : '' }}
                                            {{ str_contains($transaction['type'], 'withdraw') ? 'bg-red-100' : '' }}
                                            {{ str_contains($transaction['type'], 'transfer') ? 'bg-blue-100' : '' }}
                                            {{ str_contains($transaction['type'], 'conversion') ? 'bg-purple-100' : '' }}">
                                            @if(str_contains($transaction['type'], 'deposit'))
                                                <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                                </svg>
                                            @elseif(str_contains($transaction['type'], 'withdraw'))
                                                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                                                </svg>
                                            @elseif(str_contains($transaction['type'], 'transfer'))
                                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                                </svg>
                                            @else
                                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                            @endif
                                        </div>
                                        <div>
                                            <p class="font-medium">{{ ucfirst(str_replace('_', ' ', $transaction['type'])) }}</p>
                                            <p class="text-sm text-gray-500">{{ $transaction['description'] ?? 'No description' }}</p>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-medium {{ $transaction['amount'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                            {{ $transaction['amount'] >= 0 ? '+' : '' }}{{ number_format($transaction['amount'] / 100, 2) }} {{ $transaction['currency'] }}
                                        </p>
                                        <p class="text-sm text-gray-500">{{ $transaction['created_at']->diffForHumans() }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-500 text-center py-8">No recent transactions</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Asset Allocation Chart
        const ctx = document.getElementById('allocationChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: {!! json_encode(array_column($allocation, 'symbol')) !!},
                datasets: [{
                    data: {!! json_encode(array_column($allocation, 'percentage')) !!},
                    backgroundColor: {!! json_encode(array_column($allocation, 'color')) !!},
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': ' + context.parsed.toFixed(1) + '%';
                            }
                        }
                    }
                }
            }
        });
    </script>
</x-app-layout>