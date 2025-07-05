<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Remove Liquidity from {{ $pool['base_currency'] }}/{{ $pool['quote_currency'] }} Pool
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('liquidity.destroy', $pool['id']) }}">
                        @csrf
                        @method('DELETE')

                        <!-- Current Position -->
                        <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <h3 class="font-semibold mb-3">Your Position</h3>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="text-gray-600 dark:text-gray-400">Pool Share</p>
                                    <p class="font-medium">{{ number_format($userPosition['share_percentage'] * 100, 4) }}%</p>
                                </div>
                                <div>
                                    <p class="text-gray-600 dark:text-gray-400">LP Tokens</p>
                                    <p class="font-medium">{{ number_format($userPosition['liquidity_tokens'], 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-600 dark:text-gray-400">Current Value</p>
                                    <p class="font-medium">${{ number_format($userPosition['value_usd'], 2) }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-600 dark:text-gray-400">P&L</p>
                                    <p class="font-medium {{ $userPosition['pnl'] >= 0 ? 'text-green-600' : 'text-red-600' }}">
                                        {{ $userPosition['pnl'] >= 0 ? '+' : '' }}${{ number_format(abs($userPosition['pnl']), 2) }}
                                        ({{ $userPosition['pnl_percentage'] >= 0 ? '+' : '' }}{{ number_format($userPosition['pnl_percentage'], 2) }}%)
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Account Selection -->
                        <div class="mb-6">
                            <x-label for="account_id" value="{{ __('Receive Funds To') }}" />
                            <select id="account_id" name="account_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600" required>
                                <option value="">Select an account</option>
                                @foreach(Auth::user()->accounts as $account)
                                    <option value="{{ $account->uuid }}">{{ $account->name }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('account_id')" class="mt-2" />
                        </div>

                        <!-- Liquidity Percentage -->
                        <div class="mb-6">
                            <x-label for="liquidity_percentage" value="{{ __('Amount to Remove') }}" />
                            <div class="mt-1">
                                <input type="range" 
                                       name="liquidity_percentage" 
                                       id="liquidity_percentage" 
                                       min="1"
                                       max="100"
                                       value="100"
                                       class="w-full"
                                       required>
                                <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mt-2">
                                    <span>0%</span>
                                    <span class="font-medium text-lg" id="percentage_display">100%</span>
                                    <span>100%</span>
                                </div>
                            </div>
                            <div class="mt-2 flex space-x-2">
                                <button type="button" class="percentage-preset px-3 py-1 bg-gray-200 dark:bg-gray-700 rounded hover:bg-gray-300 dark:hover:bg-gray-600" data-value="25">25%</button>
                                <button type="button" class="percentage-preset px-3 py-1 bg-gray-200 dark:bg-gray-700 rounded hover:bg-gray-300 dark:hover:bg-gray-600" data-value="50">50%</button>
                                <button type="button" class="percentage-preset px-3 py-1 bg-gray-200 dark:bg-gray-700 rounded hover:bg-gray-300 dark:hover:bg-gray-600" data-value="75">75%</button>
                                <button type="button" class="percentage-preset px-3 py-1 bg-gray-200 dark:bg-gray-700 rounded hover:bg-gray-300 dark:hover:bg-gray-600" data-value="100">Max</button>
                            </div>
                            <x-input-error :messages="$errors->get('liquidity_percentage')" class="mt-2" />
                        </div>

                        <!-- Minimum Amounts -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <x-label for="min_base_amount" value="Minimum {{ $pool['base_currency'] }} to Receive" />
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <input type="number" 
                                           name="min_base_amount" 
                                           id="min_base_amount" 
                                           step="0.0001"
                                           min="0"
                                           class="block w-full pr-12 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                                           placeholder="0.0000"
                                           value="0"
                                           required>
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">{{ $pool['base_currency'] }}</span>
                                    </div>
                                </div>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Estimated: <span id="estimated_base">0</span> {{ $pool['base_currency'] }}
                                </p>
                                <x-input-error :messages="$errors->get('min_base_amount')" class="mt-2" />
                            </div>

                            <div>
                                <x-label for="min_quote_amount" value="Minimum {{ $pool['quote_currency'] }} to Receive" />
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <input type="number" 
                                           name="min_quote_amount" 
                                           id="min_quote_amount" 
                                           step="0.01"
                                           min="0"
                                           class="block w-full pr-12 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                                           placeholder="0.00"
                                           value="0"
                                           required>
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">{{ $pool['quote_currency'] }}</span>
                                    </div>
                                </div>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Estimated: <span id="estimated_quote">0</span> {{ $pool['quote_currency'] }}
                                </p>
                                <x-input-error :messages="$errors->get('min_quote_amount')" class="mt-2" />
                            </div>
                        </div>

                        <!-- Transaction Preview -->
                        <div class="mb-6 p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
                            <h3 class="font-semibold mb-3">You Will Receive</h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">{{ $pool['base_currency'] }}</span>
                                    <span id="receive_base">0</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">{{ $pool['quote_currency'] }}</span>
                                    <span id="receive_quote">0</span>
                                </div>
                                <div class="flex justify-between font-medium">
                                    <span class="text-gray-600 dark:text-gray-400">Total Value</span>
                                    <span id="receive_total">$0</span>
                                </div>
                            </div>
                        </div>

                        <div class="mb-6 p-4 bg-amber-50 dark:bg-amber-900/20 rounded-lg">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-amber-800 dark:text-amber-200">Warning</h3>
                                    <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">
                                        Removing liquidity will burn your LP tokens and return the underlying assets. 
                                        The actual amounts received may differ from estimates due to price movements.
                                    </p>
                                </div>
                            </div>
                        </div>

                        @if ($errors->has('error'))
                            <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                                <p class="text-sm text-red-600 dark:text-red-400">{{ $errors->first('error') }}</p>
                            </div>
                        @endif

                        <div class="flex items-center justify-end space-x-3">
                            <a href="{{ route('liquidity.show', $pool['id']) }}" 
                               class="px-4 py-2 bg-gray-300 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-600 transition">
                                Cancel
                            </a>
                            <button type="submit" 
                                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                                Remove Liquidity
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        const userPosition = @json($userPosition);
        const currentPrice = {{ $metrics['current_price'] }};
        
        // Update percentage display
        const percentageSlider = document.getElementById('liquidity_percentage');
        const percentageDisplay = document.getElementById('percentage_display');
        
        percentageSlider.addEventListener('input', function() {
            percentageDisplay.textContent = this.value + '%';
            updateEstimates();
        });

        // Percentage preset buttons
        document.querySelectorAll('.percentage-preset').forEach(button => {
            button.addEventListener('click', function() {
                percentageSlider.value = this.dataset.value;
                percentageDisplay.textContent = this.dataset.value + '%';
                updateEstimates();
            });
        });

        // Update estimated amounts
        function updateEstimates() {
            const percentage = parseFloat(percentageSlider.value) / 100;
            const baseAmount = (userPosition.base_amount || 0) * percentage;
            const quoteAmount = (userPosition.quote_amount || 0) * percentage;
            const totalValue = (userPosition.value_usd || 0) * percentage;
            
            document.getElementById('estimated_base').textContent = baseAmount.toFixed(4);
            document.getElementById('estimated_quote').textContent = quoteAmount.toFixed(2);
            document.getElementById('receive_base').textContent = baseAmount.toFixed(4) + ' ' + '{{ $pool['base_currency'] }}';
            document.getElementById('receive_quote').textContent = quoteAmount.toFixed(2) + ' ' + '{{ $pool['quote_currency'] }}';
            document.getElementById('receive_total').textContent = '$' + totalValue.toFixed(2);
            
            // Set minimum amounts (95% of estimated to account for slippage)
            document.getElementById('min_base_amount').value = (baseAmount * 0.95).toFixed(4);
            document.getElementById('min_quote_amount').value = (quoteAmount * 0.95).toFixed(2);
        }

        // Initialize estimates
        updateEstimates();
    </script>
    @endpush
</x-app-layout>