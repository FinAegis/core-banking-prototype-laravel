<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Exchange Rates') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Controls Bar -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <form id="rateControlsForm" class="flex flex-wrap gap-4 items-end">
                        <!-- Base Currency Selector -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Base Currency
                            </label>
                            <select name="base" id="baseCurrency" 
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500">
                                @foreach($assets->where('type', 'fiat') as $asset)
                                    <option value="{{ $asset->code }}" {{ $baseCurrency === $asset->code ? 'selected' : '' }}>
                                        {{ $asset->code }} - {{ $asset->name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
                        <!-- Currency Selector -->
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Display Currencies
                            </label>
                            <div class="flex flex-wrap gap-2">
                                @foreach($assets as $asset)
                                    <label class="inline-flex items-center">
                                        <input type="checkbox" name="assets[]" value="{{ $asset->code }}"
                                               class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                               {{ in_array($asset->code, $selectedAssets) ? 'checked' : '' }}>
                                        <span class="ml-2 text-sm">{{ $asset->code }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                        
                        <!-- Auto-refresh Toggle -->
                        <div>
                            <label class="inline-flex items-center">
                                <input type="checkbox" id="autoRefresh" checked
                                       class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">Auto-refresh (10s)</span>
                            </label>
                        </div>
                        
                        <!-- Update Button -->
                        <button type="submit" 
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                            Update
                        </button>
                    </form>
                </div>
            </div>

            <!-- Statistics Bar -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Pairs Tracked</p>
                    <p class="text-2xl font-bold">{{ $statistics['pairs_tracked'] ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">24h Updates</p>
                    <p class="text-2xl font-bold">{{ $statistics['total_updates'] ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Data Providers</p>
                    <p class="text-2xl font-bold">{{ $statistics['providers']->count() ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Last Update</p>
                    <p class="text-sm font-medium" id="lastUpdateTime">{{ \Carbon\Carbon::parse($statistics['last_update'] ?? now())->diffForHumans() }}</p>
                </div>
            </div>

            <!-- Exchange Rate Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                @foreach($rates as $currency => $rateData)
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg rate-card" data-currency="{{ $currency }}">
                        <div class="p-6">
                            <!-- Header -->
                            <div class="flex justify-between items-start mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold">{{ $currency }}/{{ $baseCurrency }}</h3>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ $assets->firstWhere('code', $currency)->name ?? $currency }}
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-2xl font-bold rate-value">{{ number_format($rateData['rate'], 4) }}</p>
                                    <p class="text-sm {{ $rateData['change_percent'] >= 0 ? 'text-green-600' : 'text-red-600' }} change-percent">
                                        {{ $rateData['change_percent'] >= 0 ? '+' : '' }}{{ $rateData['change_percent'] }}%
                                    </p>
                                </div>
                            </div>
                            
                            <!-- Mini Chart -->
                            <div class="h-24 bg-gray-100 dark:bg-gray-700 rounded mb-3" id="chart-{{ $currency }}">
                                <canvas id="canvas-{{ $currency }}" width="100%" height="96"></canvas>
                            </div>
                            
                            <!-- Stats -->
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="text-gray-500 dark:text-gray-400">24h Change</p>
                                    <p class="font-medium change-24h {{ $rateData['change_24h'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $rateData['change_24h'] >= 0 ? '+' : '' }}{{ number_format($rateData['change_24h'], 4) }}
                                    </p>
                                </div>
                                <div>
                                    <p class="text-gray-500 dark:text-gray-400">Updated</p>
                                    <p class="font-medium update-time">
                                        {{ \Carbon\Carbon::parse($rateData['last_updated'])->diffForHumans() }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            <!-- Historical Chart -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mt-6">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Historical Rates</h3>
                        <div class="flex gap-2">
                            <button class="period-btn px-3 py-1 text-sm rounded" data-period="24h">24H</button>
                            <button class="period-btn px-3 py-1 text-sm rounded bg-indigo-600 text-white" data-period="7d">7D</button>
                            <button class="period-btn px-3 py-1 text-sm rounded" data-period="30d">30D</button>
                            <button class="period-btn px-3 py-1 text-sm rounded" data-period="90d">90D</button>
                        </div>
                    </div>
                    <div class="h-64">
                        <canvas id="historicalChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        let autoRefreshInterval;
        let charts = {};
        let historicalChart;
        let currentPeriod = '7d';
        
        // Initialize mini charts
        @foreach($rates as $currency => $rateData)
            @if($currency !== $baseCurrency)
                initMiniChart('{{ $currency }}', @json($historicalData[$currency] ?? []));
            @endif
        @endforeach
        
        // Initialize historical chart
        initHistoricalChart();
        
        // Auto-refresh functionality
        function startAutoRefresh() {
            if (document.getElementById('autoRefresh').checked) {
                autoRefreshInterval = setInterval(refreshRates, 10000); // 10 seconds
            }
        }
        
        function stopAutoRefresh() {
            if (autoRefreshInterval) {
                clearInterval(autoRefreshInterval);
            }
        }
        
        // Refresh rates via AJAX
        async function refreshRates() {
            const base = document.getElementById('baseCurrency').value;
            const assets = Array.from(document.querySelectorAll('input[name="assets[]"]:checked'))
                .map(cb => cb.value);
            
            try {
                const response = await fetch('{{ route("exchange-rates.rates") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ base, assets })
                });
                
                const data = await response.json();
                updateRateCards(data.rates);
                document.getElementById('lastUpdateTime').textContent = 'Just now';
            } catch (error) {
                console.error('Failed to refresh rates:', error);
            }
        }
        
        // Update rate cards with new data
        function updateRateCards(rates) {
            Object.entries(rates).forEach(([currency, rateData]) => {
                const card = document.querySelector(`.rate-card[data-currency="${currency}"]`);
                if (!card) return;
                
                // Update values
                card.querySelector('.rate-value').textContent = rateData.rate.toFixed(4);
                
                const changePercent = card.querySelector('.change-percent');
                changePercent.textContent = (rateData.change_percent >= 0 ? '+' : '') + rateData.change_percent + '%';
                changePercent.className = 'text-sm change-percent ' + 
                    (rateData.change_percent >= 0 ? 'text-green-600' : 'text-red-600');
                
                const change24h = card.querySelector('.change-24h');
                change24h.textContent = (rateData.change_24h >= 0 ? '+' : '') + rateData.change_24h.toFixed(4);
                change24h.className = 'font-medium change-24h ' + 
                    (rateData.change_24h >= 0 ? 'text-green-600' : 'text-red-600');
                
                card.querySelector('.update-time').textContent = 'Just now';
                
                // Flash effect
                card.classList.add('bg-yellow-50', 'dark:bg-yellow-900/20');
                setTimeout(() => {
                    card.classList.remove('bg-yellow-50', 'dark:bg-yellow-900/20');
                }, 1000);
            });
        }
        
        // Initialize mini chart
        function initMiniChart(currency, data) {
            const ctx = document.getElementById(`canvas-${currency}`);
            if (!ctx) return;
            
            const chartData = data.map(d => ({ x: d.timestamp, y: d.rate }));
            
            charts[currency] = new Chart(ctx, {
                type: 'line',
                data: {
                    datasets: [{
                        data: chartData,
                        borderColor: '#6366f1',
                        borderWidth: 2,
                        fill: false,
                        tension: 0.1,
                        pointRadius: 0,
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: { enabled: false }
                    },
                    scales: {
                        x: { display: false },
                        y: { display: false }
                    }
                }
            });
        }
        
        // Initialize historical chart
        function initHistoricalChart() {
            const ctx = document.getElementById('historicalChart');
            const datasets = [];
            
            @foreach($rates as $currency => $rateData)
                @if($currency !== $baseCurrency && isset($historicalData[$currency]))
                    datasets.push({
                        label: '{{ $currency }}',
                        data: @json($historicalData[$currency]).map(d => ({ x: d.timestamp, y: d.rate })),
                        borderWidth: 2,
                        fill: false,
                        tension: 0.1,
                    });
                @endif
            @endforeach
            
            historicalChart = new Chart(ctx, {
                type: 'line',
                data: { datasets },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false,
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return context.dataset.label + ': ' + context.parsed.y.toFixed(4);
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
                            beginAtZero: false
                        }
                    }
                }
            });
        }
        
        // Period selector
        document.querySelectorAll('.period-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                // Update button styles
                document.querySelectorAll('.period-btn').forEach(b => {
                    b.classList.remove('bg-indigo-600', 'text-white');
                });
                this.classList.add('bg-indigo-600', 'text-white');
                
                currentPeriod = this.dataset.period;
                await updateHistoricalChart(currentPeriod);
            });
        });
        
        // Update historical chart with new period
        async function updateHistoricalChart(period) {
            // This would fetch new data via AJAX
            // For now, we'll just update the time unit
            if (historicalChart) {
                const unit = period === '24h' ? 'hour' : period === '90d' ? 'week' : 'day';
                historicalChart.options.scales.x.time.unit = unit;
                historicalChart.update();
            }
        }
        
        // Form submission
        document.getElementById('rateControlsForm').addEventListener('submit', function(e) {
            e.preventDefault();
            refreshRates();
        });
        
        // Auto-refresh toggle
        document.getElementById('autoRefresh').addEventListener('change', function() {
            if (this.checked) {
                startAutoRefresh();
            } else {
                stopAutoRefresh();
            }
        });
        
        // Start auto-refresh
        startAutoRefresh();
        
        // Cleanup on page leave
        window.addEventListener('beforeunload', stopAutoRefresh);
    </script>
    @endpush
</x-app-layout>