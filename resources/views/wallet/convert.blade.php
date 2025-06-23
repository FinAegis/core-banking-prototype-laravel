<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Convert Currency') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                @if(!auth()->user()->accounts->first())
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6 text-center">
                        <p class="text-gray-600 dark:text-gray-400">Create an account to get started with currency conversion</p>
                    </div>
                @else
                    <form method="POST" action="{{ route('wallet.convert.store') }}" class="space-y-6">
                    @csrf

                    <div>
                        <x-label for="account" value="{{ __('Account') }}" />
                        <select id="account" name="account_uuid" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" required>
                            @foreach(auth()->user()->accounts as $account)
                                <option value="{{ $account->uuid }}">
                                    {{ $account->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <x-label for="from_currency" value="{{ __('From Currency') }}" />
                            <select id="from_currency" name="from_asset_code" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" required>
                                <option value="USD">USD - US Dollar</option>
                                <option value="EUR">EUR - Euro</option>
                                <option value="GBP">GBP - British Pound</option>
                                <option value="CHF">CHF - Swiss Franc</option>
                                <option value="JPY">JPY - Japanese Yen</option>
                                <option value="GCU">GCU - Global Currency Unit</option>
                            </select>
                        </div>

                        <div>
                            <x-label for="to_currency" value="{{ __('To Currency') }}" />
                            <select id="to_currency" name="to_asset_code" class="mt-1 block w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" required>
                                <option value="EUR">EUR - Euro</option>
                                <option value="USD">USD - US Dollar</option>
                                <option value="GBP">GBP - British Pound</option>
                                <option value="CHF">CHF - Swiss Franc</option>
                                <option value="JPY">JPY - Japanese Yen</option>
                                <option value="GCU">GCU - Global Currency Unit</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <x-label for="amount" value="{{ __('Amount') }}" />
                        <x-input id="amount" type="number" step="0.01" min="0.01" name="amount" class="mt-1 block w-full" required />
                    </div>

                    <!-- Exchange Rate Preview -->
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                        <h3 class="text-sm font-medium text-gray-900 dark:text-gray-100 mb-2">{{ __('Exchange Rate') }}</h3>
                        <div class="text-2xl font-semibold text-gray-900 dark:text-gray-100" id="exchange-rate">
                            1 USD = -- EUR
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                            {{ __('You will receive approximately') }} <span id="converted-amount" class="font-semibold">--</span>
                        </p>
                    </div>

                    <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-md p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-green-800 dark:text-green-200">
                                    {{ __('Low Fees') }}
                                </h3>
                                <div class="mt-2 text-sm text-green-700 dark:text-green-300">
                                    <p>{{ __('Currency conversion fee: 0.01% - The lowest in the market!') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-end mt-6">
                        <x-button>
                            {{ __('Convert Currency') }}
                        </x-button>
                    </div>
                </form>
                @endif
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // Simple exchange rate preview (in production, this would fetch real rates)
        document.addEventListener('DOMContentLoaded', function() {
            const fromCurrency = document.getElementById('from_currency');
            const toCurrency = document.getElementById('to_currency');
            const amount = document.getElementById('amount');
            const exchangeRateDisplay = document.getElementById('exchange-rate');
            const convertedAmountDisplay = document.getElementById('converted-amount');

            function updatePreview() {
                // Mock exchange rates (in production, fetch from API)
                const rates = {
                    'USD-EUR': 0.92,
                    'EUR-USD': 1.09,
                    'USD-GBP': 0.79,
                    'GBP-USD': 1.27,
                    'USD-GCU': 0.95,
                    'GCU-USD': 1.05,
                    'EUR-GBP': 0.86,
                    'GBP-EUR': 1.16,
                };

                const from = fromCurrency.value;
                const to = toCurrency.value;
                const amountValue = parseFloat(amount.value) || 0;

                if (from === to) {
                    exchangeRateDisplay.textContent = '1 ' + from + ' = 1 ' + to;
                    convertedAmountDisplay.textContent = amountValue.toFixed(2) + ' ' + to;
                    return;
                }

                const rateKey = from + '-' + to;
                const rate = rates[rateKey] || 1;

                exchangeRateDisplay.textContent = '1 ' + from + ' = ' + rate.toFixed(4) + ' ' + to;
                convertedAmountDisplay.textContent = (amountValue * rate).toFixed(2) + ' ' + to;
            }

            fromCurrency.addEventListener('change', updatePreview);
            toCurrency.addEventListener('change', updatePreview);
            amount.addEventListener('input', updatePreview);

            updatePreview();
        });
    </script>
    @endpush
</x-app-layout>