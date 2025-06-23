<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Withdraw Funds') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                @if(!auth()->user()->accounts->first())
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6 text-center">
                        <p class="text-gray-600 dark:text-gray-400">Create an account to get started with withdrawals</p>
                    </div>
                @else
                    <form method="POST" action="{{ route('wallet.withdraw.store') }}" class="space-y-6">
                    @csrf

                    <div>
                        <x-label for="account" value="{{ __('From Account') }}" />
                        <select id="account" name="account_uuid" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" required>
                            @foreach(auth()->user()->accounts as $account)
                                <option value="{{ $account->uuid }}">
                                    {{ $account->name }} - {{ $account->formatted_balance }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-label for="asset" value="{{ __('Currency') }}" />
                        <select id="asset" name="asset_code" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" required>
                            <option value="USD">USD - US Dollar</option>
                            <option value="EUR">EUR - Euro</option>
                            <option value="GBP">GBP - British Pound</option>
                            <option value="GCU">GCU - Global Currency Unit</option>
                        </select>
                    </div>

                    <div>
                        <x-label for="amount" value="{{ __('Amount') }}" />
                        <x-input id="amount" type="number" step="0.01" min="0.01" name="amount" class="mt-1 block w-full" required />
                    </div>

                    <div>
                        <x-label for="bank" value="{{ __('Withdraw To') }}" />
                        <select id="bank" name="bank" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" required>
                            <option value="primary">Primary Bank Account</option>
                            <option value="paysera">Paysera</option>
                            <option value="deutsche_bank">Deutsche Bank</option>
                            <option value="santander">Santander</option>
                        </select>
                    </div>

                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                    {{ __('Withdrawal Notice') }}
                                </h3>
                                <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                                    <p>{{ __('Withdrawals typically process within 1-3 business days depending on your bank.') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end mt-6">
                        <x-button>
                            {{ __('Withdraw Funds') }}
                        </x-button>
                    </div>
                </form>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>