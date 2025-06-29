<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Fraud Case Details') }} - {{ $fraudCase->case_number }}
            </h2>
            <a href="{{ route('fraud.alerts.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:bg-gray-700 dark:focus:bg-white active:bg-gray-900 dark:active:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                Back to List
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Main Details -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Case Overview -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Case Overview</h3>
                            
                            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-6">
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Case Type</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200">
                                            {{ ucfirst(str_replace('_', ' ', $fraudCase->type)) }}
                                        </span>
                                    </dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                                    <dd class="mt-1 text-sm">
                                        @switch($fraudCase->status)
                                            @case('pending')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200">
                                                    Pending Review
                                                </span>
                                                @break
                                            @case('investigating')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                                    Under Investigation
                                                </span>
                                                @break
                                            @case('confirmed')
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200">
                                                    Confirmed Fraud
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
                                    </dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Risk Score</dt>
                                    <dd class="mt-1">
                                        <div class="flex items-center">
                                            <span class="text-2xl font-bold {{ $fraudCase->risk_score >= 80 ? 'text-red-600' : ($fraudCase->risk_score >= 60 ? 'text-yellow-600' : 'text-green-600') }}">
                                                {{ $fraudCase->risk_score }}%
                                            </span>
                                        </div>
                                        <div class="mt-2 w-full bg-gray-200 rounded-full h-2">
                                            <div class="h-2 rounded-full {{ $fraudCase->risk_score >= 80 ? 'bg-red-600' : ($fraudCase->risk_score >= 60 ? 'bg-yellow-600' : 'bg-green-600') }}" 
                                                 style="width: {{ $fraudCase->risk_score }}%"></div>
                                        </div>
                                    </dd>
                                </div>
                                
                                <div>
                                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Reported Date</dt>
                                    <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                        {{ $fraudCase->created_at->format('M d, Y H:i') }}
                                    </dd>
                                </div>
                            </dl>
                        </div>
                    </div>

                    <!-- Transaction Details -->
                    @if($fraudCase->transaction)
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Transaction Details</h3>
                                
                                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-6">
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Transaction ID</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white font-mono">
                                            {{ $fraudCase->transaction->reference }}
                                        </dd>
                                    </div>
                                    
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Amount</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white font-semibold">
                                            ${{ number_format($fraudCase->transaction->amount / 100, 2) }} {{ $fraudCase->transaction->currency }}
                                        </dd>
                                    </div>
                                    
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Type</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-white">
                                            {{ ucfirst($fraudCase->transaction->type) }}
                                        </dd>
                                    </div>
                                    
                                    <div>
                                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Status</dt>
                                        <dd class="mt-1 text-sm">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium 
                                                {{ $fraudCase->transaction->status === 'completed' ? 'bg-green-100 text-green-800' : 
                                                   ($fraudCase->transaction->status === 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') }}">
                                                {{ ucfirst($fraudCase->transaction->status) }}
                                            </span>
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    @endif

                    <!-- Risk Indicators -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Risk Indicators</h3>
                            
                            @if($fraudCase->risk_indicators)
                                <ul class="space-y-3">
                                    @foreach(json_decode($fraudCase->risk_indicators, true) as $indicator)
                                        <li class="flex items-start">
                                            <svg class="h-5 w-5 text-red-500 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                            <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">{{ $indicator }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            @else
                                <p class="text-sm text-gray-500 dark:text-gray-400">No specific risk indicators recorded.</p>
                            @endif
                        </div>
                    </div>

                    <!-- Investigation Notes -->
                    @if($fraudCase->investigator_notes || auth()->user()->can('manage_fraud_cases'))
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Investigation Notes</h3>
                                
                                @if($fraudCase->investigator_notes)
                                    <div class="prose dark:prose-invert max-w-none">
                                        {{ $fraudCase->investigator_notes }}
                                    </div>
                                    @if($fraudCase->investigated_at)
                                        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">
                                            Last updated: {{ $fraudCase->investigated_at->format('M d, Y H:i') }}
                                        </p>
                                    @endif
                                @else
                                    <p class="text-sm text-gray-500 dark:text-gray-400">No investigation notes yet.</p>
                                @endif
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Sidebar -->
                <div class="lg:col-span-1 space-y-6">
                    <!-- Quick Actions -->
                    @can('manage_fraud_cases')
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Update Status</h3>
                                
                                <form action="{{ route('fraud.alerts.update-status', $fraudCase) }}" method="POST">
                                    @csrf
                                    @method('PATCH')
                                    
                                    <div class="space-y-4">
                                        <div>
                                            <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                                            <select id="status" name="status" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                                <option value="pending" {{ $fraudCase->status === 'pending' ? 'selected' : '' }}>Pending</option>
                                                <option value="investigating" {{ $fraudCase->status === 'investigating' ? 'selected' : '' }}>Investigating</option>
                                                <option value="confirmed" {{ $fraudCase->status === 'confirmed' ? 'selected' : '' }}>Confirmed Fraud</option>
                                                <option value="false_positive" {{ $fraudCase->status === 'false_positive' ? 'selected' : '' }}>False Positive</option>
                                                <option value="resolved" {{ $fraudCase->status === 'resolved' ? 'selected' : '' }}>Resolved</option>
                                            </select>
                                        </div>
                                        
                                        <div>
                                            <label for="notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Notes</label>
                                            <textarea id="notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm" placeholder="Add investigation notes...">{{ $fraudCase->investigator_notes }}</textarea>
                                        </div>
                                        
                                        <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition">
                                            Update Status
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    @endcan

                    <!-- Contact Information -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Need Help?</h3>
                            
                            <div class="space-y-4">
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">
                                        If you have questions about this fraud case or need immediate assistance:
                                    </p>
                                </div>
                                
                                <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                    <a href="tel:1-800-FRAUD-HELP" class="flex items-center text-indigo-600 hover:text-indigo-500">
                                        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                                        </svg>
                                        1-800-FRAUD-HELP
                                    </a>
                                </div>
                                
                                <div>
                                    <a href="{{ route('support.contact') }}" class="flex items-center text-indigo-600 hover:text-indigo-500">
                                        <svg class="h-5 w-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                                        </svg>
                                        Contact Support
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>