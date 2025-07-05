<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Arbitrage Opportunities') }}
            </h2>
            <a href="{{ route('exchange.external.index') }}" 
               class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                Back to External Exchanges
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Performance Overview -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Total Profit (30d)</p>
                    <p class="text-2xl font-bold text-green-600">
                        ${{ number_format($historicalPerformance->sum('total_profit'), 2) }}
                    </p>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Total Trades (30d)</p>
                    <p class="text-2xl font-bold">{{ $historicalPerformance->sum('trades') }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Avg Profit %</p>
                    <p class="text-2xl font-bold">
                        {{ number_format($historicalPerformance->avg('avg_profit_percentage'), 2) }}%
                    </p>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-6">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Active Strategies</p>
                    <p class="text-2xl font-bold">{{ $activeStrategies->count() }}</p>
                </div>
            </div>

            <!-- Current Opportunities -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Live Arbitrage Opportunities</h3>
                        <div class="flex items-center space-x-2">
                            <span class="text-sm text-gray-600 dark:text-gray-400">Auto-refresh</span>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" id="autoRefresh" class="sr-only peer" checked>
                                <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-green-600"></div>
                            </label>
                        </div>
                    </div>

                    @if($opportunities->isEmpty())
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="mt-2 text-gray-600 dark:text-gray-400">No arbitrage opportunities at the moment</p>
                            <p class="text-sm text-gray-500 dark:text-gray-500 mt-1">Opportunities appear when price differences exceed fees</p>
                        </div>
                    @else
                        <div class="space-y-4">
                            @foreach($opportunities as $opportunity)
                                <div class="border {{ $opportunity['profit_percentage'] > 2 ? 'border-green-500' : 'border-gray-200 dark:border-gray-700' }} rounded-lg p-4">
                                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                        <div>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Trading Pair</p>
                                            <p class="font-semibold text-lg">{{ $opportunity['pair'] }}</p>
                                            <p class="text-xs text-gray-500 mt-1">
                                                Volume: {{ number_format($opportunity['volume_24h'], 0) }}
                                            </p>
                                        </div>
                                        
                                        <div>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Buy From</p>
                                            <p class="font-medium">{{ ucfirst($opportunity['buy_exchange']) }}</p>
                                            <p class="text-lg font-bold">${{ number_format($opportunity['buy_price'], 2) }}</p>
                                        </div>
                                        
                                        <div>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Sell To</p>
                                            <p class="font-medium">{{ ucfirst($opportunity['sell_exchange']) }}</p>
                                            <p class="text-lg font-bold">${{ number_format($opportunity['sell_price'], 2) }}</p>
                                        </div>
                                        
                                        <div class="text-right">
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Profit Margin</p>
                                            <p class="text-2xl font-bold text-green-600">
                                                +{{ number_format($opportunity['profit_percentage'], 2) }}%
                                            </p>
                                            <button onclick="showExecuteModal('{{ json_encode($opportunity) }}')" 
                                                    class="mt-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition text-sm">
                                                Execute Arbitrage
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-3 pt-3 border-t border-gray-200 dark:border-gray-700 grid grid-cols-2 md:grid-cols-4 gap-2 text-sm">
                                        <div>
                                            <span class="text-gray-600 dark:text-gray-400">Max Size:</span>
                                            <span class="font-medium">{{ number_format($opportunity['max_size'], 4) }}</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-600 dark:text-gray-400">Est. Fees:</span>
                                            <span class="font-medium">${{ number_format($opportunity['total_fees'], 2) }}</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-600 dark:text-gray-400">Net Profit:</span>
                                            <span class="font-medium text-green-600">${{ number_format($opportunity['net_profit'], 2) }}</span>
                                        </div>
                                        <div>
                                            <span class="text-gray-600 dark:text-gray-400">Time Window:</span>
                                            <span class="font-medium">~{{ $opportunity['execution_time'] }}s</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- Active Strategies -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Automated Arbitrage Strategies</h3>
                        <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-sm">
                            Create Strategy
                        </button>
                    </div>
                    
                    @if($activeStrategies->isEmpty())
                        <p class="text-gray-600 dark:text-gray-400">No active strategies. Create one to automate arbitrage trading.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Strategy
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Pairs
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Min Profit
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Profit (24h)
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($activeStrategies as $strategy)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                {{ $strategy->name }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                {{ implode(', ', json_decode($strategy->pairs)) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                {{ $strategy->min_profit_percentage }}%
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $strategy->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                                    {{ $strategy->is_active ? 'Active' : 'Paused' }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                ${{ number_format($strategy->profit_24h ?? 0, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                                <button class="text-indigo-600 hover:text-indigo-900">
                                                    {{ $strategy->is_active ? 'Pause' : 'Resume' }}
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Historical Performance Chart -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">30-Day Performance</h3>
                    <canvas id="performanceChart" height="100"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Execute Arbitrage Modal -->
    <div id="executeModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">Execute Arbitrage Trade</h3>
            
            <form method="POST" action="{{ route('exchange.external.arbitrage.execute') }}">
                @csrf
                <input type="hidden" id="opportunity_id" name="opportunity_id" value="">
                
                <div class="mb-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Trading Pair</p>
                    <p class="font-semibold" id="modal_pair"></p>
                </div>
                
                <div class="mb-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Expected Profit</p>
                    <p class="text-xl font-bold text-green-600" id="modal_profit"></p>
                </div>
                
                <div class="mb-4">
                    <x-label for="amount" value="{{ __('Trade Amount') }}" />
                    <x-input id="amount" type="number" name="amount" class="mt-1 block w-full" 
                             step="0.00000001" min="0.00000001" required />
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Max: <span id="modal_max_size"></span>
                    </p>
                </div>
                
                <div class="mb-4">
                    <x-label for="slippage_tolerance" value="{{ __('Slippage Tolerance (%)') }}" />
                    <x-input id="slippage_tolerance" type="number" name="slippage_tolerance" 
                             class="mt-1 block w-full" value="0.5" step="0.1" min="0" max="5" required />
                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                        Maximum acceptable price change during execution
                    </p>
                </div>
                
                <div class="mb-4">
                    <x-label for="password" value="{{ __('Confirm Password') }}" />
                    <x-input id="password" type="password" name="password" class="mt-1 block w-full" required />
                </div>
                
                <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-3 mb-4">
                    <p class="text-sm text-amber-700 dark:text-amber-300">
                        <strong>Risk Warning:</strong> Arbitrage trades execute across multiple exchanges. Ensure sufficient balance on both exchanges.
                    </p>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="hideExecuteModal()" 
                            class="px-4 py-2 bg-gray-300 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-600 transition">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                        Execute Trade
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Performance Chart
        const ctx = document.getElementById('performanceChart').getContext('2d');
        const performanceData = @json($historicalPerformance);
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: performanceData.map(d => d.date),
                datasets: [{
                    label: 'Daily Profit ($)',
                    data: performanceData.map(d => d.total_profit),
                    borderColor: 'rgb(34, 197, 94)',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value.toFixed(2);
                            }
                        }
                    }
                }
            }
        });
        
        // Modal functions
        function showExecuteModal(opportunityJson) {
            const opportunity = JSON.parse(opportunityJson);
            document.getElementById('opportunity_id').value = opportunity.id;
            document.getElementById('modal_pair').textContent = opportunity.pair;
            document.getElementById('modal_profit').textContent = '+' + opportunity.profit_percentage.toFixed(2) + '%';
            document.getElementById('modal_max_size').textContent = opportunity.max_size.toFixed(4);
            document.getElementById('amount').max = opportunity.max_size;
            document.getElementById('executeModal').classList.remove('hidden');
        }
        
        function hideExecuteModal() {
            document.getElementById('executeModal').classList.add('hidden');
        }
        
        // Auto-refresh
        let refreshInterval;
        const autoRefreshCheckbox = document.getElementById('autoRefresh');
        
        function startAutoRefresh() {
            refreshInterval = setInterval(function() {
                if (document.visibilityState === 'visible') {
                    window.location.reload();
                }
            }, 10000); // Refresh every 10 seconds
        }
        
        function stopAutoRefresh() {
            clearInterval(refreshInterval);
        }
        
        autoRefreshCheckbox.addEventListener('change', function() {
            if (this.checked) {
                startAutoRefresh();
            } else {
                stopAutoRefresh();
            }
        });
        
        // Start auto-refresh by default
        startAutoRefresh();
    </script>
    @endpush
</x-app-layout>