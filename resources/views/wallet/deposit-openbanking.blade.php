<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Open Banking Deposit') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            @if (session('error'))
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
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
                            <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                                <svg class="w-8 h-8 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">Open Banking Deposit</h3>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Connect directly to your bank</p>
                            </div>
                        </div>
                    </div>

                    @if(config('demo.mode'))
                        <div class="mb-6 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                        Demo Mode Active
                                    </h3>
                                    <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                                        <p>In demo mode, Open Banking connections are simulated. You won't be redirected to a real bank.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Bank Selection -->
                    <div class="mb-8">
                        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-4">Select Your Bank</h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                            @php
                                $banks = [
                                    ['id' => 'santander', 'name' => 'Santander', 'logo' => '🏦', 'available' => true],
                                    ['id' => 'hsbc', 'name' => 'HSBC', 'logo' => '🏦', 'available' => false],
                                    ['id' => 'barclays', 'name' => 'Barclays', 'logo' => '🏦', 'available' => false],
                                    ['id' => 'lloyds', 'name' => 'Lloyds', 'logo' => '🏦', 'available' => false],
                                    ['id' => 'natwest', 'name' => 'NatWest', 'logo' => '🏦', 'available' => false],
                                    ['id' => 'revolut', 'name' => 'Revolut', 'logo' => '🏦', 'available' => false],
                                ];
                            @endphp

                            @foreach($banks as $bank)
                            <button type="button" 
                                    class="bank-option relative p-4 border-2 rounded-lg transition-all {{ $bank['available'] ? 'border-gray-200 dark:border-gray-700 hover:border-indigo-500 dark:hover:border-indigo-400 cursor-pointer' : 'border-gray-100 dark:border-gray-800 opacity-50 cursor-not-allowed' }}"
                                    data-bank="{{ $bank['id'] }}"
                                    {{ !$bank['available'] ? 'disabled' : '' }}>
                                <div class="text-center">
                                    <div class="text-3xl mb-2">{{ $bank['logo'] }}</div>
                                    <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $bank['name'] }}</div>
                                    @if(!$bank['available'])
                                    <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">Coming soon</div>
                                    @endif
                                </div>
                                @if($bank['available'])
                                <div class="absolute top-2 right-2 hidden selected-mark">
                                    <svg class="w-5 h-5 text-indigo-600" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                @endif
                            </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- Deposit Form -->
                    <form id="openbanking-deposit-form" method="POST" action="{{ route('wallet.deposit.openbanking.initiate') }}" class="space-y-6 hidden">
                        @csrf
                        <input type="hidden" name="bank" id="selected-bank" value="">
                        
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
                                       min="1"
                                       max="10000"
                                       required>
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm" id="currency-label">GBP</span>
                                </div>
                            </div>
                        </div>

                        <!-- Currency Selection -->
                        <div>
                            <label for="currency" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Currency
                            </label>
                            <select id="currency" 
                                    name="currency" 
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-900 dark:border-gray-700 dark:text-white">
                                <option value="GBP" selected>British Pound (GBP)</option>
                                <option value="EUR">Euro (EUR)</option>
                                <option value="USD">US Dollar (USD)</option>
                            </select>
                        </div>

                        <!-- Consent Notice -->
                        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                        Open Banking Consent
                                    </h3>
                                    <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                                        <p>By continuing, you agree to:</p>
                                        <ul class="list-disc pl-5 mt-1 space-y-1">
                                            <li>Share your account information securely</li>
                                            <li>Authorize a one-time payment from your bank</li>
                                            <li>Allow FinAegis to confirm the payment status</li>
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
                                <span id="button-text">Connect to Bank</span>
                                <svg id="spinner" class="hidden animate-spin ml-2 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </button>
                        </div>
                    </form>

                    <!-- Security Information -->
                    <div class="mt-8 bg-gray-50 dark:bg-gray-900 rounded-lg p-6">
                        <h4 class="text-sm font-semibold text-gray-900 dark:text-white mb-3">How Open Banking Works</h4>
                        <div class="space-y-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <div class="flex items-center justify-center h-8 w-8 rounded-full bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-400 text-sm font-medium">
                                        1
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm text-gray-700 dark:text-gray-300">
                                        Select your bank from the list above
                                    </p>
                                </div>
                            </div>
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <div class="flex items-center justify-center h-8 w-8 rounded-full bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-400 text-sm font-medium">
                                        2
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm text-gray-700 dark:text-gray-300">
                                        You'll be redirected to your bank's secure login
                                    </p>
                                </div>
                            </div>
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <div class="flex items-center justify-center h-8 w-8 rounded-full bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-400 text-sm font-medium">
                                        3
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm text-gray-700 dark:text-gray-300">
                                        Authorize the payment in your bank app
                                    </p>
                                </div>
                            </div>
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <div class="flex items-center justify-center h-8 w-8 rounded-full bg-indigo-100 dark:bg-indigo-900 text-indigo-600 dark:text-indigo-400 text-sm font-medium">
                                        4
                                    </div>
                                </div>
                                <div class="ml-4">
                                    <p class="text-sm text-gray-700 dark:text-gray-300">
                                        Funds are instantly credited to your account
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // Handle bank selection
        const bankOptions = document.querySelectorAll('.bank-option:not([disabled])');
        const depositForm = document.getElementById('openbanking-deposit-form');
        const selectedBankInput = document.getElementById('selected-bank');
        
        bankOptions.forEach(option => {
            option.addEventListener('click', function() {
                // Remove previous selection
                document.querySelectorAll('.bank-option').forEach(opt => {
                    opt.classList.remove('border-indigo-500', 'dark:border-indigo-400');
                    opt.querySelector('.selected-mark')?.classList.add('hidden');
                });
                
                // Add selection to clicked bank
                this.classList.add('border-indigo-500', 'dark:border-indigo-400');
                this.querySelector('.selected-mark')?.classList.remove('hidden');
                
                // Update form
                selectedBankInput.value = this.dataset.bank;
                depositForm.classList.remove('hidden');
                
                // Scroll to form
                depositForm.scrollIntoView({ behavior: 'smooth', block: 'center' });
            });
        });

        // Update currency label
        document.getElementById('currency').addEventListener('change', function() {
            document.getElementById('currency-label').textContent = this.value;
        });

        // Handle form submission
        document.getElementById('openbanking-deposit-form').addEventListener('submit', function(e) {
            const submitButton = document.getElementById('submit-button');
            const buttonText = document.getElementById('button-text');
            const spinner = document.getElementById('spinner');
            
            submitButton.disabled = true;
            buttonText.textContent = 'Connecting...';
            spinner.classList.remove('hidden');
        });
    </script>
    @endpush
</x-app-layout>