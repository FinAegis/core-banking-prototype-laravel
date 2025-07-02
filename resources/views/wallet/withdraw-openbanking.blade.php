<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Withdraw via OpenBanking') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 lg:p-8">
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                            Withdraw Funds via OpenBanking
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Connect your bank account securely using OpenBanking to withdraw funds directly to your bank.
                        </p>
                    </div>

                    @if (session('error'))
                        <div class="mb-4 bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg">
                            {{ session('error') }}
                        </div>
                    @endif

                    <!-- Balance Display -->
                    <div class="mb-8">
                        <h4 class="text-md font-medium text-gray-900 dark:text-gray-100 mb-3">Available Balances</h4>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            @foreach($balances as $balance)
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                    <div class="text-sm text-gray-600 dark:text-gray-400">{{ $balance->asset->name }}</div>
                                    <div class="text-xl font-semibold text-gray-900 dark:text-gray-100">
                                        {{ $balance->asset->symbol }} {{ number_format($balance->balance / 100, 2) }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Connected Banks -->
                    @if($connectedBanks->isNotEmpty())
                        <div class="mb-8">
                            <h4 class="text-md font-medium text-gray-900 dark:text-gray-100 mb-3">Connected Banks</h4>
                            <div class="space-y-3">
                                @foreach($connectedBanks as $connection)
                                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                        <form action="{{ route('wallet.withdraw.openbanking.select-account') }}" method="POST" class="flex items-center justify-between">
                                            @csrf
                                            <div class="flex items-center space-x-4">
                                                <div class="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                                                    <svg class="w-6 h-6 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                                    </svg>
                                                </div>
                                                <div>
                                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                                        {{ $connection->metadata['bank_name'] ?? $connection->bankCode }}
                                                    </div>
                                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                                        Connected {{ $connection->createdAt->diffForHumans() }}
                                                    </div>
                                                </div>
                                            </div>
                                            <input type="hidden" name="bank_code" value="{{ $connection->bankCode }}">
                                            <button type="submit" class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 font-medium text-sm">
                                                Select →
                                            </button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Withdrawal Form -->
                    <form method="POST" action="{{ route('wallet.withdraw.openbanking.initiate') }}" class="space-y-6">
                        @csrf
                        
                        <div>
                            <label for="bank_code" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Select Bank
                            </label>
                            <select name="bank_code" id="bank_code" required
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600">
                                <option value="">Choose a bank...</option>
                                @foreach($availableBanks as $bank)
                                    <option value="{{ $bank['code'] }}">{{ $bank['name'] }}</option>
                                @endforeach
                            </select>
                            @error('bank_code')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Amount
                                </label>
                                <input type="number" name="amount" id="amount" step="0.01" min="10" required
                                       placeholder="0.00"
                                       class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600">
                                @error('amount')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="currency" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                    Currency
                                </label>
                                <select name="currency" id="currency" required
                                        class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600">
                                    <option value="USD">USD - US Dollar</option>
                                    <option value="EUR">EUR - Euro</option>
                                    <option value="GBP">GBP - British Pound</option>
                                </select>
                                @error('currency')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Security Notice -->
                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">Secure Connection</h3>
                                    <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                                        <p>You will be redirected to your bank's secure login page to authorize this withdrawal. FinAegis never sees or stores your bank login credentials.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Features -->
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                            <div class="text-center">
                                <div class="text-green-600 dark:text-green-400 mb-2">
                                    <svg class="w-8 h-8 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                    </svg>
                                </div>
                                <h4 class="font-medium text-gray-900 dark:text-gray-100">Bank-Grade Security</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">256-bit encryption</p>
                            </div>
                            <div class="text-center">
                                <div class="text-blue-600 dark:text-blue-400 mb-2">
                                    <svg class="w-8 h-8 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                </div>
                                <h4 class="font-medium text-gray-900 dark:text-gray-100">Fast Processing</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">1-2 business days</p>
                            </div>
                            <div class="text-center">
                                <div class="text-purple-600 dark:text-purple-400 mb-2">
                                    <svg class="w-8 h-8 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                </div>
                                <h4 class="font-medium text-gray-900 dark:text-gray-100">Low Fees</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">Competitive rates</p>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-6">
                            <a href="{{ route('wallet.withdraw.create') }}" 
                               class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                                ← Back to withdrawal options
                            </a>
                            <button type="submit"
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition duration-150 ease-in-out">
                                Connect Bank & Continue
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>