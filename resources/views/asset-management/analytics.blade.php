<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Portfolio Analytics') }}
            </h2>
            <a href="{{ route('asset-management.index') }}" 
               class="text-gray-600 hover:text-gray-900">
                ← Back to Portfolio
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Period Selector -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex space-x-2">
                        @foreach(['7d' => '7 Days', '30d' => '30 Days', '90d' => '90 Days', '1y' => '1 Year'] as $key => $label)
                            <a href="{{ route('asset-management.analytics', ['period' => $key]) }}"
                               class="px-4 py-2 rounded {{ $period === $key ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-700 hover:bg-gray-300' }}">
                                {{ $label }}
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Performance Metrics -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Performance Metrics</h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Total Return</p>
                            <p class="text-2xl font-bold {{ $metrics['total_return'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $metrics['total_return'] >= 0 ? '+' : '' }}{{ $metrics['total_return'] }}%
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Annualized</p>
                            <p class="text-2xl font-bold {{ $metrics['annualized_return'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $metrics['annualized_return'] >= 0 ? '+' : '' }}{{ $metrics['annualized_return'] }}%
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Volatility</p>
                            <p class="text-2xl font-bold">{{ $metrics['volatility'] }}%</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Sharpe Ratio</p>
                            <p class="text-2xl font-bold">{{ $metrics['sharpe_ratio'] }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Best Asset</p>
                            <p class="text-2xl font-bold text-green-600">{{ $metrics['best_performer'] }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Worst Asset</p>
                            <p class="text-2xl font-bold text-red-600">{{ $metrics['worst_performer'] }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Portfolio Value Chart -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Portfolio Value Over Time</h3>
                    <canvas id="portfolioChart" height="100"></canvas>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Risk Analysis -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Risk Analysis</h3>
                        
                        <!-- Risk Score -->
                        <div class="mb-6">
                            <div class="flex justify-between mb-2">
                                <span class="text-sm font-medium">Risk Score</span>
                                <span class="text-sm font-medium">{{ $riskAnalysis['risk_score'] }}/100</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="h-3 rounded-full {{ $riskAnalysis['risk_score'] < 40 ? 'bg-green-600' : ($riskAnalysis['risk_score'] < 70 ? 'bg-yellow-600' : 'bg-red-600') }}"
                                     style="width: {{ $riskAnalysis['risk_score'] }}%"></div>
                            </div>
                            <p class="text-sm text-gray-600 mt-1">Risk Level: {{ $riskAnalysis['risk_level'] }}</p>
                        </div>
                        
                        <!-- Risk Metrics -->
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Value at Risk (95%)</span>
                                <span class="font-medium">${{ number_format($riskAnalysis['var_95'], 2) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-sm text-gray-600">Max Drawdown</span>
                                <span class="font-medium">{{ $riskAnalysis['max_drawdown'] }}%</span>
                            </div>
                        </div>
                        
                        <!-- Recommendations -->
                        <div class="mt-4 pt-4 border-t">
                            <p class="text-sm font-medium mb-2">Recommendations</p>
                            <ul class="space-y-1">
                                @foreach($riskAnalysis['recommendations'] as $recommendation)
                                    <li class="text-sm text-gray-600 flex items-start">
                                        <span class="text-indigo-600 mr-2">•</span>
                                        {{ $recommendation }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Diversification Score -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Diversification Analysis</h3>
                        
                        <!-- Diversification Score -->
                        <div class="text-center mb-6">
                            <div class="inline-flex items-center justify-center w-32 h-32 rounded-full 
                                {{ $diversification['score'] >= 70 ? 'bg-green-100' : ($diversification['score'] >= 40 ? 'bg-yellow-100' : 'bg-red-100') }}">
                                <div class="text-center">
                                    <p class="text-3xl font-bold {{ $diversification['score'] >= 70 ? 'text-green-600' : ($diversification['score'] >= 40 ? 'text-yellow-600' : 'text-red-600') }}">
                                        {{ $diversification['score'] }}
                                    </p>
                                    <p class="text-sm {{ $diversification['score'] >= 70 ? 'text-green-600' : ($diversification['score'] >= 40 ? 'text-yellow-600' : 'text-red-600') }}">
                                        {{ $diversification['rating'] }}
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <p class="text-sm text-gray-600 text-center">{{ $diversification['suggestion'] }}</p>
                        
                        <!-- Visual representation -->
                        <div class="mt-6">
                            <canvas id="diversificationChart" height="200"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/date-fns@2.29.3/index.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3.0.0/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
    <script>
        // Portfolio Value Chart
        const portfolioCtx = document.getElementById('portfolioChart').getContext('2d');
        new Chart(portfolioCtx, {
            type: 'line',
            data: {
                labels: {!! json_encode(array_column($portfolioHistory, 'date')) !!},
                datasets: [{
                    label: 'Portfolio Value',
                    data: {!! json_encode(array_map(function($item) { return $item['value'] / 100; }, $portfolioHistory)) !!},
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
                                return 'Value: $' + context.parsed.y.toFixed(2);
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
                                return '$' + value.toFixed(0);
                            }
                        }
                    }
                }
            }
        });
        
        // Diversification Chart
        const divCtx = document.getElementById('diversificationChart').getContext('2d');
        new Chart(divCtx, {
            type: 'radar',
            data: {
                labels: ['Asset Count', 'Balance Distribution', 'Risk Spread', 'Currency Mix', 'Asset Types'],
                datasets: [{
                    label: 'Current',
                    data: [
                        Math.min(100, {{ $diversification['score'] * 0.8 }}),
                        {{ $diversification['score'] }},
                        Math.min(100, {{ $diversification['score'] * 1.2 }}),
                        Math.min(100, {{ $diversification['score'] * 0.9 }}),
                        Math.min(100, {{ $diversification['score'] * 1.1 }})
                    ],
                    borderColor: '#6366f1',
                    backgroundColor: 'rgba(99, 102, 241, 0.2)',
                    pointBackgroundColor: '#6366f1',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#6366f1'
                }, {
                    label: 'Ideal',
                    data: [90, 90, 90, 90, 90],
                    borderColor: '#10b981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    pointBackgroundColor: '#10b981',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#10b981'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    r: {
                        beginAtZero: true,
                        max: 100,
                        ticks: {
                            stepSize: 20
                        }
                    }
                }
            }
        });
    </script>
</x-app-layout>