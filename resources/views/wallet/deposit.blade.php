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
                            <p class="text-gray-600 dark:text-gray-400 mb-4">Your account is being set up. Please refresh the page in a moment.</p>
                            <p class="text-sm text-gray-500 dark:text-gray-500 mb-4">If this message persists, please contact support.</p>
                            <button onclick="window.location.reload()" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition">
                                Refresh Page
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
</x-app-layout>