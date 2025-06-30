<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Paysera Deposit') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if (session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            @if (session('success'))
                <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 lg:p-8">
                    <!-- Back link -->
                    <div class="mb-6">
                        <a href="{{ route('wallet.deposit.bank') }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 font-medium">
                            ← Back to deposit methods
                        </a>
                    </div>

                    <div class="mb-8">
                        <div class="flex items-center space-x-4 mb-4">
                            <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center">
                                <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Deposit via Paysera</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Instant SEPA payments</p>
                            </div>
                        </div>
                    </div>

                    <form id="paysera-deposit-form" method="POST" action="{{ route('wallet.deposit.paysera.initiate') }}" class="space-y-6">
                        @csrf
                        
                        <!-- Amount Input -->
                        <div>
                            <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Deposit Amount
                            </label>
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <input type="number" 
                                       name="amount" 
                                       id="amount" 
                                       class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-3 pr-20 sm:text-sm border-gray-300 rounded-md dark:bg-gray-900 dark:border-gray-700 dark:text-white" 
                                       placeholder="0.00"
                                       step="0.01"
                                       min="10"
                                       max="10000"
                                       required>
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm" id="currency-label">EUR</span>
                                </div>
                            </div>
                            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Minimum deposit: €10</p>
                        </div>

                        <!-- Currency Selection -->
                        <div>
                            <label for="currency" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Currency
                            </label>
                            <select id="currency" 
                                    name="currency" 
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                                <option value="EUR" selected>Euro (EUR)</option>
                                <option value="USD">US Dollar (USD)</option>
                                <option value="GBP">British Pound (GBP)</option>
                            </select>
                        </div>

                        <!-- Bank Account Selection (if user has saved accounts) -->
                        @if(isset($savedAccounts) && count($savedAccounts) > 0)
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                Select Bank Account
                            </label>
                            <div class="space-y-2">
                                @foreach($savedAccounts as $account)
                                <label class="relative block cursor-pointer rounded-lg border bg-white dark:bg-gray-900 px-6 py-4 shadow-sm focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2 hover:border-gray-400">
                                    <input type="radio" 
                                           name="bank_account" 
                                           value="{{ $account['id'] }}" 
                                           class="sr-only"
                                           {{ $loop->first ? 'checked' : '' }}>
                                    <span class="flex items-center">
                                        <span class="text-sm flex flex-col">
                                            <span class="font-medium text-gray-900 dark:text-white">
                                                {{ $account['bank_name'] }} - {{ substr($account['iban'], -4) }}
                                            </span>
                                            <span class="text-gray-500 dark:text-gray-400">
                                                {{ $account['account_holder'] }}
                                            </span>
                                        </span>
                                    </span>
                                    <span class="absolute -inset-px rounded-lg border-2 pointer-events-none" aria-hidden="true"></span>
                                </label>
                                @endforeach
                            </div>
                        </div>
                        @endif

                        <!-- Payment Description -->
                        <div>
                            <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Description (Optional)
                            </label>
                            <input type="text" 
                                   name="description" 
                                   id="description" 
                                   class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md dark:bg-gray-900 dark:border-gray-700 dark:text-white" 
                                   placeholder="Deposit to FinAegis account">
                        </div>

                        <!-- Security Notice -->
                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                                        Secure Payment Process
                                    </h3>
                                    <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                                        <ul class="list-disc pl-5 space-y-1">
                                            <li>You will be redirected to Paysera's secure payment portal</li>
                                            <li>Login with your bank credentials</li>
                                            <li>Authorize the payment</li>
                                            <li>Funds will be credited instantly upon confirmation</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div>
                            <button type="submit" 
                                    id="submit-button"
                                    class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                <span id="button-text">Continue to Paysera</span>
                                <svg id="spinner" class="hidden animate-spin ml-2 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </button>
                        </div>
                    </form>

                    <!-- Alternative Methods -->
                    <div class="mt-8 text-center">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Want to use a different method? 
                            <a href="{{ route('wallet.deposit.bank') }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-500">
                                Choose another option
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // Update currency label when selection changes
        document.getElementById('currency').addEventListener('change', function() {
            const currencyLabel = document.getElementById('currency-label');
            const currencySymbols = {
                'EUR': 'EUR',
                'USD': 'USD',
                'GBP': 'GBP'
            };
            currencyLabel.textContent = currencySymbols[this.value] || this.value;
            
            // Update minimum amount based on currency
            const amountInput = document.getElementById('amount');
            const minAmounts = {
                'EUR': 10,
                'USD': 10,
                'GBP': 10
            };
            amountInput.min = minAmounts[this.value] || 10;
        });

        // Handle form submission
        document.getElementById('paysera-deposit-form').addEventListener('submit', function(e) {
            const submitButton = document.getElementById('submit-button');
            const buttonText = document.getElementById('button-text');
            const spinner = document.getElementById('spinner');
            
            submitButton.disabled = true;
            buttonText.textContent = 'Processing...';
            spinner.classList.remove('hidden');
        });
    </script>
    @endpush
</x-app-layout>