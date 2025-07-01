<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Audit Trail') }}
            </h2>
            <div class="flex space-x-2">
                <button onclick="exportAuditLog()" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring focus:ring-gray-300 disabled:opacity-25 transition">
                    Export Log
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Search and Filters -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <form method="GET" action="{{ route('audit.trail') }}" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <label for="search" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Search</label>
                                <input type="text" name="search" id="search" value="{{ request('search') }}" 
                                    placeholder="Search by user, action, or entity"
                                    class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            
                            <div>
                                <label for="action_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Action Type</label>
                                <select name="action_type" id="action_type" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">All Actions</option>
                                    <option value="create" {{ request('action_type') == 'create' ? 'selected' : '' }}>Create</option>
                                    <option value="update" {{ request('action_type') == 'update' ? 'selected' : '' }}>Update</option>
                                    <option value="delete" {{ request('action_type') == 'delete' ? 'selected' : '' }}>Delete</option>
                                    <option value="view" {{ request('action_type') == 'view' ? 'selected' : '' }}>View</option>
                                    <option value="login" {{ request('action_type') == 'login' ? 'selected' : '' }}>Login</option>
                                    <option value="logout" {{ request('action_type') == 'logout' ? 'selected' : '' }}>Logout</option>
                                    <option value="export" {{ request('action_type') == 'export' ? 'selected' : '' }}>Export</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="entity_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Entity Type</label>
                                <select name="entity_type" id="entity_type" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="">All Entities</option>
                                    <option value="account" {{ request('entity_type') == 'account' ? 'selected' : '' }}>Account</option>
                                    <option value="transaction" {{ request('entity_type') == 'transaction' ? 'selected' : '' }}>Transaction</option>
                                    <option value="user" {{ request('entity_type') == 'user' ? 'selected' : '' }}>User</option>
                                    <option value="report" {{ request('entity_type') == 'report' ? 'selected' : '' }}>Report</option>
                                    <option value="kyc" {{ request('entity_type') == 'kyc' ? 'selected' : '' }}>KYC</option>
                                    <option value="compliance" {{ request('entity_type') == 'compliance' ? 'selected' : '' }}>Compliance</option>
                                </select>
                            </div>
                            
                            <div>
                                <label for="date_range" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date Range</label>
                                <select name="date_range" id="date_range" class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                                    <option value="today" {{ request('date_range') == 'today' ? 'selected' : '' }}>Today</option>
                                    <option value="yesterday" {{ request('date_range') == 'yesterday' ? 'selected' : '' }}>Yesterday</option>
                                    <option value="week" {{ request('date_range', 'week') == 'week' ? 'selected' : '' }}>Last 7 Days</option>
                                    <option value="month" {{ request('date_range') == 'month' ? 'selected' : '' }}>Last 30 Days</option>
                                    <option value="custom" {{ request('date_range') == 'custom' ? 'selected' : '' }}>Custom Range</option>
                                </select>
                            </div>
                        </div>
                        
                        <div id="customDateRange" class="grid grid-cols-1 md:grid-cols-2 gap-4" style="display: none;">
                            <div>
                                <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Start Date</label>
                                <input type="datetime-local" name="start_date" id="start_date" value="{{ request('start_date') }}" 
                                    class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                            <div>
                                <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">End Date</label>
                                <input type="datetime-local" name="end_date" id="end_date" value="{{ request('end_date') }}" 
                                    class="w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500">
                            </div>
                        </div>
                        
                        <div class="flex justify-end space-x-2">
                            <a href="{{ route('audit.trail') }}" class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400 active:bg-gray-500 focus:outline-none focus:border-gray-500 focus:ring focus:ring-gray-300 disabled:opacity-25 transition">
                                Clear Filters
                            </a>
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition">
                                Apply Filters
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Audit Log Table -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Timestamp</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Action</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Entity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">IP Address</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">User Agent</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Details</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse($auditLogs ?? [] as $log)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                            {{ $log->created_at ?? now()->format('Y-m-d H:i:s') }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ $log->user->name ?? 'System' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            @php
                                                $actionColors = [
                                                    'create' => 'green',
                                                    'update' => 'blue',
                                                    'delete' => 'red',
                                                    'view' => 'gray',
                                                    'login' => 'indigo',
                                                    'logout' => 'purple',
                                                    'export' => 'yellow'
                                                ];
                                                $color = $actionColors[$log->action ?? 'view'] ?? 'gray';
                                            @endphp
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $color }}-100 text-{{ $color }}-800 dark:bg-{{ $color }}-900 dark:text-{{ $color }}-200">
                                                {{ ucfirst($log->action ?? 'view') }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <div>
                                                <div class="font-medium">{{ ucfirst($log->entity_type ?? 'account') }}</div>
                                                <div class="text-xs text-gray-400">ID: {{ $log->entity_id ?? 'ACC-123456' }}</div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ $log->ip_address ?? '192.168.1.1' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <div class="truncate max-w-xs" title="{{ $log->user_agent ?? 'Mozilla/5.0' }}">
                                                {{ Str::limit($log->user_agent ?? 'Mozilla/5.0', 30) }}
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <button onclick="showDetails('{{ $log->id ?? 1 }}')" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                                View
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    @foreach([
                                        ['timestamp' => now()->subMinutes(5), 'user' => 'John Doe', 'action' => 'update', 'entity' => 'account', 'entity_id' => 'ACC-789012', 'ip' => '192.168.1.100'],
                                        ['timestamp' => now()->subMinutes(15), 'user' => 'Jane Smith', 'action' => 'create', 'entity' => 'transaction', 'entity_id' => 'TXN-345678', 'ip' => '192.168.1.101'],
                                        ['timestamp' => now()->subMinutes(30), 'user' => 'System', 'action' => 'export', 'entity' => 'report', 'entity_id' => 'RPT-567890', 'ip' => '127.0.0.1'],
                                        ['timestamp' => now()->subHours(1), 'user' => 'Admin User', 'action' => 'delete', 'entity' => 'user', 'entity_id' => 'USR-234567', 'ip' => '192.168.1.102'],
                                        ['timestamp' => now()->subHours(2), 'user' => 'Compliance Officer', 'action' => 'view', 'entity' => 'kyc', 'entity_id' => 'KYC-890123', 'ip' => '192.168.1.103'],
                                    ] as $mockLog)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                {{ $mockLog['timestamp']->format('Y-m-d H:i:s') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {{ $mockLog['user'] }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                @php
                                                    $actionColors = [
                                                        'create' => 'green',
                                                        'update' => 'blue',
                                                        'delete' => 'red',
                                                        'view' => 'gray',
                                                        'export' => 'yellow'
                                                    ];
                                                    $color = $actionColors[$mockLog['action']] ?? 'gray';
                                                @endphp
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $color }}-100 text-{{ $color }}-800 dark:bg-{{ $color }}-900 dark:text-{{ $color }}-200">
                                                    {{ ucfirst($mockLog['action']) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                <div>
                                                    <div class="font-medium">{{ ucfirst($mockLog['entity']) }}</div>
                                                    <div class="text-xs text-gray-400">ID: {{ $mockLog['entity_id'] }}</div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {{ $mockLog['ip'] }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                <div class="truncate max-w-xs" title="Mozilla/5.0 (Windows NT 10.0; Win64; x64)">
                                                    Mozilla/5.0 (Windows NT 10.0...)
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                <button onclick="showDetails('{{ $loop->index }}')" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                                    View
                                                </button>
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Pagination -->
                    <div class="mt-4">
                        @if(isset($auditLogs))
                            {{ $auditLogs->withQueryString()->links() }}
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Details Modal -->
    <div id="detailsModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white mb-4" id="modal-title">
                        Audit Log Details
                    </h3>
                    <div id="modalContent" class="space-y-2 text-sm">
                        <!-- Content will be dynamically inserted here -->
                    </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" onclick="closeModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // Show/hide custom date range
        document.getElementById('date_range').addEventListener('change', function() {
            document.getElementById('customDateRange').style.display = 
                this.value === 'custom' ? 'grid' : 'none';
        });

        // Show details modal
        function showDetails(id) {
            const modal = document.getElementById('detailsModal');
            const content = document.getElementById('modalContent');
            
            // Mock data for demonstration
            const details = {
                'Action': 'Update Account Balance',
                'Previous Value': '$10,000.00',
                'New Value': '$15,000.00',
                'Changed Fields': 'balance, updated_at',
                'Session ID': 'sess_abc123def456',
                'Request ID': 'req_789ghi012jkl',
                'Duration': '125ms',
                'Status': 'Success'
            };
            
            content.innerHTML = Object.entries(details).map(([key, value]) => `
                <div class="flex justify-between">
                    <span class="font-medium text-gray-700 dark:text-gray-300">${key}:</span>
                    <span class="text-gray-600 dark:text-gray-400">${value}</span>
                </div>
            `).join('');
            
            modal.classList.remove('hidden');
        }

        // Close modal
        function closeModal() {
            document.getElementById('detailsModal').classList.add('hidden');
        }

        // Export audit log
        function exportAuditLog() {
            const params = new URLSearchParams(window.location.search);
            params.append('export', 'csv');
            window.location.href = `{{ route('audit.trail') }}?${params.toString()}`;
        }

        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('detailsModal');
            if (event.target === modal) {
                closeModal();
            }
        }
    </script>
    @endpush
</x-app-layout>