<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Regulatory Report Details') }}
            </h2>
            <div class="space-x-2">
                @if($report->file_path)
                    <a href="{{ route('regulatory.reports.download', $report) }}" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 active:bg-green-900 focus:outline-none focus:border-green-900 focus:ring focus:ring-green-300 disabled:opacity-25 transition">
                        Download Report
                    </a>
                @endif
                @if($report->status === 'pending_submission')
                    <form action="{{ route('regulatory.reports.submit', $report) }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring focus:ring-blue-300 disabled:opacity-25 transition">
                            Submit to Authority
                        </button>
                    </form>
                @endif
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Back link -->
            <div class="mb-6">
                <a href="{{ route('regulatory.reports.index') }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 font-medium">
                    ← Back to reports
                </a>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Report Details -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Basic Information -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Report Information</h3>
                            
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-6">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Report ID</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white font-mono">{{ $report->report_id }}</dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Type</dt>
                                    <dd class="mt-1">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                            {{ $report->type === 'ctr' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : 
                                               ($report->type === 'sar' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : 
                                                'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200') }}">
                                            {{ strtoupper($report->type) }}
                                        </span>
                                    </dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                                    <dd class="mt-1">
                                        @switch($report->status)
                                            @case('draft')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200">
                                                    Draft
                                                </span>
                                                @break
                                            @case('pending_submission')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                    Pending Submission
                                                </span>
                                                @break
                                            @case('submitted')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                    Submitted
                                                </span>
                                                @break
                                            @case('failed')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                    Failed
                                                </span>
                                                @break
                                        @endswitch
                                    </dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Jurisdiction</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                        {{ $report->metadata['jurisdiction'] ?? 'N/A' }}
                                    </dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Reporting Period</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                        {{ \Carbon\Carbon::parse($report->reporting_period_start)->format('M d, Y') }} - 
                                        {{ \Carbon\Carbon::parse($report->reporting_period_end)->format('M d, Y') }}
                                    </dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Generated</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                        {{ $report->created_at->format('M d, Y H:i') }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Report Summary -->
                    @if($report->summary)
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Executive Summary</h3>
                                
                                <div class="prose dark:prose-invert max-w-none">
                                    {!! nl2br(e($report->summary)) !!}
                                </div>
                                
                                @if($report->metadata['total_transactions'] ?? false)
                                    <div class="mt-6 grid grid-cols-2 gap-4">
                                        <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                                            <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Transactions</dt>
                                            <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                                                {{ number_format($report->metadata['total_transactions']) }}
                                            </dd>
                                        </div>
                                        
                                        @if($report->metadata['total_amount'] ?? false)
                                            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                                                <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Amount</dt>
                                                <dd class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                                                    ${{ number_format($report->metadata['total_amount'] / 100, 2) }}
                                                </dd>
                                            </div>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endif

                    <!-- SAR Details -->
                    @if($report->type === 'sar' && isset($report->metadata['suspicious_activities']))
                        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 overflow-hidden shadow-xl sm:rounded-lg">
                            <div class="p-6">
                                <div class="flex items-center mb-4">
                                    <svg class="h-5 w-5 text-red-400 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                    <h3 class="text-lg font-medium text-red-900 dark:text-red-100">Suspicious Activities</h3>
                                </div>
                                
                                <div class="space-y-4">
                                    @foreach($report->metadata['suspicious_activities'] as $activity)
                                        <div class="bg-white dark:bg-gray-800 rounded-lg p-4">
                                            <h4 class="font-medium text-gray-900 dark:text-white">{{ $activity['type'] }}</h4>
                                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $activity['description'] }}</p>
                                            <p class="mt-2 text-xs text-gray-500 dark:text-gray-500">
                                                Risk Score: <span class="font-semibold">{{ $activity['risk_score'] ?? 'N/A' }}</span>
                                            </p>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Included Transactions -->
                    @if($report->metadata['include_details'] ?? false)
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Included Transactions</h3>
                                
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead class="bg-gray-50 dark:bg-gray-700">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                    Transaction ID
                                                </th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                    Date
                                                </th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                    Amount
                                                </th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                                    Type
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                            @forelse($report->metadata['transactions'] ?? [] as $transaction)
                                                <tr>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-900 dark:text-white">
                                                        {{ substr($transaction['id'], 0, 8) }}...
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                        {{ \Carbon\Carbon::parse($transaction['date'])->format('M d, Y') }}
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                                        ${{ number_format($transaction['amount'] / 100, 2) }}
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                        {{ ucfirst($transaction['type']) }}
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                                                        No transaction details available
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Sidebar -->
                <div class="space-y-6">
                    <!-- Actions -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Actions</h3>
                            
                            <div class="space-y-3">
                                @if($report->file_path)
                                    <a href="{{ route('regulatory.reports.download', $report) }}" class="w-full inline-flex justify-center items-center px-4 py-2 bg-gray-100 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-200 dark:hover:bg-gray-600 focus:outline-none focus:border-gray-300 focus:ring focus:ring-gray-200 disabled:opacity-25 transition">
                                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                        </svg>
                                        Download PDF
                                    </a>
                                @endif
                                
                                @if($report->status === 'pending_submission')
                                    <form action="{{ route('regulatory.reports.submit', $report) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 active:bg-blue-900 focus:outline-none focus:border-blue-900 focus:ring focus:ring-blue-300 disabled:opacity-25 transition">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                            </svg>
                                            Submit Report
                                        </button>
                                    </form>
                                @endif
                                
                                <a href="{{ route('regulatory.reports.index') }}" class="w-full inline-flex justify-center items-center px-4 py-2 bg-gray-300 dark:bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-400 dark:hover:bg-gray-500 focus:outline-none focus:border-gray-500 focus:ring focus:ring-gray-300 disabled:opacity-25 transition">
                                    Back to Reports
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Audit Trail -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Audit Trail</h3>
                            
                            <div class="flow-root">
                                <ul class="-mb-8">
                                    @if($report->submitted_at)
                                        <li>
                                            <div class="relative pb-8">
                                                <span class="absolute top-4 left-4 -ml-px h-full w-0.5 bg-gray-200 dark:bg-gray-700" aria-hidden="true"></span>
                                                <div class="relative flex space-x-3">
                                                    <div>
                                                        <span class="h-8 w-8 rounded-full bg-green-500 flex items-center justify-center ring-8 ring-white dark:ring-gray-800">
                                                            <svg class="h-5 w-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                                            </svg>
                                                        </span>
                                                    </div>
                                                    <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                                        <div>
                                                            <p class="text-sm text-gray-900 dark:text-white">
                                                                Submitted to authority
                                                            </p>
                                                        </div>
                                                        <div class="text-right text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                                            {{ $report->submitted_at->format('M d, Y H:i') }}
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </li>
                                    @endif
                                    
                                    <li>
                                        <div class="relative pb-8">
                                            <div class="relative flex space-x-3">
                                                <div>
                                                    <span class="h-8 w-8 rounded-full bg-gray-400 flex items-center justify-center ring-8 ring-white dark:ring-gray-800">
                                                        <svg class="h-5 w-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                                                        </svg>
                                                    </span>
                                                </div>
                                                <div class="min-w-0 flex-1 pt-1.5 flex justify-between space-x-4">
                                                    <div>
                                                        <p class="text-sm text-gray-900 dark:text-white">
                                                            Report generated by {{ $report->generatedBy ? $report->generatedBy->name : 'System' }}
                                                        </p>
                                                    </div>
                                                    <div class="text-right text-sm whitespace-nowrap text-gray-500 dark:text-gray-400">
                                                        {{ $report->created_at->format('M d, Y H:i') }}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Compliance Notes -->
                    @if($report->type === 'sar')
                        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 overflow-hidden shadow-xl sm:rounded-lg">
                            <div class="p-6">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                        </svg>
                                    </div>
                                    <div class="ml-3">
                                        <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                            SAR Confidentiality
                                        </h3>
                                        <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                                            <p>This report contains confidential information and must not be disclosed to the subject of the report or any unauthorized parties.</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>