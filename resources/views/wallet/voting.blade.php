<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('GCU Governance Voting') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @php
                // Get active polls (in production, this would be fetched from the database)
                $activePolls = \App\Models\Poll::where('status', 'active')
                    ->where('start_date', '<=', now())
                    ->where('end_date', '>=', now())
                    ->get();
                
                $upcomingPolls = \App\Models\Poll::where('status', 'draft')
                    ->orWhere('start_date', '>', now())
                    ->orderBy('start_date')
                    ->limit(3)
                    ->get();
            @endphp

            <!-- Voting Stats -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Your Voting Power') }}</div>
                    <div class="mt-1 text-3xl font-semibold text-gray-900 dark:text-gray-100">
                        {{ number_format(auth()->user()->accounts->sum(fn($a) => $a->getBalance('GCU')) / 100, 2) }} GCU
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Active Polls') }}</div>
                    <div class="mt-1 text-3xl font-semibold text-gray-900 dark:text-gray-100">{{ $activePolls->count() }}</div>
                </div>
                
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                    <div class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ __('Next Rebalancing') }}</div>
                    <div class="mt-1 text-lg font-semibold text-gray-900 dark:text-gray-100">
                        {{ now()->startOfMonth()->addMonth()->format('F j, Y') }}
                    </div>
                </div>
            </div>

            <!-- Active Polls -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg mb-8">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">{{ __('Active Polls') }}</h3>
                    
                    @if($activePolls->count() > 0)
                        <div class="space-y-4">
                            @foreach($activePolls as $poll)
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                    <div class="flex justify-between items-start mb-3">
                                        <div>
                                            <h4 class="text-md font-semibold text-gray-900 dark:text-gray-100">{{ $poll->title }}</h4>
                                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $poll->description }}</p>
                                        </div>
                                        <span class="px-2 py-1 text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded">
                                            {{ __('Ends') }} {{ $poll->end_date->diffForHumans() }}
                                        </span>
                                    </div>
                                    
                                    @if($poll->metadata && isset($poll->metadata['type']) && $poll->metadata['type'] === 'basket_composition')
                                        <div class="mt-4">
                                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">{{ __('Current Basket Composition:') }}</p>
                                            <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                                                <div class="text-sm"><span class="font-medium">USD:</span> 35%</div>
                                                <div class="text-sm"><span class="font-medium">EUR:</span> 30%</div>
                                                <div class="text-sm"><span class="font-medium">GBP:</span> 20%</div>
                                                <div class="text-sm"><span class="font-medium">CHF:</span> 10%</div>
                                                <div class="text-sm"><span class="font-medium">JPY:</span> 3%</div>
                                                <div class="text-sm"><span class="font-medium">XAU:</span> 2%</div>
                                            </div>
                                        </div>
                                    @endif
                                    
                                    <div class="mt-4">
                                        <a href="#" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition">
                                            {{ __('Vote Now') }}
                                        </a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-500 dark:text-gray-400">{{ __('No active polls at the moment.') }}</p>
                    @endif
                </div>
            </div>

            <!-- Upcoming Polls -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">{{ __('Upcoming Polls') }}</h3>
                    
                    @if($upcomingPolls->count() > 0)
                        <div class="space-y-3">
                            @foreach($upcomingPolls as $poll)
                                <div class="flex justify-between items-center p-3 border border-gray-200 dark:border-gray-700 rounded">
                                    <div>
                                        <h4 class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $poll->title }}</h4>
                                        <p class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ __('Starts') }} {{ $poll->start_date->format('F j, Y') }}
                                        </p>
                                    </div>
                                    <span class="text-sm text-gray-500 dark:text-gray-400">
                                        {{ $poll->start_date->diffForHumans() }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-gray-500 dark:text-gray-400">{{ __('No upcoming polls scheduled.') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>