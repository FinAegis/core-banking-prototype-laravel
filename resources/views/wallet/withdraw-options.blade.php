<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Choose Withdrawal Method') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 lg:p-8">
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                            Select Your Preferred Withdrawal Method
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Choose how you'd like to withdraw your funds. Each method has different processing times and requirements.
                        </p>
                    </div>

                    <!-- Available Balances -->
                    @if($balances && $balances->count() > 0)
                        <div class="mb-8">
                            <h4 class="text-md font-medium text-gray-900 dark:text-gray-100 mb-3">Available Balances</h4>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                @foreach($balances as $balance)
                                    @if($balance->balance > 0)
                                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                            <div class="text-sm text-gray-600 dark:text-gray-400">{{ $balance->asset->name }}</div>
                                            <div class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                                {{ $balance->asset->symbol }} {{ number_format($balance->balance / 100, 2) }}
                                            </div>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Withdrawal Options -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- OpenBanking Option -->
                        <a href="{{ route('wallet.withdraw.openbanking') }}" 
                           class="block border-2 border-gray-200 dark:border-gray-700 rounded-lg p-6 hover:border-blue-500 dark:hover:border-blue-400 transition-colors">
                            <div class="flex items-start space-x-4">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-lg flex items-center justify-center">
                                        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                        OpenBanking Withdrawal
                                    </h3>
                                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                        Connect directly to your bank for secure, fast withdrawals using the latest OpenBanking standards.
                                    </p>
                                    <ul class="mt-3 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                        <li class="flex items-center">
                                            <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                            </svg>
                                            Fastest processing (1-2 days)
                                        </li>
                                        <li class="flex items-center">
                                            <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                            </svg>
                                            Bank-grade security
                                        </li>
                                        <li class="flex items-center">
                                            <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                            </svg>
                                            No manual details required
                                        </li>
                                    </ul>
                                    <div class="mt-4">
                                        <span class="inline-flex items-center text-blue-600 dark:text-blue-400 font-medium">
                                            Select this method
                                            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </a>

                        <!-- Traditional Bank Transfer Option -->
                        <a href="{{ route('wallet.withdraw.create') }}" 
                           class="block border-2 border-gray-200 dark:border-gray-700 rounded-lg p-6 hover:border-gray-400 dark:hover:border-gray-500 transition-colors">
                            <div class="flex items-start space-x-4">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                                        <svg class="w-6 h-6 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                        </svg>
                                    </div>
                                </div>
                                <div class="flex-1">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                        Traditional Bank Transfer
                                    </h3>
                                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                        Enter your bank details manually for a standard bank transfer withdrawal.
                                    </p>
                                    <ul class="mt-3 space-y-1 text-sm text-gray-600 dark:text-gray-400">
                                        <li class="flex items-center">
                                            <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                            </svg>
                                            Standard processing (2-3 days)
                                        </li>
                                        <li class="flex items-center">
                                            <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                            </svg>
                                            Works with any bank
                                        </li>
                                        <li class="flex items-center">
                                            <svg class="w-4 h-4 text-green-500 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                            </svg>
                                            Save bank details for future use
                                        </li>
                                    </ul>
                                    <div class="mt-4">
                                        <span class="inline-flex items-center text-gray-600 dark:text-gray-400 font-medium">
                                            Select this method
                                            <svg class="w-4 h-4 ml-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                            </svg>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </a>
                    </div>

                    <!-- Information Section -->
                    <div class="mt-8 bg-gray-50 dark:bg-gray-700 rounded-lg p-6">
                        <h4 class="text-md font-medium text-gray-900 dark:text-gray-100 mb-3">Important Information</h4>
                        <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                            <li class="flex items-start">
                                <svg class="w-4 h-4 text-gray-400 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                                Minimum withdrawal amount is $10 or equivalent
                            </li>
                            <li class="flex items-start">
                                <svg class="w-4 h-4 text-gray-400 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                                Withdrawal fees may apply depending on your account type
                            </li>
                            <li class="flex items-start">
                                <svg class="w-4 h-4 text-gray-400 mt-0.5 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                </svg>
                                For security, first-time withdrawals may require additional verification
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>