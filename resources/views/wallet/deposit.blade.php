<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Deposit Funds') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-6">
                    {{ __('Choose Deposit Method') }}
                </h3>

                <!-- Bank Transfer -->
                <div class="mb-6 p-6 border border-gray-200 dark:border-gray-700 rounded-lg">
                    <h4 class="text-md font-semibold text-gray-900 dark:text-gray-100 mb-3">
                        {{ __('Bank Transfer') }}
                    </h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        {{ __('Transfer funds directly from your bank account using Open Banking or Paysera.') }}
                    </p>
                    
                    <div class="flex space-x-4">
                        <a href="{{ route('wallet.deposit.bank') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition">
                            {{ __('Bank Deposit Options') }}
                        </a>
                    </div>
                    
                    <div class="mt-4 flex items-center space-x-4 text-xs text-gray-500 dark:text-gray-500">
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-1 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            Instant deposits
                        </div>
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-1 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            Secure PSD2 compliant
                        </div>
                        <div class="flex items-center">
                            <svg class="w-4 h-4 mr-1 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            Multiple banks supported
                        </div>
                    </div>
                </div>

                <!-- Card Deposit -->
                <div class="mb-6 p-6 border border-gray-200 dark:border-gray-700 rounded-lg">
                    <h4 class="text-md font-semibold text-gray-900 dark:text-gray-100 mb-3">
                        {{ __('Card Deposit') }}
                    </h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        {{ __('Instant deposit using debit or credit card. Processing time: Immediate.') }}
                    </p>
                    
                    @if(!auth()->user()->accounts->first())
                        <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-lg p-6 text-center">
                            <svg class="mx-auto h-12 w-12 text-yellow-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            <h4 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Account Setup Required</h4>
                            <p class="text-gray-600 dark:text-gray-400 mb-4">You need to create an account before you can deposit funds.</p>
                            <button onclick="window.createAccount()" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition">
                                Create Account Now
                            </button>
                        </div>
                    @else
                        <a href="{{ route('wallet.deposit.create') }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition">
                            {{ __('Deposit with Card') }}
                        </a>
                        
                        <p class="text-xs text-gray-500 dark:text-gray-500 mt-3">
                            {{ __('Secure payment processing powered by Stripe. Card processing fee: 2.9% + $0.30') }}
                        </p>
                    @endif
                </div>

                <!-- Crypto Deposit -->
                <div class="p-6 border border-gray-200 dark:border-gray-700 rounded-lg opacity-50">
                    <h4 class="text-md font-semibold text-gray-900 dark:text-gray-100 mb-3">
                        {{ __('Cryptocurrency Deposit') }}
                        <span class="ml-2 text-xs bg-gray-200 dark:bg-gray-700 text-gray-600 dark:text-gray-400 px-2 py-1 rounded">{{ __('Coming Soon') }}</span>
                    </h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        {{ __('Deposit Bitcoin, Ethereum, and other cryptocurrencies. Processing time: 10-30 minutes.') }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    <!-- Account Creation Modal -->
    <div id="accountModal" class="fixed z-10 inset-0 overflow-y-auto hidden" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <div class="flex items-end justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            
            <div class="inline-block align-bottom bg-white dark:bg-gray-800 rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form id="accountForm" onsubmit="window.submitAccountForm(event)">
                    <div class="bg-white dark:bg-gray-800 px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-indigo-100 dark:bg-indigo-900 sm:mx-0 sm:h-10 sm:w-10">
                                <svg class="h-6 w-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 dark:text-white" id="modal-title">
                                    Create Your Account
                                </h3>
                                <div class="mt-4">
                                    <label for="accountName" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Account Name
                                    </label>
                                    <input type="text" 
                                           name="accountName" 
                                           id="accountName" 
                                           class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md dark:bg-gray-900 dark:border-gray-700 dark:text-white" 
                                           placeholder="e.g., Personal Account"
                                           value="Personal Account"
                                           required>
                                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                        This will create a multi-currency account that supports USD, EUR, GBP, and GCU.
                                    </p>
                                </div>
                                <div id="accountError" class="mt-2 text-sm text-red-600 dark:text-red-400 hidden"></div>
                            </div>
                        </div>
                    </div>
                    <div class="bg-gray-50 dark:bg-gray-700 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                        <button type="submit" 
                                id="createAccountBtn"
                                class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-indigo-600 text-base font-medium text-white hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:ml-3 sm:w-auto sm:text-sm">
                            Create Account
                        </button>
                        <button type="button" 
                                onclick="window.closeAccountModal()"
                                class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 dark:border-gray-600 shadow-sm px-4 py-2 bg-white dark:bg-gray-800 text-base font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        window.createAccount = function() {
            document.getElementById('accountModal').classList.remove('hidden');
        }

        window.closeAccountModal = function() {
            document.getElementById('accountModal').classList.add('hidden');
            document.getElementById('accountError').classList.add('hidden');
        }

        window.submitAccountForm = async function(event) {
            event.preventDefault();
            
            const accountName = document.getElementById('accountName').value;
            const errorDiv = document.getElementById('accountError');
            const submitBtn = document.getElementById('createAccountBtn');
            
            // Disable button and show loading state
            submitBtn.disabled = true;
            submitBtn.textContent = 'Creating...';
            errorDiv.classList.add('hidden');

            try {
                const response = await fetch('/accounts/create', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        name: accountName
                    })
                });

                const data = await response.json();
                
                if (response.ok && data.success) {
                    // Account created successfully - reload the page
                    window.location.reload();
                } else {
                    // Show error
                    errorDiv.textContent = data.message || 'Failed to create account. Please try again.';
                    errorDiv.classList.remove('hidden');
                }
            } catch (error) {
                // Show error
                errorDiv.textContent = 'An error occurred. Please try again.';
                errorDiv.classList.remove('hidden');
                console.error('Account creation error:', error);
            } finally {
                // Re-enable button
                submitBtn.disabled = false;
                submitBtn.textContent = 'Create Account';
            }
        }
    </script>
</x-app-layout>