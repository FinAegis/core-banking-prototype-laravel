<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ $asset->name }} ({{ $asset->symbol }})
                </h2>
                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $asset->description }}</p>
            </div>
            <a href="{{ route('asset-management.index') }}" 
               class="text-gray-600 hover:text-gray-900">
                ← Back to Portfolio
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Asset Overview -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Current Price</p>
                            <p class="text-2xl font-bold">${{ number_format($priceHistory[count($priceHistory)-1]['price'] / 100, 4) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Market Cap</p>
                            <p class="text-2xl font-bold">${{ number_format($statistics['market_cap'] / 100000000, 2) }}M</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">24h Volume</p>
                            <p class="text-2xl font-bold">${{ number_format($statistics['volume_24h'] / 100, 0) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Holders</p>
                            <p class="text-2xl font-bold">{{ number_format($statistics['holders']) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Your Holdings -->
            @if(count($holdings) > 0)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Your Holdings</h3>
                        <div class="space-y-3">
                            @php
                                $totalBalance = 0;
                                $totalValue = 0;
                            @endphp
                            @foreach($holdings as $holding)
                                @php
                                    $totalBalance += $holding['balance']->balance;
                                    $totalValue += $holding['value_usd'];
                                @endphp
                                <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700 rounded">
                                    <div>
                                        <p class="font-medium">{{ $holding['account']->name }}</p>
                                        <p class="text-sm text-gray-500">Account {{ $holding['account']->type }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="font-medium">{{ number_format($holding['balance']->balance / 100, 2) }} {{ $asset->symbol }}</p>
                                        <p class="text-sm text-gray-500">${{ number_format($holding['value_usd'] / 100, 2) }}</p>
                                    </div>
                                </div>
                            @endforeach
                            
                            <div class="border-t pt-3">
                                <div class="flex items-center justify-between">
                                    <p class="font-semibold">Total Holdings</p>
                                    <div class="text-right">
                                        <p class="font-semibold">{{ number_format($totalBalance / 100, 2) }} {{ $asset->symbol }}</p>
                                        <p class="text-sm text-gray-500">${{ number_format($totalValue / 100, 2) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Price Chart -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Price History (30 Days)</h3>
                    <canvas id="priceChart" height="100"></canvas>
                </div>
            </div>

            <!-- Statistics -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Statistics</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        @if($statistics['total_supply'])
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Total Supply</p>
                                <p class="font-medium">{{ number_format($statistics['total_supply']) }}</p>
                            </div>
                        @endif
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">24h Transactions</p>
                            <p class="font-medium">{{ number_format($statistics['transactions_24h']) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Asset Type</p>
                            <p class="font-medium">{{ ucfirst($asset->type) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Status</p>
                            <p class="font-medium">
                                <span class="px-2 py-1 text-xs rounded-full {{ $asset->is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                    {{ $asset->is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Your {{ $asset->symbol }} Transactions</h3>
                    
                    @if($transactions->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Date
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Type
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Description
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Amount
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Status
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($transactions as $transaction)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                {{ $transaction->created_at->format('M d, Y H:i') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                {{ ucfirst(str_replace('_', ' ', $transaction->type)) }}
                                            </td>
                                            <td class="px-6 py-4 text-sm">
                                                {{ $transaction->description ?? 'No description' }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                                                <span class="{{ $transaction->amount >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                                    {{ $transaction->amount >= 0 ? '+' : '' }}{{ number_format($transaction->amount / 100, 2) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                    {{ $transaction->status === 'completed' ? 'bg-green-100 text-green-800' : '' }}
                                                    {{ $transaction->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : '' }}
                                                    {{ $transaction->status === 'failed' ? 'bg-red-100 text-red-800' : '' }}">
                                                    {{ ucfirst($transaction->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-gray-500 text-center py-8">No transactions found for this asset</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/date-fns@2.29.3/index.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <script>
        // Price Chart
        const ctx = document.getElementById('priceChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: {!! json_encode(array_column($priceHistory, 'date')) !!},
                datasets: [{
                    label: 'Price',
                    data: {!! json_encode(array_map(function($item) { return $item['price'] / 100; }, $priceHistory)) !!},
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    tension: 0.1,
                    fill: true
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
                                return '$' + context.parsed.y.toFixed(4);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        type: 'time',
                        time: {
                            unit: 'day'
                        }
                    },
                    y: {
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toFixed(2);
                            }
                        }
                    }
                }
            }
        });
    </script>
</x-app-layout>