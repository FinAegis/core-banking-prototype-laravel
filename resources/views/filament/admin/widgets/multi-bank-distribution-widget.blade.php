<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            Multi-Bank Distribution Network
        </x-slot>

        <x-slot name="description">
            @if($isGcuEnabled)
                GCU funds are distributed across multiple regulated banks for maximum security and deposit insurance coverage.
            @else
                Platform capability: Distribute user funds across multiple regulated banks.
            @endif
        </x-slot>

        <div class="space-y-6">
            {{-- Bank Distribution Grid --}}
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @forelse($bankStats as $bank)
                    <div class="relative overflow-hidden rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 p-6">
                        {{-- Bank Header --}}
                        <div class="flex items-start justify-between">
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                    {{ $bank['bank_name'] }}
                                </h3>
                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                    {{ $bank['country'] }} • {{ $bank['coverage'] }} insurance
                                </p>
                            </div>
                            @if($bank['primary_count'] > 0)
                                <span class="inline-flex items-center rounded-md bg-blue-50 px-2 py-1 text-xs font-medium text-blue-700 ring-1 ring-inset ring-blue-700/10 dark:bg-blue-400/10 dark:text-blue-400 dark:ring-blue-400/30">
                                    Primary
                                </span>
                            @endif
                        </div>

                        {{-- Bank Statistics --}}
                        <div class="mt-4 space-y-3">
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Active Users</p>
                                <p class="text-2xl font-semibold text-gray-900 dark:text-white">
                                    {{ number_format($bank['user_count']) }}
                                </p>
                            </div>
                            
                            <div>
                                <p class="text-sm text-gray-500 dark:text-gray-400">Average Allocation</p>
                                <div class="mt-1 flex items-center">
                                    <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                        <div 
                                            class="bg-{{ $bank['color'] }}-600 h-2 rounded-full"
                                            style="width: {{ $bank['avg_allocation'] }}%"
                                        ></div>
                                    </div>
                                    <span class="ml-3 text-sm font-medium text-gray-900 dark:text-white">
                                        {{ number_format($bank['avg_allocation'], 1) }}%
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Decorative Element --}}
                        <div class="absolute -right-4 -bottom-4 opacity-5">
                            <svg class="w-32 h-32" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M2.273 5.625A4.483 4.483 0 015.25 4.5h13.5c1.141 0 2.183.425 2.977 1.125A3 3 0 0018.75 3H5.25a3 3 0 00-2.977 2.625zM2.273 8.625A4.483 4.483 0 015.25 7.5h13.5c1.141 0 2.183.425 2.977 1.125A3 3 0 0018.75 6H5.25a3 3 0 00-2.977 2.625zM5.25 10.5a3 3 0 00-3 3v6a3 3 0 003 3h13.5a3 3 0 003-3v-6a3 3 0 00-3-3H5.25zm3.75 7.5a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0zm7.5 0a1.5 1.5 0 11-3 0 1.5 1.5 0 013 0z"/>
                            </svg>
                        </div>
                    </div>
                @empty
                    <div class="col-span-full text-center py-12">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No bank distributions yet</h3>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                            Bank distributions will appear here when users configure their preferences.
                        </p>
                    </div>
                @endforelse
            </div>

            {{-- Summary Statistics --}}
            @if($totalAllocated['users'] > 0)
                <div class="border-t border-gray-200 dark:border-gray-700 pt-6">
                    <div class="grid grid-cols-3 gap-4 text-center">
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Total Users</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                                {{ number_format($totalAllocated['users']) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Bank Relationships</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                                {{ number_format($totalAllocated['relationships']) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Avg Banks per User</p>
                            <p class="mt-1 text-2xl font-semibold text-gray-900 dark:text-white">
                                {{ $totalAllocated['averagePerUser'] }}
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            {{-- Insurance Coverage Note --}}
            <div class="rounded-md bg-blue-50 dark:bg-blue-900/20 p-4">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-blue-800 dark:text-blue-400">
                            Deposit Insurance Protection
                        </h3>
                        <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                            <p>
                                Each bank provides government-backed deposit insurance up to €100,000 per depositor. 
                                By distributing funds across multiple banks, users can achieve up to €500,000 in total coverage.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>