<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Custodian Integration Status') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <!-- Health Overview -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                @php
                    $totalCustodians = count($custodians);
                    $healthyCustodians = collect($custodians)->where('status', 'healthy')->count();
                    $degradedCustodians = collect($custodians)->where('status', 'degraded')->count();
                    $downCustodians = collect($custodians)->where('status', 'down')->count();
                @endphp
                
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <div class="text-sm text-gray-600 dark:text-gray-400">Total Custodians</div>
                        <div class="text-3xl font-bold text-gray-900 dark:text-gray-100">{{ $totalCustodians }}</div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <div class="text-sm text-gray-600 dark:text-gray-400">Healthy</div>
                        <div class="text-3xl font-bold text-green-600">{{ $healthyCustodians }}</div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <div class="text-sm text-gray-600 dark:text-gray-400">Degraded</div>
                        <div class="text-3xl font-bold text-yellow-600">{{ $degradedCustodians }}</div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <div class="text-sm text-gray-600 dark:text-gray-400">Down</div>
                        <div class="text-3xl font-bold text-red-600">{{ $downCustodians }}</div>
                    </div>
                </div>
            </div>

            <!-- Custodians Status Grid -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Custodian Status</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($custodians as $custodian)
                            <div class="border dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition-shadow">
                                <div class="flex justify-between items-start mb-2">
                                    <div>
                                        <h4 class="font-semibold text-gray-900 dark:text-gray-100">
                                            {{ $custodian['name'] }}
                                        </h4>
                                        <p class="text-sm text-gray-500">{{ $custodian['code'] }}</p>
                                    </div>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full
                                        @if($custodian['status'] === 'healthy') bg-green-100 text-green-800
                                        @elseif($custodian['status'] === 'degraded') bg-yellow-100 text-yellow-800
                                        @else bg-red-100 text-red-800
                                        @endif">
                                        {{ ucfirst($custodian['status']) }}
                                    </span>
                                </div>
                                
                                <div class="space-y-2 text-sm">
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Health Score:</span>
                                        <span class="font-medium">{{ $custodian['health_score'] }}%</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Accounts:</span>
                                        <span class="font-medium">{{ $custodian['accounts'] }}</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Last Sync:</span>
                                        <span class="font-medium">
                                            @if($custodian['last_sync'])
                                                {{ \Carbon\Carbon::parse($custodian['last_sync'])->diffForHumans() }}
                                            @else
                                                Never
                                            @endif
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="mt-4 flex space-x-2">
                                    <a href="{{ route('custodian-integration.show', $custodian['code']) }}" 
                                       class="flex-1 text-center px-3 py-1 bg-blue-600 text-white rounded hover:bg-blue-700 text-sm">
                                        View Details
                                    </a>
                                    <button onclick="testConnection('{{ $custodian['code'] }}')"
                                            class="px-3 py-1 bg-gray-600 text-white rounded hover:bg-gray-700 text-sm">
                                        Test
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Recent Transfers -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Recent Transfers</h3>
                    @if($recentTransfers->isEmpty())
                        <p class="text-gray-500">No recent transfers</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Time</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Custodian</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Type</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">From/To</th>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($recentTransfers as $transfer)
                                        <tr>
                                            <td class="px-4 py-2 text-sm">
                                                {{ $transfer['created_at']->format('H:i:s') }}
                                            </td>
                                            <td class="px-4 py-2 text-sm">{{ $transfer['custodian'] }}</td>
                                            <td class="px-4 py-2 text-sm">{{ ucfirst($transfer['type']) }}</td>
                                            <td class="px-4 py-2 text-sm">
                                                {{ number_format($transfer['amount'] / 100, 2) }} {{ $transfer['currency'] }}
                                            </td>
                                            <td class="px-4 py-2 text-sm">
                                                @if($transfer['type'] === 'outgoing')
                                                    {{ $transfer['source'] }} → {{ $transfer['destination'] }}
                                                @else
                                                    {{ $transfer['destination'] }} ← {{ $transfer['source'] }}
                                                @endif
                                            </td>
                                            <td class="px-4 py-2">
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full
                                                    @if($transfer['status'] === 'completed') bg-green-100 text-green-800
                                                    @elseif($transfer['status'] === 'pending') bg-yellow-100 text-yellow-800
                                                    @else bg-red-100 text-red-800
                                                    @endif">
                                                    {{ ucfirst($transfer['status']) }}
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

            <!-- Webhook Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Webhook Statistics (24h)</h3>
                        <div class="space-y-3">
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Total Webhooks:</span>
                                <span class="font-medium">{{ number_format($webhookStats['total']) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Processed:</span>
                                <span class="font-medium text-green-600">{{ number_format($webhookStats['processed']) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Failed:</span>
                                <span class="font-medium text-red-600">{{ number_format($webhookStats['failed']) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Pending:</span>
                                <span class="font-medium text-yellow-600">{{ number_format($webhookStats['pending']) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600 dark:text-gray-400">Success Rate:</span>
                                <span class="font-medium">{{ $webhookStats['success_rate'] }}%</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Synchronization Status -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Synchronization Status</h3>
                        <div class="space-y-3">
                            @foreach($syncStatus as $sync)
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600 dark:text-gray-400">{{ $sync['custodian'] }}:</span>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full
                                        @if($sync['status'] === 'synced') bg-green-100 text-green-800
                                        @elseif($sync['status'] === 'recent') bg-blue-100 text-blue-800
                                        @elseif($sync['status'] === 'stale') bg-yellow-100 text-yellow-800
                                        @else bg-red-100 text-red-800
                                        @endif">
                                        {{ ucfirst($sync['status']) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
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