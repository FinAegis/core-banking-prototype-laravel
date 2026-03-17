<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Transfer Funds') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg p-6">
                @if(!auth()->user()->accounts->first())
                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6 text-center">
                        <p class="text-gray-600 dark:text-gray-400">Create an account to get started with transfers</p>
                    </div>
                @else
                    <form id="transfer-form" class="space-y-6">
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
                        <div id="from-account-error" class="text-red-600 text-sm mt-1 hidden"></div>
                    </div>

                    <div>
                        <x-label for="to_account" value="{{ __('To Account') }}" />
                        <x-input id="to_account" type="text" name="to_account_uuid" placeholder="Enter recipient account UUID" class="mt-1 block w-full" required />
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ __('Ask the recipient for their account UUID') }}</p>
                        <div id="to-account-error" class="text-red-600 text-sm mt-1 hidden"></div>
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
                        <x-label for="reference" value="{{ __('Reference (Optional)') }}" />
                        <x-input id="reference" type="text" name="reference" placeholder="Payment for services" class="mt-1 block w-full" />
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
                                    <p>{{ __('Transfers between ' . config('brand.name', 'Zelta') . ' accounts are instant and free.') }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="success-message" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded hidden"></div>
                    <div id="error-message" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded hidden"></div>

                    <div class="flex items-center justify-end mt-6">
                        <x-button type="button" id="transfer-btn">
                            {{ __('Transfer Funds') }}
                        </x-button>
                    </div>
                </form>

                <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const transferForm = document.getElementById('transfer-form');
                    const transferBtn = document.getElementById('transfer-btn');
                    const successMessage = document.getElementById('success-message');
                    const errorMessage = document.getElementById('error-message');
                    
                    transferBtn.addEventListener('click', async function(e) {
                        e.preventDefault();
                        
                        // Clear previous errors
                        clearErrors();
                        
                        const formData = new FormData(transferForm);
                        
                        const requestData = {
                            from_account: formData.get('from_account_uuid'),
                            to_account: formData.get('to_account_uuid'),
                            amount: parseFloat(formData.get('amount')),
                            asset_code: formData.get('asset_code'),
                            reference: formData.get('reference') || null
                        };
                        
                        try {
                            transferBtn.disabled = true;
                            transferBtn.textContent = 'Processing...';
                            
                            const response = await fetch('/api/transfers', {
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
                                showSuccess('Transfer initiated successfully');
                                transferForm.reset();
                                setTimeout(() => {
                                    window.location.href = '{{ route("dashboard") }}';
                                }, 2000);
                            } else {
                                if (result.errors) {
                                    showValidationErrors(result.errors);
                                } else {
                                    showError(result.message || 'Transfer failed');
                                }
                            }
                        } catch (error) {
                            showError('Network error occurred. Please try again.');
                            console.error('Transfer error:', error);
                        } finally {
                            transferBtn.disabled = false;
                            transferBtn.textContent = 'Transfer Funds';
                        }
                    });
                    
                    function clearErrors() {
                        document.getElementById('from-account-error').classList.add('hidden');
                        document.getElementById('to-account-error').classList.add('hidden');
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
                        if (errors.from_account) {
                            const error = document.getElementById('from-account-error');
                            error.textContent = errors.from_account[0];
                            error.classList.remove('hidden');
                        }
                        if (errors.to_account) {
                            const error = document.getElementById('to-account-error');
                            error.textContent = errors.to_account[0];
                            error.classList.remove('hidden');
                        }
                        if (errors.amount) {
                            const error = document.getElementById('amount-error');
                            error.textContent = errors.amount[0];
                            error.classList.remove('hidden');
                        }
                        if (errors.asset_code) {
                            const error = document.getElementById('asset-error');
                            error.textContent = errors.asset_code[0];
                            error.classList.remove('hidden');
                        }
                    }
                });
                </script>
                @endif
            </div>
        </div>
    </div>
</x-app-layout>