<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Card Payment') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">
                        Complete Card Payment
                    </h3>
                    
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6">
                        <p class="text-blue-800 dark:text-blue-200">
                            Investment Amount: <strong>${{ number_format($amount, 2) }} USD</strong>
                        </p>
                    </div>
                    
                    <!-- Stripe Integration Notice -->
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-6">
                        <h4 class="font-semibold text-yellow-800 dark:text-yellow-200 mb-2">Coming Soon</h4>
                        <p class="text-sm text-yellow-700 dark:text-yellow-300">
                            Card payment integration is currently being implemented. For now, please use cryptocurrency or bank transfer options.
                        </p>
                    </div>
                    
                    <!-- Alternative Payment Methods -->
                    <div class="mb-6">
                        <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-3">Alternative Payment Methods:</h4>
                        <div class="space-y-3">
                            <a href="{{ route('cgo.invest') }}" class="block p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-indigo-500 transition">
                                <div class="flex items-center">
                                    <svg class="w-6 h-6 text-indigo-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <div>
                                        <p class="font-semibold text-gray-900 dark:text-gray-100">Cryptocurrency</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Pay with BTC, ETH, USDT, or USDC</p>
                                    </div>
                                </div>
                            </a>
                            
                            <a href="{{ route('cgo.invest') }}" class="block p-4 border border-gray-200 dark:border-gray-700 rounded-lg hover:border-indigo-500 transition">
                                <div class="flex items-center">
                                    <svg class="w-6 h-6 text-indigo-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                                    </svg>
                                    <div>
                                        <p class="font-semibold text-gray-900 dark:text-gray-100">Bank Transfer</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Wire transfer from your bank account</p>
                                    </div>
                                </div>
                            </a>
                        </div>
                    </div>
                    
                    <!-- Investment Details -->
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 mb-6">
                        <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">Investment Summary:</h4>
                        <dl class="text-sm space-y-1">
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">Investment ID:</dt>
                                <dd class="font-mono text-gray-900 dark:text-gray-100">{{ $investment->uuid }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">Amount:</dt>
                                <dd class="font-semibold text-gray-900 dark:text-gray-100">${{ number_format($investment->amount, 2) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">Shares:</dt>
                                <dd class="font-semibold text-gray-900 dark:text-gray-100">{{ number_format($investment->shares_purchased, 4) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">Tier:</dt>
                                <dd class="font-semibold text-gray-900 dark:text-gray-100">{{ ucfirst($investment->tier) }}</dd>
                            </div>
                        </dl>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex justify-center">
                        <a href="{{ route('cgo.invest') }}" class="bg-indigo-600 text-white px-8 py-3 rounded-md hover:bg-indigo-700 transition">
                            Choose Different Payment Method
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>