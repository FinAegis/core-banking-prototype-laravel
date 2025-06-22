<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Transfer Funds') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                <form method="POST" action="{{ route('api.transfers.store') }}" class="space-y-6">
                    @csrf

                    <div>
                        <x-label for="from_account" value="{{ __('From Account') }}" />
                        <select id="from_account" name="from_account_uuid" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" required>
                            @foreach(auth()->user()->accounts as $account)
                                <option value="{{ $account->uuid }}">
                                    {{ $account->name }} - {{ $account->formatted_balance }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <x-label for="to_account" value="{{ __('To Account') }}" />
                        <x-input id="to_account" type="text" name="to_account_uuid" placeholder="Enter recipient account UUID" class="mt-1 block w-full" required />
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Ask the recipient for their account UUID') }}</p>
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
                        <x-label for="description" value="{{ __('Description (Optional)') }}" />
                        <x-input id="description" type="text" name="description" placeholder="Payment for services" class="mt-1 block w-full" />
                    </div>

                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-blue-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a.75.75 0 000 1.5h.253a.25.25 0 01.244.304l-.459 2.066A1.75 1.75 0 0010.747 15H11a.75.75 0 000-1.5h-.253a.25.25 0 01-.244-.304l.459-2.066A1.75 1.75 0 009.253 9H9z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-blue-800 dark:text-blue-200">
                                    {{ __('Instant Transfer') }}
                                </h3>
                                <div class="mt-2 text-sm text-blue-700 dark:text-blue-300">
                                    <p>{{ __('Transfers between FinAegis accounts are instant and free.') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end mt-6">
                        <x-button>
                            {{ __('Transfer Funds') }}
                        </x-button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>