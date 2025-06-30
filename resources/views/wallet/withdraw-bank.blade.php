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

                        <!-- Bank Account Selection -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-3">
                                Bank Account
                            </label>
                            
                            <input type="hidden" name="bank_account_type" value="{{ $bankAccounts->count() > 0 ? 'saved' : 'new' }}" id="bank_account_type">
                            
                            @if($bankAccounts->count() > 0)
                                <div class="space-y-2 mb-4">
                                    @foreach($bankAccounts as $bankAccount)
                                        <label class="relative block cursor-pointer rounded-lg border bg-white dark:bg-gray-900 px-6 py-4 shadow-sm focus-within:ring-2 focus-within:ring-indigo-500 focus-within:ring-offset-2 hover:border-gray-400">
                                            <input type="radio" 
                                                   name="bank_account_id" 
                                                   value="{{ $bankAccount->id }}" 
                                                   class="sr-only"
                                                   onchange="document.getElementById('bank_account_type').value='saved'; document.getElementById('new-bank-account-fields').classList.add('hidden');"
                                                   {{ $loop->first ? 'checked' : '' }}>
                                            <span class="flex items-center justify-between">
                                                <span class="text-sm flex flex-col">
                                                    <span class="font-medium text-gray-900 dark:text-white">
                                                        {{ $bankAccount->display_name }}
                                                    </span>
                                                    <span class="text-gray-500 dark:text-gray-400">
                                                        {{ $bankAccount->account_holder_name }}
                                                        @if(!$bankAccount->verified)
                                                            <span class="ml-2 text-yellow-600 text-xs">(Unverified)</span>
                                                        @endif
                                                    </span>
                                                </span>
                                                @if($bankAccount->is_default)
                                                    <span class="text-xs bg-indigo-100 text-indigo-800 px-2 py-1 rounded">Default</span>
                                                @endif
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
                                           name="bank_account_id" 
                                           value="new" 
                                           class="sr-only"
                                           onchange="document.getElementById('bank_account_type').value='new'; document.getElementById('new-bank-account-fields').classList.remove('hidden');"
                                           {{ $bankAccounts->count() === 0 ? 'checked' : '' }}>
                                    <span class="flex items-center">
                                        <span class="text-sm flex flex-col">
                                            <span class="font-medium text-gray-900 dark:text-white">
                                                Add new bank account
                                            </span>
                                        </span>
                                    </span>
                                    <span class="absolute -inset-px rounded-lg border-2 pointer-events-none" aria-hidden="true"></span>
                                </label>
                            </div>
                        </div>

                        <!-- New Bank Account Fields -->
                        <div id="new-bank-account-fields" class="{{ $bankAccounts->count() > 0 ? 'hidden' : '' }} space-y-4 border-t border-gray-200 dark:border-gray-700 pt-4">
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

                            <div>
                                <label class="flex items-center">
                                    <input type="checkbox" 
                                           name="save_bank_account" 
                                           value="1" 
                                           class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50"
                                           {{ old('save_bank_account') ? 'checked' : '' }}>
                                    <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">
                                        Save this bank account for future withdrawals
                                    </span>
                                </label>
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

                    <!-- Saved Bank Accounts Management -->
                    @if($bankAccounts->count() > 0)
                        <div class="mt-8 border-t border-gray-200 dark:border-gray-700 pt-8">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Manage Bank Accounts</h3>
                            <div class="space-y-3">
                                @foreach($bankAccounts as $bankAccount)
                                    <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-900 rounded-lg">
                                        <div>
                                            <div class="font-medium text-gray-900 dark:text-white">
                                                {{ $bankAccount->display_name }}
                                            </div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ $bankAccount->account_holder_name }}
                                                @if(!$bankAccount->verified)
                                                    <span class="ml-2 text-yellow-600">(Pending verification)</span>
                                                @endif
                                            </div>
                                        </div>
                                        <form action="{{ route('wallet.withdraw.bank-account.remove', $bankAccount) }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-red-600 hover:text-red-900 text-sm">
                                                Remove
                                            </button>
                                        </form>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>