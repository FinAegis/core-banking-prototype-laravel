<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('AML/BSA/OFAC Reporting') }}
            </h2>
            <div class="flex space-x-2">
                <button onclick="runSanctionsCheck()" class="inline-flex items-center px-4 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 active:bg-red-900 focus:outline-none focus:border-red-900 focus:ring focus:ring-red-300 disabled:opacity-25 transition">
                    Run Sanctions Check
                </button>
                <a href="{{ route('compliance.aml.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition">
                    New Report
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Compliance Overview -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Active Alerts</p>
                                <p class="text-3xl font-bold text-yellow-600 dark:text-yellow-400">{{ $stats['active_alerts'] ?? 18 }}</p>
                            </div>
                            <div class="p-3 bg-yellow-100 dark:bg-yellow-900 rounded-full">
                                <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">{{ $stats['new_today'] ?? 3 }} new today</p>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">OFAC Matches</p>
                                <p class="text-3xl font-bold text-red-600 dark:text-red-400">{{ $stats['ofac_matches'] ?? 2 }}</p>
                            </div>
                            <div class="p-3 bg-red-100 dark:bg-red-900 rounded-full">
                                <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path>
                                </svg>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Immediate action required</p>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">BSA Reports</p>
                                <p class="text-3xl font-bold text-blue-600 dark:text-blue-400">{{ $stats['bsa_reports'] ?? 45 }}</p>
                            </div>
                            <div class="p-3 bg-blue-100 dark:bg-blue-900 rounded-full">
                                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">{{ $stats['pending_bsa'] ?? 5 }} pending submission</p>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Risk Score</p>
                                <p class="text-3xl font-bold text-green-600 dark:text-green-400">{{ $stats['risk_score'] ?? 'Low' }}</p>
                            </div>
                            <div class="p-3 bg-green-100 dark:bg-green-900 rounded-full">
                                <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">Last assessed: {{ now()->subHours(2)->diffForHumans() }}</p>
                    </div>
                </div>
            </div>

            <!-- Active Sanctions Lists -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Active Sanctions Lists</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        @foreach([
                            ['name' => 'OFAC SDN List', 'status' => 'active', 'updated' => '2 hours ago', 'entries' => '6,312'],
                            ['name' => 'EU Consolidated List', 'status' => 'active', 'updated' => '5 hours ago', 'entries' => '4,891'],
                            ['name' => 'UN Security Council', 'status' => 'active', 'updated' => '1 day ago', 'entries' => '2,156'],
                            ['name' => 'HM Treasury UK', 'status' => 'active', 'updated' => '3 hours ago', 'entries' => '3,428'],
                            ['name' => 'DFAT Australia', 'status' => 'updating', 'updated' => 'Updating...', 'entries' => '1,892'],
                            ['name' => 'WorldCheck', 'status' => 'active', 'updated' => '30 minutes ago', 'entries' => '12,456'],
                        ] as $list)
                            <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                <div class="flex justify-between items-start mb-2">
                                    <h4 class="font-medium text-gray-900 dark:text-white">{{ $list['name'] }}</h4>
                                    @if($list['status'] === 'active')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                            Active
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                            Updating
                                        </span>
                                    @endif
                                </div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">{{ $list['entries'] }} entries</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Updated: {{ $list['updated'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Recent AML Alerts -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Recent AML Alerts</h3>
                        <select class="rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 text-sm">
                            <option>All Alerts</option>
                            <option>High Risk</option>
                            <option>OFAC Matches</option>
                            <option>Unusual Activity</option>
                            <option>Large Transactions</option>
                        </select>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Alert ID</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Entity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Risk Score</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Generated</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach([
                                    ['id' => 'AML-2024-0156', 'type' => 'OFAC Match', 'entity' => 'John Doe (Customer)', 'risk' => 95, 'status' => 'urgent', 'time' => '10 minutes ago'],
                                    ['id' => 'AML-2024-0155', 'type' => 'Unusual Pattern', 'entity' => 'ABC Corp (Business)', 'risk' => 78, 'status' => 'investigating', 'time' => '2 hours ago'],
                                    ['id' => 'AML-2024-0154', 'type' => 'Large Cash', 'entity' => 'Jane Smith (Customer)', 'risk' => 65, 'status' => 'pending', 'time' => '5 hours ago'],
                                    ['id' => 'AML-2024-0153', 'type' => 'Rapid Movement', 'entity' => 'XYZ Ltd (Business)', 'risk' => 82, 'status' => 'investigating', 'time' => '1 day ago'],
                                    ['id' => 'AML-2024-0152', 'type' => 'Geographic Risk', 'entity' => 'Mike Johnson (Customer)', 'risk' => 71, 'status' => 'cleared', 'time' => '2 days ago'],
                                ] as $alert)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $alert['id'] }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            @if($alert['type'] === 'OFAC Match')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                    {{ $alert['type'] }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                    {{ $alert['type'] }}
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ $alert['entity'] }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <div class="flex items-center">
                                                <span class="text-sm font-medium {{ $alert['risk'] > 80 ? 'text-red-600 dark:text-red-400' : ($alert['risk'] > 60 ? 'text-yellow-600 dark:text-yellow-400' : 'text-green-600 dark:text-green-400') }}">
                                                    {{ $alert['risk'] }}
                                                </span>
                                                <div class="ml-2 w-16 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                                    <div class="{{ $alert['risk'] > 80 ? 'bg-red-600' : ($alert['risk'] > 60 ? 'bg-yellow-600' : 'bg-green-600') }} h-2 rounded-full" style="width: {{ $alert['risk'] }}%"></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            @php
                                                $statusColors = [
                                                    'urgent' => 'red',
                                                    'investigating' => 'yellow',
                                                    'pending' => 'blue',
                                                    'cleared' => 'green'
                                                ];
                                                $color = $statusColors[$alert['status']] ?? 'gray';
                                            @endphp
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $color }}-100 text-{{ $color }}-800 dark:bg-{{ $color }}-900 dark:text-{{ $color }}-200">
                                                {{ ucfirst($alert['status']) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ $alert['time'] }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                            <a href="#" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">Investigate</a>
                                            @if($alert['status'] !== 'cleared')
                                                <a href="#" class="text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300">Clear</a>
                                                <a href="#" class="text-red-600 hover:text-red-900 dark:text-red-400 dark:hover:text-red-300">Escalate</a>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- BSA Reporting Status -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">BSA Filing Status</h3>
                        <div class="space-y-3">
                            @foreach([
                                ['type' => 'Currency Transaction Report (CTR)', 'pending' => 3, 'deadline' => '15 days'],
                                ['type' => 'Suspicious Activity Report (SAR)', 'pending' => 2, 'deadline' => '30 days'],
                                ['type' => 'Foreign Bank Account Report (FBAR)', 'pending' => 0, 'deadline' => 'Quarterly'],
                                ['type' => 'Form 8300', 'pending' => 1, 'deadline' => '15 days'],
                            ] as $report)
                                <div class="border-l-4 {{ $report['pending'] > 0 ? 'border-yellow-500' : 'border-green-500' }} pl-4">
                                    <div class="flex justify-between items-start">
                                        <div>
                                            <h4 class="font-medium text-gray-900 dark:text-white">{{ $report['type'] }}</h4>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">Filing deadline: {{ $report['deadline'] }}</p>
                                        </div>
                                        @if($report['pending'] > 0)
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                {{ $report['pending'] }} pending
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                Up to date
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Compliance Actions</h3>
                        <div class="space-y-3">
                            <button onclick="generateBSAReport()" class="w-full text-left px-4 py-3 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg hover:bg-blue-100 dark:hover:bg-blue-900/30 transition">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        <span class="font-medium text-blue-900 dark:text-blue-100">Generate BSA Report</span>
                                    </div>
                                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </div>
                            </button>

                            <button onclick="runRiskAssessment()" class="w-full text-left px-4 py-3 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg hover:bg-yellow-100 dark:hover:bg-yellow-900/30 transition">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                        </svg>
                                        <span class="font-medium text-yellow-900 dark:text-yellow-100">Run Risk Assessment</span>
                                    </div>
                                    <svg class="w-5 h-5 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </div>
                            </button>

                            <button onclick="updateSanctionsList()" class="w-full text-left px-4 py-3 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg hover:bg-green-100 dark:hover:bg-green-900/30 transition">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <svg class="w-5 h-5 text-green-600 dark:text-green-400 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                        </svg>
                                        <span class="font-medium text-green-900 dark:text-green-100">Update Sanctions Lists</span>
                                    </div>
                                    <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                    </svg>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function runSanctionsCheck() {
            if (confirm('Run sanctions check for all active customers? This may take several minutes.')) {
                // Implementation
                alert('Sanctions check initiated. You will be notified when complete.');
            }
        }

        function generateBSAReport() {
            window.location.href = '{{ route("compliance.bsa.create") }}';
        }

        function runRiskAssessment() {
            window.location.href = '{{ route("compliance.risk.assessment") }}';
        }

        function updateSanctionsList() {
            if (confirm('Update all sanctions lists from providers?')) {
                // Implementation
                alert('Sanctions lists update initiated.');
            }
        }
    </script>
    @endpush
</x-app-layout>