<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Select Bank Account') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 lg:p-8">
                    <div class="mb-8">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                            Select Destination Account
                        </h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Choose which bank account you want to withdraw funds to.
                        </p>
                    </div>

                    @if (session('error'))
                        <div class="mb-4 bg-red-50 border border-red-200 text-red-600 px-4 py-3 rounded-lg">
                            {{ session('error') }}
                        </div>
                    @endif

                    <!-- Withdrawal Summary -->
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4 mb-6">
                        <div class="text-sm text-gray-600 dark:text-gray-400 mb-1">Withdrawal Amount</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                            {{ $currency }} {{ number_format($amount, 2) }}
                        </div>
                    </div>

                    <!-- Bank Accounts List -->
                    <form method="POST" action="{{ route('wallet.withdraw.openbanking.process') }}">
                        @csrf
                        <input type="hidden" name="bank_code" value="{{ $bankCode }}">
                        <input type="hidden" name="amount" value="{{ $amount }}">
                        <input type="hidden" name="currency" value="{{ $currency }}">
                        
                        <div class="space-y-3 mb-6">
                            @foreach($bankAccounts as $account)
                                <label class="block">
                                    <input type="radio" name="bank_account_id" value="{{ $account->id }}" 
                                           class="sr-only peer" required>
                                    <div class="border-2 border-gray-200 dark:border-gray-700 rounded-lg p-4 cursor-pointer transition-all
                                                peer-checked:border-blue-500 peer-checked:bg-blue-50 dark:peer-checked:bg-blue-900/20
                                                hover:border-gray-300 dark:hover:border-gray-600">
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center space-x-4">
                                                <div class="w-12 h-12 bg-gray-100 dark:bg-gray-700 rounded-lg flex items-center justify-center">
                                                    <svg class="w-6 h-6 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                                                    </svg>
                                                </div>
                                                <div>
                                                    <div class="font-medium text-gray-900 dark:text-gray-100">
                                                        {{ $account->accountType }} Account
                                                    </div>
                                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                                        @if($account->iban)
                                                            IBAN: {{ substr($account->iban, 0, 4) }}...{{ substr($account->iban, -4) }}
                                                        @else
                                                            Account: ****{{ substr($account->accountNumber, -4) }}
                                                        @endif
                                                    </div>
                                                    @if($account->holderName)
                                                        <div class="text-sm text-gray-500 dark:text-gray-500">
                                                            {{ $account->holderName }}
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    {{ $account->currency }}
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-500">
                                                    {{ ucfirst($account->status) }}
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </label>
                            @endforeach
                        </div>

                        <!-- Processing Time Notice -->
                        <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4 mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-amber-800 dark:text-amber-200">Processing Time</h3>
                                    <div class="mt-2 text-sm text-amber-700 dark:text-amber-300">
                                        <p>Withdrawals via OpenBanking typically arrive within 1-2 business days.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-between">
                            <a href="{{ route('wallet.withdraw.openbanking') }}" 
                               class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                                ← Back
                            </a>
                            <button type="submit"
                                    class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-6 rounded-lg transition duration-150 ease-in-out">
                                Confirm Withdrawal
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>