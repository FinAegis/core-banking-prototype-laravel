<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Card Deposit') }}
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
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Account Balance</h3>
                        <p class="mt-1 text-3xl font-semibold text-gray-900 dark:text-white">
                            ${{ number_format($account->getBalance('USD') / 100, 2) }} USD
                        </p>
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
                                        <p>You're in demo mode. No real money will be transferred. Use the quick deposit button below to instantly add funds to your account.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endif

                    <form id="deposit-form" class="space-y-6">
                        @csrf
                        
                        <!-- Amount Input -->
                        <div>
                            <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Deposit Amount
                            </label>
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm">$</span>
                                </div>
                                <input type="number" 
                                       name="amount" 
                                       id="amount" 
                                       class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pl-7 pr-12 sm:text-sm border-gray-300 rounded-md dark:bg-gray-900 dark:border-gray-700 dark:text-white" 
                                       placeholder="0.00"
                                       step="0.01"
                                       min="1"
                                       max="10000"
                                       required>
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm" id="currency-label">USD</span>
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
                                <option value="USD">US Dollar (USD)</option>
                                <option value="EUR">Euro (EUR)</option>
                                <option value="GBP">British Pound (GBP)</option>
                            </select>
                        </div>

                        <!-- Payment Method Selection -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                Payment Method
                            </label>
                            
                            @if(isset($paymentMethods) && count($paymentMethods) > 0)
                                <div class="space-y-2 mb-4">
                                    @foreach($paymentMethods as $method)
                                        <label class="relative block cursor-pointer rounded-lg border bg-white dark:bg-gray-900 px-6 py-4 shadow-sm focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2 hover:border-gray-400">
                                            <input type="radio" 
                                                   name="payment_method" 
                                                   value="{{ $method['id'] }}" 
                                                   class="sr-only"
                                                   {{ $loop->first ? 'checked' : '' }}>
                                            <span class="flex items-center">
                                                <span class="text-sm flex flex-col">
                                                    <span class="font-medium text-gray-900 dark:text-white">
                                                        {{ ucfirst($method['brand']) }} ending in {{ $method['last4'] }}
                                                    </span>
                                                    <span class="text-gray-500 dark:text-gray-400">
                                                        Expires {{ $method['exp_month'] }}/{{ $method['exp_year'] }}
                                                    </span>
                                                </span>
                                            </span>
                                            <span class="absolute -inset-px rounded-lg border-2 pointer-events-none" aria-hidden="true"></span>
                                        </label>
                                    @endforeach
                                </div>
                                <div class="relative">
                                    <div class="absolute inset-0 flex items-center" aria-hidden="true">
                                        <div class="w-full border-t border-gray-300 dark:border-gray-700"></div>
                                    </div>
                                    <div class="relative flex justify-center text-sm">
                                        <span class="px-2 bg-white dark:bg-gray-800 text-gray-500">Or</span>
                                    </div>
                                </div>
                            @endif
                            
                            <div class="mt-4">
                                <label class="relative block cursor-pointer rounded-lg border bg-white dark:bg-gray-900 px-6 py-4 shadow-sm focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2 hover:border-gray-400">
                                    <input type="radio" 
                                           name="payment_method" 
                                           value="new" 
                                           class="sr-only"
                                           {{ !isset($paymentMethods) || count($paymentMethods) === 0 ? 'checked' : '' }}>
                                    <span class="flex items-center">
                                        <span class="text-sm flex flex-col">
                                            <span class="font-medium text-gray-900 dark:text-white">
                                                Add new payment method
                                            </span>
                                        </span>
                                    </span>
                                    <span class="absolute -inset-px rounded-lg border-2 pointer-events-none" aria-hidden="true"></span>
                                </label>
                            </div>
                        </div>

                        <!-- Card Element (for new payment method) -->
                        <div id="card-element-container" class="hidden">
                            <label for="card-element" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Card Details
                            </label>
                            <div id="card-element" class="p-3 border border-gray-300 rounded-md dark:border-gray-700"></div>
                            <div id="card-errors" class="text-red-600 text-sm mt-2" role="alert"></div>
                        </div>

                        <!-- Submit Button -->
                        <div>
                            <button type="submit" 
                                    id="submit-button"
                                    class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed">
                                <span id="button-text">Deposit Funds</span>
                                <svg id="spinner" class="hidden animate-spin ml-2 h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </button>
                        </div>
                    </form>

                    @if(config('demo.mode'))
                        <!-- Demo Mode Quick Actions -->
                        <div class="mt-6 border-t border-gray-200 dark:border-gray-700 pt-6">
                            <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-4">Demo Quick Actions</h4>
                            <form action="{{ route('wallet.deposit.simulate') }}" method="POST" class="space-y-4">
                                @csrf
                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <input type="hidden" name="currency" value="USD">
                                    <button type="submit" name="amount" value="100" class="inline-flex justify-center items-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        <svg class="mr-2 h-5 w-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Instant $100
                                    </button>
                                    <button type="submit" name="amount" value="500" class="inline-flex justify-center items-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        <svg class="mr-2 h-5 w-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Instant $500
                                    </button>
                                    <button type="submit" name="amount" value="1000" class="inline-flex justify-center items-center px-4 py-2 border border-gray-300 dark:border-gray-600 shadow-sm text-sm font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-800 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                        <svg class="mr-2 h-5 w-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Instant $1000
                                    </button>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    These buttons instantly add demo funds to your account without going through payment processing.
                                </p>
                            </form>
                        </div>
                    @endif

                    <!-- Security Notice -->
                    <div class="mt-8 bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                                    Secure Payment Processing
                                </h3>
                                <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                                    <p>Your payment information is processed securely by Stripe. We never store your card details on our servers.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script src="https://js.stripe.com/v3/"></script>
    <script>
        // Initialize Stripe
        const stripe = Stripe('{{ $stripeKey ?? config('cashier.key') }}');
        const elements = stripe.elements();
        
        // Create card element
        const cardElement = elements.create('card', {
            style: {
                base: {
                    color: '#32325d',
                    fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
                    fontSmoothing: 'antialiased',
                    fontSize: '16px',
                    '::placeholder': {
                        color: '#aab7c4'
                    }
                },
                invalid: {
                    color: '#fa755a',
                    iconColor: '#fa755a'
                }
            }
        });
        
        // Handle payment method selection
        const paymentMethodRadios = document.querySelectorAll('input[name="payment_method"]');
        const cardElementContainer = document.getElementById('card-element-container');
        
        paymentMethodRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'new') {
                    cardElementContainer.classList.remove('hidden');
                    cardElement.mount('#card-element');
                } else {
                    cardElementContainer.classList.add('hidden');
                    cardElement.unmount();
                }
            });
        });
        
        // Mount card element if new payment method is selected by default
        if (document.querySelector('input[name="payment_method"]:checked')?.value === 'new') {
            cardElementContainer.classList.remove('hidden');
            cardElement.mount('#card-element');
        }
        
        // Handle real-time validation errors from the card Element
        cardElement.addEventListener('change', function(event) {
            const displayError = document.getElementById('card-errors');
            if (event.error) {
                displayError.textContent = event.error.message;
            } else {
                displayError.textContent = '';
            }
        });
        
        // Handle form submission
        const form = document.getElementById('deposit-form');
        const submitButton = document.getElementById('submit-button');
        const buttonText = document.getElementById('button-text');
        const spinner = document.getElementById('spinner');
        
        form.addEventListener('submit', async function(event) {
            event.preventDefault();
            
            // Disable button
            submitButton.disabled = true;
            buttonText.textContent = 'Processing...';
            spinner.classList.remove('hidden');
            
            const amount = document.getElementById('amount').value;
            const currency = document.getElementById('currency').value;
            const selectedPaymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
            
            try {
                // Create payment intent
                const response = await fetch('{{ route("wallet.deposit.store") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        amount: amount,
                        currency: currency
                    })
                });
                
                const data = await response.json();
                
                if (data.error) {
                    throw new Error(data.error);
                }
                
                // Confirm payment
                let result;
                if (selectedPaymentMethod === 'new') {
                    // Create new payment method
                    result = await stripe.confirmCardPayment(data.client_secret, {
                        payment_method: {
                            card: cardElement,
                        },
                        setup_future_usage: 'on_session'
                    });
                } else {
                    // Use existing payment method
                    result = await stripe.confirmCardPayment(data.client_secret, {
                        payment_method: selectedPaymentMethod
                    });
                }
                
                if (result.error) {
                    throw new Error(result.error.message);
                } else {
                    // Payment succeeded, redirect to confirm
                    window.location.href = '{{ route("wallet.deposit.confirm") }}?payment_intent_id=' + result.paymentIntent.id;
                }
            } catch (error) {
                // Show error to customer
                const errorElement = document.getElementById('card-errors');
                errorElement.textContent = error.message;
                
                // Re-enable button
                submitButton.disabled = false;
                buttonText.textContent = 'Deposit Funds';
                spinner.classList.add('hidden');
            }
        });
        
        // Update currency label
        document.getElementById('currency').addEventListener('change', function() {
            const currencyLabel = document.getElementById('currency-label');
            currencyLabel.textContent = this.value;
        });
    </script>
    @endpush
</x-app-layout>