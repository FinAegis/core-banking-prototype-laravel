<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Bank Withdrawal') }}
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
                    <!-- Account Balances -->
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Available Balances</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            @foreach($balances as $balance)
                                @if($balance->balance > 0)
                                    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $balance->asset->name }}</div>
                                        <div class="text-xl font-semibold text-gray-900 dark:text-white">
                                            {{ $balance->asset->symbol }}{{ number_format($balance->balance / 100, 2) }}
                                        </div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                            {{ $balance->asset_code }}
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>

                    <form action="{{ route('wallet.withdraw.store') }}" method="POST" class="space-y-6">
                        @csrf
                        
                        <!-- Amount and Currency -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Withdrawal Amount
                                </label>
                                <div class="mt-1 relative rounded-md shadow-sm">
                                    <input type="number" 
                                           name="amount" 
                                           id="amount" 
                                           class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pr-12 sm:text-sm border-gray-300 rounded-md dark:bg-gray-900 dark:border-gray-700 dark:text-white @error('amount') border-red-300 @enderror" 
                                           placeholder="0.00"
                                           step="0.01"
                                           min="10"
                                           value="{{ old('amount') }}"
                                           required>
                                </div>
                                @error('amount')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="currency" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Currency
                                </label>
                                <select id="currency" 
                                        name="currency" 
                                        class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-900 dark:border-gray-700 dark:text-white @error('currency') border-red-300 @enderror">
                                    @foreach($balances as $balance)
                                        @if($balance->balance > 0)
                                            <option value="{{ $balance->asset_code }}" {{ old('currency') == $balance->asset_code ? 'selected' : '' }}>
                                                {{ $balance->asset_code }} (Available: {{ $balance->asset->symbol }}{{ number_format($balance->balance / 100, 2) }})
                                            </option>
                                        @endif
                                    @endforeach
                                </select>
                                @error('currency')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Bank Account Details -->
                        <div class="space-y-4 border-t border-gray-200 dark:border-gray-700 pt-4">
                            <div>
                                <label for="bank_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Bank Name
                                </label>
                                <input type="text" 
                                       name="bank_name" 
                                       id="bank_name" 
                                       class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md dark:bg-gray-900 dark:border-gray-700 dark:text-white @error('bank_name') border-red-300 @enderror"
                                       value="{{ old('bank_name') }}">
                                @error('bank_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="account_holder_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Account Holder Name
                                </label>
                                <input type="text" 
                                       name="account_holder_name" 
                                       id="account_holder_name" 
                                       class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md dark:bg-gray-900 dark:border-gray-700 dark:text-white @error('account_holder_name') border-red-300 @enderror"
                                       value="{{ old('account_holder_name', auth()->user()->name) }}">
                                @error('account_holder_name')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div>
                                <label for="account_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Account Number / IBAN
                                </label>
                                <input type="text" 
                                       name="account_number" 
                                       id="account_number" 
                                       class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md dark:bg-gray-900 dark:border-gray-700 dark:text-white @error('account_number') border-red-300 @enderror"
                                       value="{{ old('account_number') }}">
                                @error('account_number')
                                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="routing_number" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Routing Number (US)
                                    </label>
                                    <input type="text" 
                                           name="routing_number" 
                                           id="routing_number" 
                                           class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                                           value="{{ old('routing_number') }}">
                                </div>

                                <div>
                                    <label for="swift" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        SWIFT/BIC Code
                                    </label>
                                    <input type="text" 
                                           name="swift" 
                                           id="swift" 
                                           class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                                           value="{{ old('swift') }}">
                                </div>
                            </div>

                        </div>

                        <!-- Fee Notice -->
                        <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-blue-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                                        Withdrawal Information
                                    </h3>
                                    <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                                        <ul class="list-disc list-inside">
                                            <li>Processing time: 1-3 business days</li>
                                            <li>Minimum withdrawal: $10.00</li>
                                            <li>Bank transfer fee: $5.00 (deducted from withdrawal amount)</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div>
                            <button type="submit" 
                                    class="w-full flex justify-center py-3 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                                Process Withdrawal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>