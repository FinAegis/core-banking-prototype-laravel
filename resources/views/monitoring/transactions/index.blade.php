<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Real-Time Transaction Monitoring') }}
            </h2>
            <div class="flex items-center space-x-2">
                <span class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                    <span class="relative flex h-3 w-3 mr-2">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                    </span>
                    Live Monitoring Active
                </span>
                <button id="pauseMonitoring" class="inline-flex items-center px-3 py-1.5 bg-gray-200 dark:bg-gray-700 rounded-md text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600">
                    <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Pause
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Monitoring Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Transactions/Hour</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                    <span id="transactionsPerHour">2,847</span>
                                </p>
                            </div>
                            <div class="bg-blue-100 dark:bg-blue-900 rounded-full p-3">
                                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Flagged Today</p>
                                <p class="text-2xl font-semibold text-yellow-600 dark:text-yellow-400">
                                    <span id="flaggedToday">47</span>
                                </p>
                            </div>
                            <div class="bg-yellow-100 dark:bg-yellow-900 rounded-full p-3">
                                <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">High Risk</p>
                                <p class="text-2xl font-semibold text-red-600 dark:text-red-400">
                                    <span id="highRiskCount">12</span>
                                </p>
                            </div>
                            <div class="bg-red-100 dark:bg-red-900 rounded-full p-3">
                                <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-4">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Volume</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                    $<span id="totalVolume">4.7M</span>
                                </p>
                            </div>
                            <div class="bg-green-100 dark:bg-green-900 rounded-full p-3">
                                <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Real-time Activity Chart -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Transaction Activity</h3>
                        <div class="flex space-x-2">
                            <button class="text-sm px-3 py-1 rounded-md bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300">1H</button>
                            <button class="text-sm px-3 py-1 rounded-md text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700">6H</button>
                            <button class="text-sm px-3 py-1 rounded-md text-gray-600 dark:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-700">24H</button>
                        </div>
                    </div>
                    <div id="activityChart" class="h-64"></div>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Real-time Transactions Feed -->
                <div class="lg:col-span-2 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white">Live Transaction Feed</h3>
                            <div class="flex items-center space-x-2">
                                <select class="text-sm border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                                    <option>All Transactions</option>
                                    <option>Flagged Only</option>
                                    <option>High Risk</option>
                                    <option>Large Amount</option>
                                </select>
                            </div>
                        </div>
                        
                        <div id="transactionFeed" class="space-y-2 max-h-96 overflow-y-auto">
                            <!-- Transactions will be added here dynamically -->
                            <div class="animate-pulse">
                                <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-3/4 mb-2"></div>
                                <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/2"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alert Rules -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Active Alert Rules</h3>
                        
                        <div class="space-y-3">
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white">Large Transaction</h4>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        Active
                                    </span>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Amount > $10,000</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Triggered: 23 times today</p>
                            </div>

                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white">Velocity Check</h4>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        Active
                                    </span>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">> 5 transactions in 10 minutes</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Triggered: 7 times today</p>
                            </div>

                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white">High-Risk Country</h4>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        Active
                                    </span>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">Sanctioned jurisdictions</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Triggered: 2 times today</p>
                            </div>

                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <h4 class="text-sm font-medium text-gray-900 dark:text-white">Pattern Detection</h4>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                        Learning
                                    </span>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">ML-based anomaly detection</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Triggered: 15 times today</p>
                            </div>
                        </div>

                        <button class="mt-4 w-full inline-flex justify-center items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition">
                            Configure Rules
                        </button>
                    </div>
                </div>
            </div>

            <!-- Recent Alerts -->
            <div class="mt-6 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Recent Alerts</h3>
                        <a href="{{ route('fraud.alerts.index') }}" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300 text-sm font-medium">
                            View All Alerts →
                        </a>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Transaction</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Risk Score</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Alert Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody id="alertsTable" class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <!-- Alerts will be added here dynamically -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        // Real-time activity chart
        var activityOptions = {
            series: [{
                name: 'Transactions',
                data: []
            }],
            chart: {
                id: 'realtime',
                height: 256,
                type: 'line',
                animations: {
                    enabled: true,
                    easing: 'linear',
                    dynamicAnimation: {
                        speed: 1000
                    }
                },
                toolbar: {
                    show: false
                },
                zoom: {
                    enabled: false
                }
            },
            dataLabels: {
                enabled: false
            },
            stroke: {
                curve: 'smooth',
                width: 2
            },
            markers: {
                size: 0
            },
            xaxis: {
                type: 'datetime',
                range: 300000, // 5 minutes
                labels: {
                    formatter: function(value) {
                        return new Date(value).toLocaleTimeString();
                    }
                }
            },
            yaxis: {
                max: 100
            },
            theme: {
                mode: document.documentElement.classList.contains('dark') ? 'dark' : 'light'
            },
            colors: ['#6366F1']
        };

        var activityChart = new ApexCharts(document.querySelector("#activityChart"), activityOptions);
        activityChart.render();

        // Generate initial data
        var data = [];
        var baseTime = new Date().getTime();
        for (var i = -30; i <= 0; i++) {
            data.push({
                x: baseTime + i * 10000,
                y: Math.floor(Math.random() * 40) + 30
            });
        }
        activityChart.updateSeries([{
            data: data
        }]);

        // Transaction types for simulation
        const transactionTypes = ['deposit', 'withdrawal', 'transfer', 'payment'];
        const currencies = ['USD', 'EUR', 'GBP', 'JPY'];
        const alertTypes = ['Large Transaction', 'Velocity Check', 'Geographic Risk', 'Pattern Anomaly'];
        
        // Simulate real-time updates
        var isPaused = false;
        
        document.getElementById('pauseMonitoring').addEventListener('click', function() {
            isPaused = !isPaused;
            this.innerHTML = isPaused ? 
                '<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>Resume' : 
                '<svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 9v6m4-6v6m7-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>Pause';
        });

        function updateData() {
            if (!isPaused) {
                // Update chart
                var newTime = new Date().getTime();
                var newValue = Math.floor(Math.random() * 40) + 30;
                
                data.push({
                    x: newTime,
                    y: newValue
                });
                
                if (data.length > 31) {
                    data.shift();
                }
                
                activityChart.updateSeries([{
                    data: data
                }]);
                
                // Add new transaction to feed
                addTransactionToFeed();
                
                // Update statistics
                updateStatistics();
                
                // Occasionally add an alert
                if (Math.random() > 0.9) {
                    addAlert();
                }
            }
        }

        function addTransactionToFeed() {
            const feed = document.getElementById('transactionFeed');
            const isHighRisk = Math.random() > 0.85;
            const amount = Math.floor(Math.random() * 50000) + 100;
            const type = transactionTypes[Math.floor(Math.random() * transactionTypes.length)];
            const currency = currencies[Math.floor(Math.random() * currencies.length)];
            
            const transaction = document.createElement('div');
            transaction.className = `border ${isHighRisk ? 'border-red-300 dark:border-red-700 bg-red-50 dark:bg-red-900/20' : 'border-gray-200 dark:border-gray-700'} rounded-lg p-3 transition-all duration-300 transform`;
            transaction.style.opacity = '0';
            transaction.innerHTML = `
                <div class="flex justify-between items-start">
                    <div class="flex-1">
                        <div class="flex items-center">
                            <span class="text-sm font-medium text-gray-900 dark:text-white">${type.charAt(0).toUpperCase() + type.slice(1)}</span>
                            ${isHighRisk ? '<span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">High Risk</span>' : ''}
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                            From: ****${Math.floor(Math.random() * 9000) + 1000} → To: ****${Math.floor(Math.random() * 9000) + 1000}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-semibold text-gray-900 dark:text-white">${currency} ${amount.toLocaleString()}</p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">${new Date().toLocaleTimeString()}</p>
                    </div>
                </div>
            `;
            
            feed.insertBefore(transaction, feed.firstChild);
            
            // Remove loading placeholder if it exists
            const placeholder = feed.querySelector('.animate-pulse');
            if (placeholder) {
                placeholder.remove();
            }
            
            // Animate in
            setTimeout(() => {
                transaction.style.opacity = '1';
            }, 10);
            
            // Keep only last 10 transactions
            while (feed.children.length > 10) {
                feed.removeChild(feed.lastChild);
            }
        }

        function addAlert() {
            const alertsTable = document.getElementById('alertsTable');
            const alertType = alertTypes[Math.floor(Math.random() * alertTypes.length)];
            const riskScore = Math.floor(Math.random() * 30) + 70;
            const amount = Math.floor(Math.random() * 100000) + 10000;
            
            const alert = document.createElement('tr');
            alert.className = 'animate-pulse-once';
            alert.innerHTML = `
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                    ${new Date().toLocaleTimeString()}
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                    TXN-${Math.random().toString(36).substr(2, 9).toUpperCase()}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="flex items-center">
                        <span class="text-sm font-semibold text-red-600 dark:text-red-400">${riskScore}</span>
                        <div class="ml-2 w-16 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                            <div class="bg-red-600 h-2 rounded-full" style="width: ${riskScore}%"></div>
                        </div>
                    </div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                    ${alertType}
                </td>
                <td class="px-6 py-4 whitespace-nowrap">
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                        Pending Review
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                    <a href="#" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                        Review
                    </a>
                </td>
            `;
            
            alertsTable.insertBefore(alert, alertsTable.firstChild);
            
            // Keep only last 5 alerts
            while (alertsTable.children.length > 5) {
                alertsTable.removeChild(alertsTable.lastChild);
            }
            
            // Update high risk count
            const highRiskCount = document.getElementById('highRiskCount');
            highRiskCount.textContent = parseInt(highRiskCount.textContent) + 1;
            
            // Update flagged today count
            const flaggedToday = document.getElementById('flaggedToday');
            flaggedToday.textContent = parseInt(flaggedToday.textContent) + 1;
        }

        function updateStatistics() {
            // Update transactions per hour (slight variations)
            const tph = document.getElementById('transactionsPerHour');
            const currentTph = parseInt(tph.textContent.replace(',', ''));
            const change = Math.floor(Math.random() * 21) - 10;
            tph.textContent = (currentTph + change).toLocaleString();
            
            // Update total volume
            const volume = document.getElementById('totalVolume');
            const currentVolume = parseFloat(volume.textContent.replace('M', ''));
            const volumeChange = (Math.random() * 0.1 - 0.05);
            volume.textContent = (currentVolume + volumeChange).toFixed(1) + 'M';
        }

        // Update every 2 seconds
        setInterval(updateData, 2000);
        
        // Add custom animation for alerts
        const style = document.createElement('style');
        style.textContent = `
            @keyframes pulse-once {
                0% { background-color: rgba(251, 191, 36, 0.3); }
                50% { background-color: rgba(251, 191, 36, 0.1); }
                100% { background-color: transparent; }
            }
            .animate-pulse-once {
                animation: pulse-once 2s ease-in-out;
            }
        `;
        document.head.appendChild(style);
    </script>
    @endpush
</x-app-layout>