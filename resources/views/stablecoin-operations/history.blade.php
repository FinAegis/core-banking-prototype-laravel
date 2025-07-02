<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Stablecoin Operation History') }}
            </h2>
            <a href="{{ route('stablecoin-operations.index') }}" 
               class="text-gray-600 hover:text-gray-900">
                ← Back to Operations
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <form method="GET" action="{{ route('stablecoin-operations.history') }}" class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Operation Type
                            </label>
                            <select name="type" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="all" {{ $filters['type'] == 'all' ? 'selected' : '' }}>All Types</option>
                                <option value="mint" {{ $filters['type'] == 'mint' ? 'selected' : '' }}>Mint</option>
                                <option value="burn" {{ $filters['type'] == 'burn' ? 'selected' : '' }}>Burn</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Stablecoin
                            </label>
                            <select name="stablecoin" class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                                <option value="all" {{ $filters['stablecoin'] == 'all' ? 'selected' : '' }}>All Stablecoins</option>
                                <option value="USDX" {{ $filters['stablecoin'] == 'USDX' ? 'selected' : '' }}>USDX</option>
                                <option value="EURX" {{ $filters['stablecoin'] == 'EURX' ? 'selected' : '' }}>EURX</option>
                                <option value="GBPX" {{ $filters['stablecoin'] == 'GBPX' ? 'selected' : '' }}>GBPX</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                From Date
                            </label>
                            <input type="date" 
                                   name="date_from" 
                                   value="{{ $filters['date_from'] }}"
                                   class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                To Date
                            </label>
                            <input type="date" 
                                   name="date_to" 
                                   value="{{ $filters['date_to'] }}"
                                   class="w-full rounded-md border-gray-300 shadow-sm text-sm">
                        </div>
                        
                        <div class="flex items-end">
                            <button type="submit" 
                                    class="w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm">
                                Apply Filters
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Summary Statistics -->
            @if($summary)
                <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-4">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Total Operations</p>
                            <p class="text-2xl font-bold">{{ number_format($summary['total_operations']) }}</p>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-4">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Mint Operations</p>
                            <p class="text-2xl font-bold text-green-600">{{ number_format($summary['mint_operations']) }}</p>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-4">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Burn Operations</p>
                            <p class="text-2xl font-bold text-red-600">{{ number_format($summary['burn_operations']) }}</p>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-4">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Total Minted</p>
                            <p class="text-xl font-bold">${{ number_format($summary['total_minted'] / 100, 2) }}</p>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-4">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Total Burned</p>
                            <p class="text-xl font-bold">${{ number_format($summary['total_burned'] / 100, 2) }}</p>
                        </div>
                    </div>
                    
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-4">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Net Supply Change</p>
                            <p class="text-xl font-bold {{ $summary['net_supply_change'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                {{ $summary['net_supply_change'] >= 0 ? '+' : '' }}${{ number_format(abs($summary['net_supply_change']) / 100, 2) }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Operations Table -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    @if($operations->isEmpty())
                        <div class="text-center py-8">
                            <p class="text-gray-500">No operations found matching the selected filters.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Date/Time
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Type
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Stablecoin
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Amount
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Collateral
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Operator
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Reason
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($operations as $operation)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                {{ \Carbon\Carbon::parse($operation['created_at'])->format('Y-m-d H:i:s') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                    {{ $operation['type'] === 'mint' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                                    {{ ucfirst($operation['type']) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                {{ $operation['stablecoin'] }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                ${{ number_format($operation['amount'] / 100, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                @if($operation['type'] === 'mint')
                                                    <span class="text-gray-600">
                                                        {{ number_format($operation['collateral_amount'] / 100, 2) }} {{ $operation['collateral_asset'] }}
                                                    </span>
                                                @elseif($operation['return_collateral'] ?? false)
                                                    <span class="text-gray-600">
                                                        {{ number_format($operation['collateral_return'], 2) }} {{ $operation['collateral_asset'] }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">-</span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                {{ $operation['operator'] }}
                                            </td>
                                            <td class="px-6 py-4 text-sm">
                                                <span class="block truncate max-w-xs" title="{{ $operation['reason'] }}">
                                                    {{ $operation['reason'] }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                                    {{ $operation['status'] === 'completed' ? 'bg-green-100 text-green-800' : 
                                                       ($operation['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                                    {{ ucfirst($operation['status']) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <button onclick="showOperationDetails('{{ $operation['id'] }}')" 
                                                        class="text-indigo-600 hover:text-indigo-900">
                                                    View Details
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

            <!-- Export Options -->
            <div class="mt-6 flex justify-end space-x-3">
                <button onclick="exportToCSV()" 
                        class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    Export to CSV
                </button>
                <button onclick="exportToPDF()" 
                        class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                    Export to PDF
                </button>
            </div>
        </div>
    </div>

    <!-- Operation Details Modal -->
    <div id="operationDetailsModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <div class="mt-3">
                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-gray-100">
                    Operation Details
                </h3>
                <div class="mt-2 px-7 py-3">
                    <div id="operationDetailsContent" class="text-sm text-gray-500 dark:text-gray-400">
                        <!-- Details will be loaded here -->
                    </div>
                </div>
                <div class="items-center px-4 py-3">
                    <button onclick="closeModal()" 
                            class="px-4 py-2 bg-gray-500 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-gray-600">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const operations = {!! json_encode($operations) !!};
        
        function showOperationDetails(operationId) {
            const operation = operations.find(op => op.id === operationId);
            if (!operation) return;
            
            let detailsHtml = `
                <div class="space-y-2">
                    <p><strong>Operation ID:</strong> ${operation.id}</p>
                    <p><strong>Type:</strong> ${operation.type}</p>
                    <p><strong>Stablecoin:</strong> ${operation.stablecoin}</p>
                    <p><strong>Amount:</strong> $${(operation.amount / 100).toFixed(2)}</p>
                    <p><strong>Created At:</strong> ${new Date(operation.created_at).toLocaleString()}</p>
                    <p><strong>Operator:</strong> ${operation.operator}</p>
                    <p><strong>Status:</strong> ${operation.status}</p>
                    <p><strong>Reason:</strong> ${operation.reason}</p>
            `;
            
            if (operation.type === 'mint') {
                detailsHtml += `
                    <p><strong>Collateral Asset:</strong> ${operation.collateral_asset}</p>
                    <p><strong>Collateral Amount:</strong> ${(operation.collateral_amount / 100).toFixed(2)} ${operation.collateral_asset}</p>
                    <p><strong>Recipient Account:</strong> ${operation.recipient_account}</p>
                `;
            } else if (operation.type === 'burn') {
                detailsHtml += `
                    <p><strong>Source Account:</strong> ${operation.source_account}</p>
                `;
                if (operation.return_collateral) {
                    detailsHtml += `
                        <p><strong>Collateral Returned:</strong> ${operation.collateral_return.toFixed(2)} ${operation.collateral_asset}</p>
                    `;
                }
            }
            
            if (operation.position_uuid) {
                detailsHtml += `<p><strong>Position UUID:</strong> ${operation.position_uuid}</p>`;
            }
            
            detailsHtml += '</div>';
            
            document.getElementById('operationDetailsContent').innerHTML = detailsHtml;
            document.getElementById('operationDetailsModal').classList.remove('hidden');
        }
        
        function closeModal() {
            document.getElementById('operationDetailsModal').classList.add('hidden');
        }
        
        function exportToCSV() {
            // Build CSV content
            let csv = 'Date/Time,Type,Stablecoin,Amount,Collateral,Operator,Reason,Status\n';
            
            operations.forEach(op => {
                const collateral = op.type === 'mint' 
                    ? `${(op.collateral_amount / 100).toFixed(2)} ${op.collateral_asset}`
                    : (op.return_collateral ? `${op.collateral_return.toFixed(2)} ${op.collateral_asset}` : '-');
                
                csv += `"${new Date(op.created_at).toLocaleString()}","${op.type}","${op.stablecoin}","${(op.amount / 100).toFixed(2)}","${collateral}","${op.operator}","${op.reason}","${op.status}"\n`;
            });
            
            // Download CSV
            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `stablecoin-operations-${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
        }
        
        function exportToPDF() {
            alert('PDF export would be implemented with a library like jsPDF');
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('operationDetailsModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</x-app-layout>