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
                    <form id="withdraw-form" class="space-y-6">
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
                            @if($balances->count() > 0)
                                @foreach($balances as $balance)
                                    <option value="{{ $balance->asset_code }}">
                                        {{ $balance->asset_code }} - {{ $balance->asset->name }} ({{ $balance->formatted_balance }})
                                    </option>
                                @endforeach
                            @else
                                <option value="">No balances available</option>
                            @endif
                        </select>
                        <div id="asset-error" class="text-red-600 text-sm mt-1 hidden"></div>
                    </div>

                    <div>
                        <x-label for="amount" value="{{ __('Amount') }}" />
                        <x-input id="amount" type="number" step="0.01" min="0.01" name="amount" class="mt-1 block w-full" required />
                        <div id="amount-error" class="text-red-600 text-sm mt-1 hidden"></div>
                    </div>

                    <div>
                        <x-label for="bank" value="{{ __('Withdraw To') }}" />
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            {{ __('Bank withdrawals are available through the dedicated withdrawal interface.') }}
                        </p>
                        <a href="{{ route('wallet.withdraw.create') }}" class="mt-2 inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition">
                            {{ __('Go to Bank Withdrawal') }}
                        </a>
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

                    <div id="success-message" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded hidden"></div>
                    <div id="error-message" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded hidden"></div>

                    <div class="flex items-center justify-end mt-6">
                        <x-button type="button" id="withdraw-btn">
                            {{ __('Withdraw Funds') }}
                        </x-button>
                    </div>
                </form>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const withdrawForm = document.getElementById('withdraw-form');
                    const withdrawBtn = document.getElementById('withdraw-btn');
                    const successMessage = document.getElementById('success-message');
                    const errorMessage = document.getElementById('error-message');
                    
                    withdrawBtn.addEventListener('click', async function(e) {
                        e.preventDefault();
                        
                        // Clear previous errors
                        clearErrors();
                        
                        const formData = new FormData(withdrawForm);
                        const accountUuid = formData.get('account_uuid');
                        
                        const requestData = {
                            amount: parseFloat(formData.get('amount')),
                            asset_code: formData.get('asset_code')
                        };
                        
                        try {
                            withdrawBtn.disabled = true;
                            withdrawBtn.textContent = 'Processing...';
                            
                            const response = await fetch(`/api/accounts/${accountUuid}/withdraw`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                    'Authorization': `Bearer {{ auth()->user()->currentAccessToken()->token ?? auth()->user()->createToken('wallet-access')->plainTextToken }}`
                                },
                                body: JSON.stringify(requestData)
                            });
                            
                            const result = await response.json();
                            
                            if (response.ok) {
                                showSuccess(result.message || 'Withdrawal initiated successfully');
                                withdrawForm.reset();
                                setTimeout(() => {
                                    window.location.href = '{{ route("dashboard") }}';
                                }, 2000);
                            } else {
                                if (result.errors) {
                                    showValidationErrors(result.errors);
                                } else {
                                    showError(result.message || 'Withdrawal failed');
                                }
                            }
                        } catch (error) {
                            showError('Network error occurred. Please try again.');
                            console.error('Withdrawal error:', error);
                        } finally {
                            withdrawBtn.disabled = false;
                            withdrawBtn.textContent = 'Withdraw Funds';
                        }
                    });
                    
                    function clearErrors() {
                        document.getElementById('amount-error').classList.add('hidden');
                        document.getElementById('asset-error').classList.add('hidden');
                        successMessage.classList.add('hidden');
                        errorMessage.classList.add('hidden');
                    }
                    
                    function showSuccess(message) {
                        successMessage.textContent = message;
                        successMessage.classList.remove('hidden');
                    }
                    
                    function showError(message) {
                        errorMessage.textContent = message;
                        errorMessage.classList.remove('hidden');
                    }
                    
                    function showValidationErrors(errors) {
                        if (errors.amount) {
                            const amountError = document.getElementById('amount-error');
                            amountError.textContent = errors.amount[0];
                            amountError.classList.remove('hidden');
                        }
                        if (errors.asset_code) {
                            const assetError = document.getElementById('asset-error');
                            assetError.textContent = errors.asset_code[0];
                            assetError.classList.remove('hidden');
                        }
                    }
                });
                </script>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>