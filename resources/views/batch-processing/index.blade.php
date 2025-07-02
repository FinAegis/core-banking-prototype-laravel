<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Batch Processing') }}
            </h2>
            <a href="{{ route('batch-processing.create') }}" 
               class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                Create Batch Job
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Total Jobs (30d)</p>
                    <p class="text-2xl font-bold">{{ $statistics->total_jobs ?? 0 }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Items Processed</p>
                    <p class="text-2xl font-bold">{{ number_format($statistics->processed_items ?? 0) }}</p>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Success Rate</p>
                    <p class="text-2xl font-bold">
                        {{ $statistics->processed_items > 0 
                            ? round((($statistics->processed_items - $statistics->failed_items) / $statistics->processed_items) * 100, 1) 
                            : 0 }}%
                    </p>
                </div>
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg p-4">
                    <p class="text-sm text-gray-600 dark:text-gray-400">Failed Items</p>
                    <p class="text-2xl font-bold text-red-600">{{ $statistics->failed_items ?? 0 }}</p>
                </div>
            </div>

            <!-- Templates -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Quick Start Templates</h3>
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                        @foreach($templates as $template)
                            <a href="{{ route('batch-processing.create', ['template' => $template['id']]) }}"
                               class="p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-indigo-600 transition">
                                <div class="flex items-center mb-2">
                                    <div class="p-2 bg-indigo-100 dark:bg-indigo-900 rounded">
                                        @if($template['icon'] === 'currency-dollar')
                                            <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                        @elseif($template['icon'] === 'shopping-cart')
                                            <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                            </svg>
                                        @elseif($template['icon'] === 'refresh')
                                            <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                            </svg>
                                        @else
                                            <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                            </svg>
                                        @endif
                                    </div>
                                    <h4 class="ml-3 font-medium">{{ $template['name'] }}</h4>
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $template['description'] }}</p>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <form method="GET" action="{{ route('batch-processing.index') }}" class="flex flex-wrap gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                            <select name="status" class="rounded-md border-gray-300 shadow-sm">
                                <option value="all">All Status</option>
                                <option value="pending" {{ $filters['status'] === 'pending' ? 'selected' : '' }}>Pending</option>
                                <option value="processing" {{ $filters['status'] === 'processing' ? 'selected' : '' }}>Processing</option>
                                <option value="completed" {{ $filters['status'] === 'completed' ? 'selected' : '' }}>Completed</option>
                                <option value="failed" {{ $filters['status'] === 'failed' ? 'selected' : '' }}>Failed</option>
                                <option value="cancelled" {{ $filters['status'] === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type</label>
                            <select name="type" class="rounded-md border-gray-300 shadow-sm">
                                <option value="all">All Types</option>
                                <option value="transfer" {{ $filters['type'] === 'transfer' ? 'selected' : '' }}>Transfer</option>
                                <option value="payment" {{ $filters['type'] === 'payment' ? 'selected' : '' }}>Payment</option>
                                <option value="conversion" {{ $filters['type'] === 'conversion' ? 'selected' : '' }}>Conversion</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date From</label>
                            <input type="date" name="date_from" value="{{ $filters['date_from'] }}" 
                                   class="rounded-md border-gray-300 shadow-sm">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Date To</label>
                            <input type="date" name="date_to" value="{{ $filters['date_to'] }}" 
                                   class="rounded-md border-gray-300 shadow-sm">
                        </div>
                        
                        <div class="flex items-end">
                            <button type="submit" class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                                Apply Filters
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Batch Jobs List -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Batch Jobs</h3>
                    
                    @if($batchJobs->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Name
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Type
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Progress
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Created
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($batchJobs as $job)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900 dark:text-white">
                                                    {{ $job->name }}
                                                </div>
                                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                                    {{ $job->total_items }} items
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">
                                                    {{ ucfirst($job->type) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
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
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $statusColors[$job->status] ?? 'bg-gray-100 text-gray-800' }}">
                                                    {{ str_replace('_', ' ', ucfirst($job->status)) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="flex-1 mr-3">
                                                        <div class="bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                                            <div class="bg-indigo-600 h-2 rounded-full" 
                                                                 style="width: {{ $job->progress_percentage }}%"></div>
                                                        </div>
                                                    </div>
                                                    <span class="text-sm text-gray-600 dark:text-gray-400">
                                                        {{ $job->progress_percentage }}%
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {{ $job->created_at->format('M j, Y g:i A') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <div class="flex justify-end space-x-2">
                                                    <a href="{{ route('batch-processing.show', $job) }}" 
                                                       class="text-indigo-600 hover:text-indigo-900">View</a>
                                                    
                                                    @if($job->canBeCancelled())
                                                        <form action="{{ route('batch-processing.cancel', $job) }}" method="POST" class="inline">
                                                            @csrf
                                                            <button type="submit" class="text-red-600 hover:text-red-900"
                                                                    onclick="return confirm('Are you sure you want to cancel this batch job?')">
                                                                Cancel
                                                            </button>
                                                        </form>
                                                    @endif
                                                    
                                                    @if($job->canBeRetried())
                                                        <form action="{{ route('batch-processing.retry', $job) }}" method="POST" class="inline">
                                                            @csrf
                                                            <button type="submit" class="text-green-600 hover:text-green-900">
                                                                Retry
                                                            </button>
                                                        </form>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="mt-4">
                            {{ $batchJobs->links() }}
                        </div>
                    @else
                        <p class="text-gray-500 dark:text-gray-400 text-center py-8">
                            No batch jobs found. <a href="{{ route('batch-processing.create') }}" class="text-indigo-600 hover:text-indigo-900">Create your first batch job</a>.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>