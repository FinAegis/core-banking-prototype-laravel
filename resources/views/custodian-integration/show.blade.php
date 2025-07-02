<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ $custodian['name'] }} - Integration Details
            </h2>
            <a href="{{ route('custodian-integration.index') }}" 
               class="text-gray-600 hover:text-gray-900">
                ← Back to Overview
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Custodian Information -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Custodian Information</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Code</p>
                            <p class="font-medium">{{ $custodian['code'] }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Type</p>
                            <p class="font-medium">{{ ucfirst($custodian['type']) }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Base URL</p>
                            <p class="font-medium">{{ $custodian['base_url'] ?? 'Not configured' }}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Features</p>
                            <div class="flex flex-wrap gap-1 mt-1">
                                @foreach($custodian['features'] as $feature)
                                    <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded">
                                        {{ $feature }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Health Status -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Health Status</h3>
                        <span class="px-3 py-1 text-sm font-semibold rounded-full
                            @if($custodian['health']['status'] === 'healthy') bg-green-100 text-green-800
                            @elseif($custodian['health']['status'] === 'degraded') bg-yellow-100 text-yellow-800
                            @else bg-red-100 text-red-800
                            @endif">
                            {{ ucfirst($custodian['health']['status']) }}
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Health Score</p>
                            <p class="text-2xl font-bold">{{ $custodian['health']['score'] ?? 0 }}%</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Response Time</p>
                            <p class="text-2xl font-bold">{{ $custodian['health']['response_time'] ?? 'N/A' }}ms</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Error Rate</p>
                            <p class="text-2xl font-bold">{{ $custodian['health']['error_rate'] ?? 0 }}%</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Circuit Breaker</p>
                            <p class="text-2xl font-bold">{{ ucfirst($custodian['health']['circuit_breaker_status'] ?? 'closed') }}</p>
                        </div>
                    </div>
                    
                    <!-- Health History Chart -->
                    <div class="mt-6">
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">24 Hour Health History</h4>
                        <canvas id="healthChart" height="100"></canvas>
                    </div>
                </div>
            </div>

            <!-- Actions -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Actions</h3>
                    <div class="flex space-x-3">
                        <button onclick="testConnection('{{ $custodian['code'] }}')"
                                class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                            Test Connection
                        </button>
                        <form method="POST" action="{{ route('custodian-integration.synchronize', $custodian['code']) }}" class="inline">
                            @csrf
                            <button type="submit"
                                    class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                                Synchronize Now
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Connected Accounts -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Connected Accounts ({{ $accounts->count() }})</h3>
                    @if($accounts->isEmpty())
                        <p class="text-gray-500">No accounts connected</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Account</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">External ID</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Balance</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Last Sync</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($accounts as $account)
                                        <tr>
                                            <td class="px-4 py-2 text-sm">
                                                {{ $account->account->name ?? 'Unknown' }}
                                            </td>
                                            <td class="px-4 py-2 text-sm font-mono">
                                                {{ $account->external_account_id }}
                                            </td>
                                            <td class="px-4 py-2 text-sm">
                                                {{ number_format($account->balance / 100, 2) }} {{ $account->currency }}
                                            </td>
                                            <td class="px-4 py-2 text-sm">
                                                {{ $account->last_synced_at ? $account->last_synced_at->diffForHumans() : 'Never' }}
                                            </td>
                                            <td class="px-4 py-2">
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full
                                                    {{ $account->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                                    {{ $account->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Recent Transfers -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Recent Transfers ({{ $transfers->count() }})</h3>
                    @if($transfers->isEmpty())
                        <p class="text-gray-500">No transfers found</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Reference</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($transfers as $transfer)
                                        <tr>
                                            <td class="px-4 py-2 text-sm">
                                                {{ $transfer->created_at->format('Y-m-d H:i:s') }}
                                            </td>
                                            <td class="px-4 py-2 text-sm">
                                                {{ ucfirst($transfer->type) }}
                                            </td>
                                            <td class="px-4 py-2 text-sm">
                                                {{ number_format($transfer->amount / 100, 2) }} {{ $transfer->currency }}
                                            </td>
                                            <td class="px-4 py-2 text-sm font-mono text-xs">
                                                {{ $transfer->reference }}
                                            </td>
                                            <td class="px-4 py-2">
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full
                                                    @if($transfer->status === 'completed') bg-green-100 text-green-800
                                                    @elseif($transfer->status === 'pending') bg-yellow-100 text-yellow-800
                                                    @else bg-red-100 text-red-800
                                                    @endif">
                                                    {{ ucfirst($transfer->status) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Webhook History -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Recent Webhooks ({{ $webhooks->count() }})</h3>
                    @if($webhooks->isEmpty())
                        <p class="text-gray-500">No webhooks received</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Event Type</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Resource</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Processing Time</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($webhooks as $webhook)
                                        <tr>
                                            <td class="px-4 py-2 text-sm">
                                                {{ $webhook->created_at->format('H:i:s') }}
                                            </td>
                                            <td class="px-4 py-2 text-sm">
                                                {{ $webhook->event_type }}
                                            </td>
                                            <td class="px-4 py-2 text-sm font-mono text-xs">
                                                {{ $webhook->resource_id }}
                                            </td>
                                            <td class="px-4 py-2">
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full
                                                    @if($webhook->status === 'processed') bg-green-100 text-green-800
                                                    @elseif($webhook->status === 'pending') bg-yellow-100 text-yellow-800
                                                    @else bg-red-100 text-red-800
                                                    @endif">
                                                    {{ ucfirst($webhook->status) }}
                                                </span>
                                            </td>
                                            <td class="px-4 py-2 text-sm">
                                                @if($webhook->processed_at)
                                                    {{ round($webhook->created_at->diffInMilliseconds($webhook->processed_at)) }}ms
                                                @else
                                                    -
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Health History Chart
        const ctx = document.getElementById('healthChart').getContext('2d');
        const healthHistory = {!! json_encode($healthHistory) !!};
        
        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: healthHistory.map(h => new Date(h.timestamp).getHours() + ':00'),
                datasets: [{
                    label: 'Health Score',
                    data: healthHistory.map(h => h.score),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4
                }, {
                    label: 'Response Time (ms)',
                    data: healthHistory.map(h => h.response_time / 10), // Scale down for visibility
                    borderColor: 'rgb(16, 185, 129)',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top',
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        min: 0,
                        max: 100,
                        title: {
                            display: true,
                            text: 'Health Score %'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        },
                        title: {
                            display: true,
                            text: 'Response Time (x10 ms)'
                        }
                    }
                }
            }
        });

        function testConnection(custodianCode) {
            fetch(`/custodian-integration/${custodianCode}/test-connection`, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Connection successful: ${data.message}`);
                    location.reload(); // Refresh to show updated status
                } else {
                    alert(`Connection failed: ${data.message}`);
                }
            })
            .catch(error => {
                alert(`Error testing connection: ${error.message}`);
            });
        }
    </script>
</x-app-layout>