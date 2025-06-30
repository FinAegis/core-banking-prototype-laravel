<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Bank Deposit') }}
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
                            {{ $account ? '$' . number_format($account->getBalance('USD') / 100, 2) . ' USD' : 'No account' }}
                        </p>
                    </div>

                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Select Bank Deposit Method</h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                            Choose your preferred bank to deposit funds into your FinAegis account.
                        </p>

                        <div class="space-y-4">
                            @if(config('custodians.paysera.enabled'))
                            <!-- Paysera Option -->
                            <a href="{{ route('wallet.deposit.paysera') }}" class="block">
                                <div class="border-2 border-gray-200 dark:border-gray-700 rounded-lg p-6 hover:border-indigo-500 dark:hover:border-indigo-400 transition-colors cursor-pointer">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-4">
                                            <div class="w-16 h-16 bg-green-100 dark:bg-green-900 rounded-lg flex items-center justify-center">
                                                <svg class="w-10 h-10 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <h4 class="text-lg font-semibold text-gray-900 dark:text-white">Paysera</h4>
                                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                                    SEPA instant payments • Multi-currency support
                                                </p>
                                                <div class="mt-2 flex items-center space-x-2">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200">
                                                        Instant
                                                    </span>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200">
                                                        EUR, USD, GBP
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </div>
                                </div>
                            </a>
                            @endif

                            <!-- Open Banking Option -->
                            <a href="{{ route('wallet.deposit.openbanking') }}" class="block">
                                <div class="border-2 border-gray-200 dark:border-gray-700 rounded-lg p-6 hover:border-indigo-500 dark:hover:border-indigo-400 transition-colors cursor-pointer">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-4">
                                            <div class="w-16 h-16 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                                                <svg class="w-10 h-10 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <h4 class="text-lg font-semibold text-gray-900 dark:text-white">Open Banking</h4>
                                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                                    Connect directly to your bank • Secure PSD2 compliant
                                                </p>
                                                <div class="mt-2 flex items-center space-x-2">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-100 dark:bg-indigo-900 text-indigo-800 dark:text-indigo-200">
                                                        Secure
                                                    </span>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300">
                                                        Multiple Banks
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </div>
                                </div>
                            </a>

                            <!-- Manual Bank Transfer Option -->
                            <a href="{{ route('wallet.deposit.manual') }}" class="block">
                                <div class="border-2 border-gray-200 dark:border-gray-700 rounded-lg p-6 hover:border-indigo-500 dark:hover:border-indigo-400 transition-colors cursor-pointer">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-4">
                                            <div class="w-16 h-16 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                                                <svg class="w-10 h-10 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <h4 class="text-lg font-semibold text-gray-900 dark:text-white">Bank Transfer</h4>
                                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                                    Traditional wire transfer • 1-3 business days
                                                </p>
                                                <div class="mt-2 flex items-center space-x-2">
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 dark:bg-yellow-900 text-yellow-800 dark:text-yellow-200">
                                                        1-3 days
                                                    </span>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-300">
                                                        All currencies
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- Alternative Payment Methods -->
                    <div class="mt-8 pt-8 border-t border-gray-200 dark:border-gray-700">
                        <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-4">Other Payment Methods</h4>
                        <div class="grid grid-cols-2 gap-4">
                            <a href="{{ route('wallet.deposit.create') }}" class="text-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                <svg class="w-8 h-8 text-gray-600 dark:text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                </svg>
                                <span class="text-sm text-gray-600 dark:text-gray-400">Credit/Debit Card</span>
                            </a>
                            <a href="{{ route('wallet.deposit.crypto') }}" class="text-center p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                <svg class="w-8 h-8 text-gray-600 dark:text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                <span class="text-sm text-gray-600 dark:text-gray-400">Cryptocurrency</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>