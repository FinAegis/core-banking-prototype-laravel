<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Batch Job: {{ $batchJob->name }}
            </h2>
            <a href="{{ route('batch-processing.index') }}" 
               class="text-gray-600 hover:text-gray-900">
                ← Back to Batch Processing
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Job Status -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Job Status</h3>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Status</p>
                                    @php
                                        $statusColors = [
                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                            'processing' => 'bg-blue-100 text-blue-800',
                                            'completed' => 'bg-green-100 text-green-800',
                                            'failed' => 'bg-red-100 text-red-800',
                                            'cancelled' => 'bg-gray-100 text-gray-800',
                                            'completed_with_errors' => 'bg-orange-100 text-orange-800',
                                        ];
                                    @endphp
                                    <span class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full {{ $statusColors[$batchJob->status] ?? 'bg-gray-100 text-gray-800' }}">
                                        {{ str_replace('_', ' ', ucfirst($batchJob->status)) }}
                                    </span>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Type</p>
                                    <p class="font-medium">{{ ucfirst($batchJob->type) }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Created</p>
                                    <p class="font-medium">{{ $batchJob->created_at->format('M j, Y g:i A') }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Scheduled</p>
                                    <p class="font-medium">{{ $batchJob->scheduled_at->format('M j, Y g:i A') }}</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex space-x-2">
                            @if($batchJob->canBeCancelled())
                                <form action="{{ route('batch-processing.cancel', $batchJob) }}" method="POST">
                                    @csrf
                                    <button type="submit" 
                                            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700"
                                            onclick="return confirm('Are you sure you want to cancel this batch job?')">
                                        Cancel Job
                                    </button>
                                </form>
                            @endif
                            
                            @if($batchJob->canBeRetried())
                                <form action="{{ route('batch-processing.retry', $batchJob) }}" method="POST">
                                    @csrf
                                    <button type="submit" 
                                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                                        Retry Failed Items
                                    </button>
                                </form>
                            @endif
                            
                            <a href="{{ route('batch-processing.download', $batchJob) }}" 
                               class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700">
                                Download Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progress -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Progress</h3>
                    
                    <div class="mb-4">
                        <div class="flex justify-between text-sm text-gray-600 mb-2">
                            <span>Overall Progress</span>
                            <span>{{ $batchJob->processed_items }} / {{ $batchJob->total_items }} items</span>
                        </div>
                        <div class="bg-gray-200 dark:bg-gray-700 rounded-full h-4">
                            <div class="bg-indigo-600 h-4 rounded-full transition-all duration-500" 
                                 style="width: {{ $batchJob->progress_percentage }}%"></div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div class="text-center">
                            <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $batchJob->total_items }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Total Items</p>
                        </div>
                        <div class="text-center">
                            <p class="text-3xl font-bold text-green-600">{{ $batchJob->processed_items - $batchJob->failed_items }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Successful</p>
                        </div>
                        <div class="text-center">
                            <p class="text-3xl font-bold text-red-600">{{ $batchJob->failed_items }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Failed</p>
                        </div>
                        <div class="text-center">
                            <p class="text-3xl font-bold text-gray-600">{{ $batchJob->total_items - $batchJob->processed_items }}</p>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Pending</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Statistics</h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Success Rate</p>
                            <p class="text-2xl font-bold">{{ $stats['success_rate'] }}%</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Avg Processing Time</p>
                            <p class="text-2xl font-bold">{{ number_format($stats['avg_processing_time'], 1) }}s</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Duration</p>
                            <p class="text-2xl font-bold">
                                @if($batchJob->completed_at && $batchJob->started_at)
                                    {{ $batchJob->completed_at->diffForHumans($batchJob->started_at, true) }}
                                @elseif($batchJob->started_at)
                                    {{ $batchJob->started_at->diffForHumans(null, true) }}
                                @else
                                    -
                                @endif
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Items</h3>
                    
                    <!-- Filters -->
                    <div class="mb-4 flex flex-wrap gap-2">
                        <button onclick="filterItems('all')" 
                                class="filter-btn px-3 py-1 rounded text-sm font-medium bg-gray-200 text-gray-700">
                            All ({{ $batchJob->items->count() }})
                        </button>
                        <button onclick="filterItems('completed')" 
                                class="filter-btn px-3 py-1 rounded text-sm font-medium bg-gray-100 text-gray-600">
                            Completed ({{ $batchJob->items->where('status', 'completed')->count() }})
                        </button>
                        <button onclick="filterItems('failed')" 
                                class="filter-btn px-3 py-1 rounded text-sm font-medium bg-gray-100 text-gray-600">
                            Failed ({{ $batchJob->items->where('status', 'failed')->count() }})
                        </button>
                        <button onclick="filterItems('pending')" 
                                class="filter-btn px-3 py-1 rounded text-sm font-medium bg-gray-100 text-gray-600">
                            Pending ({{ $batchJob->items->where('status', 'pending')->count() }})
                        </button>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">#</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Details</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Result/Error</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($batchJob->items as $item)
                                    <tr class="item-row" data-status="{{ $item->status }}">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $item->sequence }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">{{ ucfirst($item->type) }}</td>
                                        <td class="px-6 py-4 text-sm">
                                            @if($item->type === 'transfer' || $item->type === 'payment')
                                                <p class="text-xs text-gray-600">From: {{ $item->data['from_account'] ?? 'N/A' }}</p>
                                                <p class="text-xs text-gray-600">To: {{ $item->data['to_account'] ?? 'N/A' }}</p>
                                            @elseif($item->type === 'conversion')
                                                <p class="text-xs text-gray-600">{{ $item->data['from_currency'] ?? 'N/A' }} → {{ $item->data['to_currency'] ?? 'N/A' }}</p>
                                            @endif
                                            @if(!empty($item->data['description']))
                                                <p class="text-xs text-gray-500 mt-1">{{ $item->data['description'] }}</p>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            {{ number_format($item->data['amount'] ?? 0, 2) }} {{ $item->data['currency'] ?? '' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @php
                                                $itemStatusColors = [
                                                    'pending' => 'bg-yellow-100 text-yellow-800',
                                                    'completed' => 'bg-green-100 text-green-800',
                                                    'failed' => 'bg-red-100 text-red-800',
                                                    'cancelled' => 'bg-gray-100 text-gray-800',
                                                ];
                                            @endphp
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $itemStatusColors[$item->status] ?? 'bg-gray-100 text-gray-800' }}">
                                                {{ ucfirst($item->status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm">
                                            @if($item->status === 'completed' && $item->result)
                                                <p class="text-green-600 text-xs">
                                                    @if($item->type === 'conversion' && isset($item->result['converted_amount']))
                                                        Converted: {{ number_format($item->result['converted_amount'] / 100, 2) }}
                                                        @ {{ $item->result['rate'] ?? 'N/A' }}
                                                    @else
                                                        {{ json_encode($item->result) }}
                                                    @endif
                                                </p>
                                            @elseif($item->status === 'failed' && $item->error_message)
                                                <p class="text-red-600 text-xs">{{ $item->error_message }}</p>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto-refresh for processing jobs
        @if(in_array($batchJob->status, ['processing', 'pending']))
            setInterval(function() {
                window.location.reload();
            }, 5000); // Refresh every 5 seconds
        @endif
        
        // Filter items
        function filterItems(status) {
            const rows = document.querySelectorAll('.item-row');
            const buttons = document.querySelectorAll('.filter-btn');
            
            // Update button styles
            buttons.forEach(btn => {
                btn.classList.remove('bg-gray-200', 'text-gray-700');
                btn.classList.add('bg-gray-100', 'text-gray-600');
            });
            
            event.target.classList.remove('bg-gray-100', 'text-gray-600');
            event.target.classList.add('bg-gray-200', 'text-gray-700');
            
            // Show/hide rows
            rows.forEach(row => {
                if (status === 'all' || row.dataset.status === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    </script>
</x-app-layout>