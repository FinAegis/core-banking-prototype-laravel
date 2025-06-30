<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Fraud Alerts Dashboard') }}
            </h2>
            @can('export_fraud_data')
                <a href="{{ route('fraud.alerts.export', request()->all()) }}" class="inline-flex items-center px-4 py-2 bg-green-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-700 active:bg-green-900 focus:outline-none focus:border-green-900 focus:ring ring-green-300 disabled:opacity-25 transition ease-in-out duration-150">
                    <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    Export to CSV
                </a>
            @endcan
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Filters -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <form method="GET" action="{{ route('fraud.alerts.index') }}" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-4 gap-4">
                            <!-- Search -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Search</label>
                                <input type="text" name="search" value="{{ request('search') }}" 
                                    placeholder="Case number or description"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                            
                            <!-- Status Filter -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                                <select name="status" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">All Statuses</option>
                                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                                    <option value="investigating" {{ request('status') == 'investigating' ? 'selected' : '' }}>Investigating</option>
                                    <option value="confirmed" {{ request('status') == 'confirmed' ? 'selected' : '' }}>Confirmed</option>
                                    <option value="false_positive" {{ request('status') == 'false_positive' ? 'selected' : '' }}>False Positive</option>
                                    <option value="resolved" {{ request('status') == 'resolved' ? 'selected' : '' }}>Resolved</option>
                                </select>
                            </div>
                            
                            <!-- Type Filter -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Type</label>
                                <select name="type" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">All Types</option>
                                    @foreach(\App\Models\FraudCase::FRAUD_TYPES as $key => $label)
                                        <option value="{{ $key }}" {{ request('type') == $key ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <!-- Severity Filter -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Severity</label>
                                <select name="severity" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">All Severities</option>
                                    <option value="low" {{ request('severity') == 'low' ? 'selected' : '' }}>Low</option>
                                    <option value="medium" {{ request('severity') == 'medium' ? 'selected' : '' }}>Medium</option>
                                    <option value="high" {{ request('severity') == 'high' ? 'selected' : '' }}>High</option>
                                    <option value="critical" {{ request('severity') == 'critical' ? 'selected' : '' }}>Critical</option>
                                </select>
                            </div>
                            
                            <!-- Risk Score -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Min Risk Score</label>
                                <input type="number" name="risk_score_min" value="{{ request('risk_score_min') }}" 
                                    min="0" max="100" placeholder="0-100"
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                            
                            <!-- Date From -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Date From</label>
                                <input type="date" name="date_from" value="{{ request('date_from') }}" 
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                            
                            <!-- Date To -->
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">Date To</label>
                                <input type="date" name="date_to" value="{{ request('date_to') }}" 
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            </div>
                        </div>
                        
                        <div class="flex gap-2">
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring ring-indigo-300 disabled:opacity-25 transition ease-in-out duration-150">
                                Apply Filters
                            </button>
                            <a href="{{ route('fraud.alerts.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                                Clear Filters
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-6 gap-4 mb-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-indigo-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                </svg>
                            </div>
                            <div class="ml-5">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                        Total Cases
                                    </dt>
                                    <dd class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ number_format($stats['total_cases']) }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-yellow-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-5">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                        Pending
                                    </dt>
                                    <dd class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ number_format($stats['pending_cases']) }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-5">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                        Investigating
                                    </dt>
                                    <dd class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ number_format($stats['investigating_cases']) }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-red-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                            </div>
                            <div class="ml-5">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                        Confirmed
                                    </dt>
                                    <dd class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ number_format($stats['confirmed_cases']) }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <div class="ml-5">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                        False Positives
                                    </dt>
                                    <dd class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ number_format($stats['false_positives']) }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 bg-gray-500 rounded-md p-3">
                                <svg class="h-6 w-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                            </div>
                            <div class="ml-5">
                                <dl>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400 truncate">
                                        Resolved
                                    </dt>
                                    <dd class="text-lg font-semibold text-gray-900 dark:text-white">
                                        {{ number_format($stats['resolved_cases']) }}
                                    </dd>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Row -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                <!-- Trend Chart -->
                <div class="lg:col-span-2 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Fraud Detection Trend (Last 30 Days)</h3>
                        <canvas id="trendChart" width="400" height="200"></canvas>
                    </div>
                </div>

                <!-- Risk Distribution -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Risk Score Distribution</h3>
                        <canvas id="riskChart" width="200" height="200"></canvas>
                    </div>
                </div>
            </div>

            <!-- Type Distribution -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Fraud Type Distribution</h3>
                    <canvas id="typeChart" width="400" height="100"></canvas>
                </div>
            </div>

            <!-- Fraud Cases Table -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6">
                    <div class="mb-4 flex justify-between items-center">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Fraud Cases</h3>
                        <div class="text-sm text-gray-500 dark:text-gray-400">
                            Showing {{ $fraudCases->firstItem() ?? 0 }} to {{ $fraudCases->lastItem() ?? 0 }} of {{ $fraudCases->total() }} results
                        </div>
                    </div>

                    @if($fraudCases->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <a href="{{ route('fraud.alerts.index', array_merge(request()->all(), ['sort' => 'case_number', 'direction' => request('sort') == 'case_number' && request('direction') == 'asc' ? 'desc' : 'asc'])) }}" class="group inline-flex">
                                                Case Number
                                                @if(request('sort') == 'case_number')
                                                    @if(request('direction') == 'asc')
                                                        <svg class="ml-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd"/>
                                                        </svg>
                                                    @else
                                                        <svg class="ml-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                                        </svg>
                                                    @endif
                                                @endif
                                            </a>
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Type
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Severity
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <a href="{{ route('fraud.alerts.index', array_merge(request()->all(), ['sort' => 'risk_score', 'direction' => request('sort') == 'risk_score' && request('direction') == 'desc' ? 'asc' : 'desc'])) }}" class="group inline-flex">
                                                Risk Score
                                                @if(request('sort') == 'risk_score')
                                                    @if(request('direction') == 'asc')
                                                        <svg class="ml-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd"/>
                                                        </svg>
                                                    @else
                                                        <svg class="ml-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                                        </svg>
                                                    @endif
                                                @endif
                                            </a>
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Amount
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            <a href="{{ route('fraud.alerts.index', array_merge(request()->all(), ['sort' => 'detected_at', 'direction' => request('sort') == 'detected_at' && request('direction') == 'desc' ? 'asc' : 'desc'])) }}" class="group inline-flex">
                                                Date
                                                @if(request('sort') == 'detected_at' || !request('sort'))
                                                    @if(request('direction') == 'asc')
                                                        <svg class="ml-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd"/>
                                                        </svg>
                                                    @else
                                                        <svg class="ml-1 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                                                            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"/>
                                                        </svg>
                                                    @endif
                                                @endif
                                            </a>
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">
                                            Action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($fraudCases as $case)
                                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-white">
                                                {{ $case->case_number }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                                    {{ ucfirst(str_replace('_', ' ', $case->type)) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                @switch($case->status)
                                                    @case('pending')
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                            Pending
                                                        </span>
                                                        @break
                                                    @case('investigating')
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                            Investigating
                                                        </span>
                                                        @break
                                                    @case('confirmed')
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                            Confirmed
                                                        </span>
                                                        @break
                                                    @case('false_positive')
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                            False Positive
                                                        </span>
                                                        @break
                                                    @case('resolved')
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200">
                                                            Resolved
                                                        </span>
                                                        @break
                                                @endswitch
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                @switch($case->severity)
                                                    @case('critical')
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                            Critical
                                                        </span>
                                                        @break
                                                    @case('high')
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800 dark:bg-orange-900 dark:text-orange-200">
                                                            High
                                                        </span>
                                                        @break
                                                    @case('medium')
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                            Medium
                                                        </span>
                                                        @break
                                                    @case('low')
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                                            Low
                                                        </span>
                                                        @break
                                                @endswitch
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                <div class="flex items-center">
                                                    <span class="text-sm font-medium {{ $case->risk_score >= 80 ? 'text-red-600' : ($case->risk_score >= 60 ? 'text-yellow-600' : 'text-green-600') }}">
                                                        {{ $case->risk_score }}%
                                                    </span>
                                                    <div class="ml-2 w-16 bg-gray-200 rounded-full h-2">
                                                        <div class="h-2 rounded-full {{ $case->risk_score >= 80 ? 'bg-red-600' : ($case->risk_score >= 60 ? 'bg-yellow-600' : 'bg-green-600') }}" 
                                                             style="width: {{ $case->risk_score }}%"></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                @if($case->amount)
                                                    ${{ number_format($case->amount, 2) }} {{ $case->currency ?? 'USD' }}
                                                @else
                                                    N/A
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                                {{ $case->detected_at->format('M d, Y H:i') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="{{ route('fraud.alerts.show', $case) }}" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">
                                                    View Details
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="mt-4">
                            {{ $fraudCases->links() }}
                        </div>
                    @else
                        <div class="text-center py-12">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No fraud cases found</h3>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                @if(request()->hasAny(['search', 'status', 'type', 'severity', 'risk_score_min', 'date_from', 'date_to']))
                                    Try adjusting your filters.
                                @else
                                    No fraud cases have been detected.
                                @endif
                            </p>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Information Box for Customers -->
            @if(auth()->user()->hasRole(['customer_private', 'customer_business']))
                <div class="mt-6 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                                About Fraud Protection
                            </h3>
                            <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                                <p>We monitor all transactions 24/7 to protect your account from fraudulent activity. If we detect any suspicious activity, we'll:</p>
                                <ul class="list-disc list-inside mt-2">
                                    <li>Alert you immediately via email and SMS</li>
                                    <li>Temporarily block the suspicious transaction</li>
                                    <li>Work with you to verify and resolve the issue</li>
                                </ul>
                                <p class="mt-2">If you notice any unauthorized transactions, please contact our support team immediately.</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Dark mode detection
        const isDarkMode = document.documentElement.classList.contains('dark');
        const textColor = isDarkMode ? '#E5E7EB' : '#374151';
        const gridColor = isDarkMode ? '#374151' : '#E5E7EB';
        
        // Trend Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: {!! json_encode(array_column($trendData, 'date')) !!},
                datasets: [{
                    label: 'Fraud Cases',
                    data: {!! json_encode(array_column($trendData, 'count')) !!},
                    borderColor: 'rgb(99, 102, 241)',
                    backgroundColor: 'rgba(99, 102, 241, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            color: textColor
                        },
                        grid: {
                            color: gridColor
                        }
                    },
                    x: {
                        ticks: {
                            color: textColor
                        },
                        grid: {
                            color: gridColor
                        }
                    }
                }
            }
        });
        
        // Risk Distribution Chart
        const riskCtx = document.getElementById('riskChart').getContext('2d');
        new Chart(riskCtx, {
            type: 'doughnut',
            data: {
                labels: ['Low (0-30)', 'Medium (31-60)', 'High (61-80)', 'Critical (80+)'],
                datasets: [{
                    data: [
                        {{ $riskDistribution['low'] }},
                        {{ $riskDistribution['medium'] }},
                        {{ $riskDistribution['high'] }},
                        {{ $riskDistribution['critical'] }}
                    ],
                    backgroundColor: [
                        'rgba(34, 197, 94, 0.8)',
                        'rgba(250, 204, 21, 0.8)',
                        'rgba(251, 146, 60, 0.8)',
                        'rgba(239, 68, 68, 0.8)'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            color: textColor
                        }
                    }
                }
            }
        });
        
        // Type Distribution Chart
        const typeCtx = document.getElementById('typeChart').getContext('2d');
        new Chart(typeCtx, {
            type: 'bar',
            data: {
                labels: {!! json_encode(array_map(function($type) { return ucfirst(str_replace('_', ' ', $type)); }, array_keys($typeDistribution))) !!},
                datasets: [{
                    label: 'Cases',
                    data: {!! json_encode(array_values($typeDistribution)) !!},
                    backgroundColor: 'rgba(99, 102, 241, 0.8)'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            color: textColor
                        },
                        grid: {
                            color: gridColor
                        }
                    },
                    x: {
                        ticks: {
                            color: textColor
                        },
                        grid: {
                            color: gridColor
                        }
                    }
                }
            }
        });
    </script>
    @endpush
</x-app-layout>