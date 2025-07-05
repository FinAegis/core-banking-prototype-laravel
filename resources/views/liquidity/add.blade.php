<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Add Liquidity to {{ $pool['base_currency'] }}/{{ $pool['quote_currency'] }} Pool
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('liquidity.store', $pool['id']) }}">
                        @csrf

                        <!-- Pool Information -->
                        <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <h3 class="font-semibold mb-3">Pool Information</h3>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <p class="text-gray-600 dark:text-gray-400">Current Price</p>
                                    <p class="font-medium">1 {{ $pool['base_currency'] }} = {{ number_format($metrics['current_price'], 4) }} {{ $pool['quote_currency'] }}</p>
                                </div>
                                <div>
                                    <p class="text-gray-600 dark:text-gray-400">Pool TVL</p>
                                    <p class="font-medium">${{ number_format($metrics['tvl'] / 1000000, 2) }}M</p>
                                </div>
                                <div>
                                    <p class="text-gray-600 dark:text-gray-400">Fee Tier</p>
                                    <p class="font-medium">{{ $pool['fee_rate'] * 100 }}%</p>
                                </div>
                                <div>
                                    <p class="text-gray-600 dark:text-gray-400">APY</p>
                                    <p class="font-medium text-green-600">{{ number_format($metrics['fee_apy'], 2) }}%</p>
                                </div>
                            </div>
                        </div>

                        <!-- Account Selection -->
                        <div class="mb-6">
                            <x-label for="account_id" value="{{ __('Select Account') }}" />
                            <select id="account_id" name="account_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600" required>
                                <option value="">Select an account</option>
                                @foreach($userBalances as $balance)
                                    <option value="{{ $balance['account_id'] }}" 
                                            data-base-balance="{{ $balance['base_balance'] }}"
                                            data-quote-balance="{{ $balance['quote_balance'] }}">
                                        {{ $balance['account_name'] }} 
                                        ({{ number_format($balance['base_balance'], 4) }} {{ $pool['base_currency'] }}, 
                                         {{ number_format($balance['quote_balance'], 2) }} {{ $pool['quote_currency'] }})
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('account_id')" class="mt-2" />
                        </div>

                        <!-- Amount Inputs -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                            <div>
                                <x-label for="base_amount" value="{{ $pool['base_currency'] }} Amount" />
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <input type="number" 
                                           name="base_amount" 
                                           id="base_amount" 
                                           step="0.0001"
                                           min="0.01"
                                           class="block w-full pr-12 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                                           placeholder="0.0000"
                                           required>
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">{{ $pool['base_currency'] }}</span>
                                    </div>
                                </div>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Balance: <span id="base_balance">0</span> {{ $pool['base_currency'] }}
                                </p>
                                <x-input-error :messages="$errors->get('base_amount')" class="mt-2" />
                            </div>

                            <div>
                                <x-label for="quote_amount" value="{{ $pool['quote_currency'] }} Amount" />
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <input type="number" 
                                           name="quote_amount" 
                                           id="quote_amount" 
                                           step="0.01"
                                           min="0.01"
                                           class="block w-full pr-12 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                                           placeholder="0.00"
                                           required>
                                    <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                        <span class="text-gray-500 sm:text-sm">{{ $pool['quote_currency'] }}</span>
                                    </div>
                                </div>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    Balance: <span id="quote_balance">0</span> {{ $pool['quote_currency'] }}
                                </p>
                                <x-input-error :messages="$errors->get('quote_amount')" class="mt-2" />
                            </div>
                        </div>

                        <!-- Slippage Tolerance -->
                        <div class="mb-6">
                            <x-label for="slippage_tolerance" value="{{ __('Slippage Tolerance (%)') }}" />
                            <div class="mt-1 flex space-x-2">
                                <button type="button" class="slippage-preset px-3 py-1 bg-gray-200 dark:bg-gray-700 rounded hover:bg-gray-300 dark:hover:bg-gray-600" data-value="0.5">0.5%</button>
                                <button type="button" class="slippage-preset px-3 py-1 bg-gray-200 dark:bg-gray-700 rounded hover:bg-gray-300 dark:hover:bg-gray-600" data-value="1">1%</button>
                                <button type="button" class="slippage-preset px-3 py-1 bg-gray-200 dark:bg-gray-700 rounded hover:bg-gray-300 dark:hover:bg-gray-600" data-value="3">3%</button>
                                <input type="number" 
                                       name="slippage_tolerance" 
                                       id="slippage_tolerance" 
                                       step="0.1"
                                       min="0.1"
                                       max="50"
                                       value="1"
                                       class="flex-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                                       required>
                            </div>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Your transaction will revert if the price changes unfavorably by more than this percentage.
                            </p>
                            <x-input-error :messages="$errors->get('slippage_tolerance')" class="mt-2" />
                        </div>

                        <!-- Transaction Preview -->
                        <div class="mb-6 p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                            <h3 class="font-semibold mb-3">Transaction Preview</h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Pool Share</span>
                                    <span id="pool_share">0.00%</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">LP Tokens to Receive</span>
                                    <span id="lp_tokens">0</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Estimated APY</span>
                                    <span class="text-green-600">{{ number_format($metrics['fee_apy'], 2) }}%</span>
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
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                                Add Liquidity
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        const currentPrice = {{ $metrics['current_price'] }};
        const totalTvl = {{ $metrics['tvl'] }};
        
        // Update balances when account is selected
        document.getElementById('account_id').addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            document.getElementById('base_balance').textContent = selected.dataset.baseBalance || '0';
            document.getElementById('quote_balance').textContent = selected.dataset.quoteBalance || '0';
        });

        // Auto-calculate quote amount based on base amount
        document.getElementById('base_amount').addEventListener('input', function() {
            const baseAmount = parseFloat(this.value) || 0;
            const quoteAmount = baseAmount * currentPrice;
            document.getElementById('quote_amount').value = quoteAmount.toFixed(2);
            updatePreview();
        });

        // Auto-calculate base amount based on quote amount
        document.getElementById('quote_amount').addEventListener('input', function() {
            const quoteAmount = parseFloat(this.value) || 0;
            const baseAmount = quoteAmount / currentPrice;
            document.getElementById('base_amount').value = baseAmount.toFixed(4);
            updatePreview();
        });

        // Slippage preset buttons
        document.querySelectorAll('.slippage-preset').forEach(button => {
            button.addEventListener('click', function() {
                document.getElementById('slippage_tolerance').value = this.dataset.value;
            });
        });

        // Update transaction preview
        function updatePreview() {
            const baseAmount = parseFloat(document.getElementById('base_amount').value) || 0;
            const quoteAmount = parseFloat(document.getElementById('quote_amount').value) || 0;
            
            if (baseAmount > 0 && quoteAmount > 0) {
                const totalValue = quoteAmount; // Simplified calculation
                const poolShare = (totalValue / (totalTvl + totalValue)) * 100;
                const lpTokens = Math.sqrt(baseAmount * quoteAmount) * 1000; // Simplified
                
                document.getElementById('pool_share').textContent = poolShare.toFixed(4) + '%';
                document.getElementById('lp_tokens').textContent = lpTokens.toFixed(2);
            }
        }
    </script>
    @endpush
</x-app-layout>