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
                $activePolls = \App\Domain\Governance\Models\Poll::where('status', 'active')
                    ->where('start_date', '<=', now())
                    ->where('end_date', '>=', now())
                    ->get();
                
                $upcomingPolls = \App\Domain\Governance\Models\Poll::where('status', 'draft')
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
                        @if(auth()->user()->accounts->count() > 0)
                            {{ number_format(auth()->user()->accounts->sum(fn($a) => $a->getBalance('GCU')) / 100, 2) }} GCU
                        @else
                            0.00 GCU
                        @endif
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
                                        <button onclick="openVotingModal('{{ $poll->uuid }}')" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition">
                                            {{ __('Vote Now') }}
                                        </button>
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

    <!-- Voting Modal -->
    <div id="voting-modal" class="fixed inset-0 z-50 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-2xl sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4" id="modal-title">
                        Vote on GCU Basket Composition
                    </h3>
                    
                    <div id="voting-form-content">
                        <p class="text-sm text-gray-500 mb-4">
                            Allocate percentages to each currency. Total must equal 100%.
                        </p>
                        
                        <form id="voting-form" class="space-y-4">
                            <input type="hidden" id="poll-uuid" value="">
                            
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">USD</label>
                                    <input type="number" name="USD" value="35" min="0" max="100" step="0.1" 
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">EUR</label>
                                    <input type="number" name="EUR" value="30" min="0" max="100" step="0.1"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">GBP</label>
                                    <input type="number" name="GBP" value="20" min="0" max="100" step="0.1"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">CHF</label>
                                    <input type="number" name="CHF" value="10" min="0" max="100" step="0.1"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">JPY</label>
                                    <input type="number" name="JPY" value="3" min="0" max="100" step="0.1"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">XAU (Gold)</label>
                                    <input type="number" name="XAU" value="2" min="0" max="100" step="0.1"
                                           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm">
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <div class="flex justify-between items-center">
                                    <span class="text-sm font-medium text-gray-700">Total:</span>
                                    <span id="total-percentage" class="text-lg font-bold">100%</span>
                                </div>
                                <div id="total-error" class="text-red-600 text-sm mt-1 hidden">
                                    Total must equal 100%
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" onclick="submitVote()" 
                            class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                        Submit Vote
                    </button>
                    <button type="button" onclick="closeVotingModal()"
                            class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openVotingModal(pollUuid) {
            document.getElementById('poll-uuid').value = pollUuid;
            document.getElementById('voting-modal').classList.remove('hidden');
            updateTotal();
        }

        function closeVotingModal() {
            document.getElementById('voting-modal').classList.add('hidden');
        }

        // Update total percentage when inputs change
        document.querySelectorAll('#voting-form input[type="number"]').forEach(input => {
            input.addEventListener('input', updateTotal);
        });

        function updateTotal() {
            const inputs = document.querySelectorAll('#voting-form input[type="number"]');
            let total = 0;
            inputs.forEach(input => {
                total += parseFloat(input.value) || 0;
            });
            
            document.getElementById('total-percentage').textContent = total.toFixed(1) + '%';
            
            if (Math.abs(total - 100) > 0.01) {
                document.getElementById('total-error').classList.remove('hidden');
                document.getElementById('total-percentage').classList.add('text-red-600');
            } else {
                document.getElementById('total-error').classList.add('hidden');
                document.getElementById('total-percentage').classList.remove('text-red-600');
            }
        }

        async function submitVote() {
            const pollUuid = document.getElementById('poll-uuid').value;
            const formData = new FormData(document.getElementById('voting-form'));
            
            const allocations = {};
            let total = 0;
            
            for (let [key, value] of formData.entries()) {
                if (key !== 'poll-uuid') {
                    const numValue = parseFloat(value) || 0;
                    allocations[key] = numValue;
                    total += numValue;
                }
            }
            
            if (Math.abs(total - 100) > 0.01) {
                alert('Total allocations must equal 100%');
                return;
            }
            
            try {
                const response = await fetch(`/api/voting/polls/${pollUuid}/vote`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({ allocations })
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    alert('Your vote has been submitted successfully!');
                    closeVotingModal();
                    window.location.reload();
                } else {
                    alert(data.error || 'Failed to submit vote');
                }
            } catch (error) {
                console.error('Error submitting vote:', error);
                alert('An error occurred while submitting your vote');
            }
        }
    </script>
</x-app-layout>