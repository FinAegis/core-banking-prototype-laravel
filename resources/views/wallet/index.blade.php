<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Wallet Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Account Status Alert -->
            @if(!auth()->user()->accounts->first())
                <div class="mb-6 bg-yellow-50 border border-yellow-400 text-yellow-800 px-6 py-4 rounded-lg">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm font-medium">Account Setup Required</p>
                            <p class="text-sm mt-1">You need to create an account before you can deposit funds. <a href="#" class="font-semibold underline">Create Account</a></p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Main Wallet Interface -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 lg:p-8">
                    <!-- Balance Overview -->
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Account Balance</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Total Balance -->
                            <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 rounded-xl p-6 text-white">
                                <p class="text-sm font-medium opacity-90">Total Balance</p>
                                <p class="text-3xl font-bold mt-2">
                                    ${{ number_format(auth()->user()->accounts->first() ? auth()->user()->accounts->first()->getBalance('USD') / 100 : 0, 2) }}
                                </p>
                                <p class="text-xs mt-2 opacity-75">Across all currencies</p>
                            </div>

                            <!-- Available Balance -->
                            <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl p-6 text-white">
                                <p class="text-sm font-medium opacity-90">Available Balance</p>
                                <p class="text-3xl font-bold mt-2">
                                    ${{ number_format(auth()->user()->accounts->first() ? auth()->user()->accounts->first()->getBalance('USD') / 100 : 0, 2) }}
                                </p>
                                <p class="text-xs mt-2 opacity-75">Ready to use</p>
                            </div>

                            <!-- GCU Balance -->
                            <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl p-6 text-white">
                                <p class="text-sm font-medium opacity-90">GCU Balance</p>
                                <p class="text-3xl font-bold mt-2">
                                    Ǥ{{ number_format(auth()->user()->accounts->first() ? auth()->user()->accounts->first()->getBalance('GCU') / 100 : 0, 2) }}
                                </p>
                                <p class="text-xs mt-2 opacity-75">Global Currency Units</p>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <!-- Deposit -->
                            <a href="{{ route('wallet.deposit') }}" class="group relative bg-white dark:bg-gray-700 border-2 border-gray-200 dark:border-gray-600 rounded-xl p-6 hover:border-indigo-500 transition-all hover:shadow-lg">
                                <div class="flex flex-col items-center">
                                    <div class="p-3 bg-indigo-100 dark:bg-indigo-900 rounded-full mb-3 group-hover:scale-110 transition-transform">
                                        <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                        </svg>
                                    </div>
                                    <span class="font-medium text-gray-900 dark:text-white">Deposit</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 mt-1">Add funds</span>
                                </div>
                            </a>

                            <!-- Withdraw -->
                            <a href="{{ route('wallet.withdraw') }}" class="group relative bg-white dark:bg-gray-700 border-2 border-gray-200 dark:border-gray-600 rounded-xl p-6 hover:border-purple-500 transition-all hover:shadow-lg">
                                <div class="flex flex-col items-center">
                                    <div class="p-3 bg-purple-100 dark:bg-purple-900 rounded-full mb-3 group-hover:scale-110 transition-transform">
                                        <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                                        </svg>
                                    </div>
                                    <span class="font-medium text-gray-900 dark:text-white">Withdraw</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 mt-1">Cash out</span>
                                </div>
                            </a>

                            <!-- Transfer -->
                            <a href="{{ route('wallet.transfer') }}" class="group relative bg-white dark:bg-gray-700 border-2 border-gray-200 dark:border-gray-600 rounded-xl p-6 hover:border-green-500 transition-all hover:shadow-lg">
                                <div class="flex flex-col items-center">
                                    <div class="p-3 bg-green-100 dark:bg-green-900 rounded-full mb-3 group-hover:scale-110 transition-transform">
                                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                        </svg>
                                    </div>
                                    <span class="font-medium text-gray-900 dark:text-white">Transfer</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 mt-1">Send money</span>
                                </div>
                            </a>

                            <!-- Convert -->
                            <a href="{{ route('wallet.convert') }}" class="group relative bg-white dark:bg-gray-700 border-2 border-gray-200 dark:border-gray-600 rounded-xl p-6 hover:border-yellow-500 transition-all hover:shadow-lg">
                                <div class="flex flex-col items-center">
                                    <div class="p-3 bg-yellow-100 dark:bg-yellow-900 rounded-full mb-3 group-hover:scale-110 transition-transform">
                                        <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                        </svg>
                                    </div>
                                    <span class="font-medium text-gray-900 dark:text-white">Convert</span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400 mt-1">Exchange</span>
                                </div>
                            </a>
                        </div>
                    </div>

                    <!-- How to Deposit Section -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-6 mb-8">
                        <h4 class="text-lg font-semibold text-blue-900 dark:text-blue-100 mb-4">How to Deposit Funds</h4>
                        <div class="grid md:grid-cols-2 gap-6">
                            <div>
                                <h5 class="font-medium text-blue-800 dark:text-blue-200 mb-2">Option 1: Bank Transfer</h5>
                                <ol class="text-sm text-blue-700 dark:text-blue-300 space-y-2">
                                    <li>1. Click the "Deposit" button above</li>
                                    <li>2. Select "Bank Transfer" as your deposit method</li>
                                    <li>3. Copy the provided bank details and reference number</li>
                                    <li>4. Make a transfer from your bank to the provided account</li>
                                    <li>5. Funds will appear in 1-3 business days</li>
                                </ol>
                            </div>
                            <div>
                                <h5 class="font-medium text-blue-800 dark:text-blue-200 mb-2">Option 2: Card Payment</h5>
                                <ol class="text-sm text-blue-700 dark:text-blue-300 space-y-2">
                                    <li>1. Click the "Deposit" button above</li>
                                    <li>2. Select "Card Deposit" as your method</li>
                                    <li>3. Enter the amount you want to deposit</li>
                                    <li>4. Complete the secure payment form</li>
                                    <li>5. Funds appear instantly in your account</li>
                                </ol>
                            </div>
                        </div>
                        <p class="text-xs text-blue-600 dark:text-blue-400 mt-4">
                            Note: Card deposits include a 2.9% + $0.30 processing fee. Bank transfers have no fees but take longer.
                        </p>
                    </div>

                    <!-- Recent Transactions -->
                    <div>
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Transactions</h3>
                            <a href="{{ route('wallet.transactions') }}" class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">
                                View all →
                            </a>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-8 text-center">
                            <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            <p class="text-gray-600 dark:text-gray-400">No transactions yet</p>
                            <p class="text-sm text-gray-500 dark:text-gray-500 mt-1">Your transaction history will appear here once you start using your wallet</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Help Section -->
            <div class="mt-8 grid md:grid-cols-2 gap-6">
                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Need Help?</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Our support team is available 24/7 to help you with deposits, withdrawals, and account issues.
                    </p>
                    <a href="{{ route('support.contact') }}" class="inline-flex items-center text-sm font-medium text-indigo-600 hover:text-indigo-700 dark:text-indigo-400">
                        Contact Support
                        <svg class="ml-1 w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </div>

                <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                    <h4 class="font-semibold text-gray-900 dark:text-white mb-3">Security Tips</h4>
                    <ul class="text-sm text-gray-600 dark:text-gray-400 space-y-2">
                        <li>• Always verify bank details before making transfers</li>
                        <li>• Never share your account credentials</li>
                        <li>• Enable two-factor authentication</li>
                        <li>• Monitor your account for suspicious activity</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>