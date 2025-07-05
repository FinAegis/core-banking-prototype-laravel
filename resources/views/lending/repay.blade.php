<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Make Loan Payment
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <!-- Loan Summary -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Loan Information</h3>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Loan ID</p>
                            <p class="font-medium">{{ substr($loan->loan_uuid, 0, 8) }}...</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-600 dark:text-gray-400">Outstanding Balance</p>
                            <p class="font-medium text-lg">${{ number_format($outstandingBalance, 2) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Next Payment Due -->
            @if($nextPayment)
                <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Next Payment Due</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Due Date</p>
                                <p class="font-medium">{{ \Carbon\Carbon::parse($nextPayment['due_date'])->format('M d, Y') }}</p>
                                <p class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($nextPayment['due_date'])->diffForHumans() }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Amount Due</p>
                                <p class="font-medium text-lg">${{ number_format($nextPayment['amount'], 2) }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Breakdown</p>
                                <p class="text-sm">Principal: ${{ number_format($nextPayment['principal'], 2) }}</p>
                                <p class="text-sm">Interest: ${{ number_format($nextPayment['interest'], 2) }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Payment Form -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('lending.repay.process', $loan->loan_uuid) }}">
                        @csrf

                        <!-- Account Selection -->
                        <div class="mb-6">
                            <x-label for="account_id" value="{{ __('Pay From Account') }}" />
                            <select id="account_id" name="account_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600" required>
                                <option value="">Select an account</option>
                                @foreach($accounts as $account)
                                    @php
                                        $balance = $account->balances->where('asset_code', 'USD')->first();
                                    @endphp
                                    <option value="{{ $account->uuid }}" {{ $balance && $balance->balance >= ($nextPayment['amount'] ?? 0) * 100 ? '' : 'disabled' }}>
                                        {{ $account->name }} - Balance: ${{ number_format(($balance->balance ?? 0) / 100, 2) }}
                                        {{ $balance && $balance->balance >= ($nextPayment['amount'] ?? 0) * 100 ? '' : '(Insufficient funds)' }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('account_id')" class="mt-2" />
                        </div>

                        <!-- Payment Type -->
                        <div class="mb-6">
                            <x-label for="payment_type" value="{{ __('Payment Type') }}" />
                            <div class="mt-2 space-y-2">
                                @if($nextPayment)
                                    <label class="flex items-center">
                                        <input type="radio" name="payment_type" value="scheduled" class="mr-2" checked>
                                        <span>Scheduled Payment - ${{ number_format($nextPayment['amount'], 2) }}</span>
                                    </label>
                                @endif
                                <label class="flex items-center">
                                    <input type="radio" name="payment_type" value="partial" class="mr-2" {{ !$nextPayment ? 'checked' : '' }}>
                                    <span>Partial Payment</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="payment_type" value="full" class="mr-2">
                                    <span>Pay Off Loan - ${{ number_format($outstandingBalance, 2) }}</span>
                                </label>
                            </div>
                            <x-input-error :messages="$errors->get('payment_type')" class="mt-2" />
                        </div>

                        <!-- Payment Amount -->
                        <div class="mb-6" id="amount-section" style="display: none;">
                            <x-label for="amount" value="{{ __('Payment Amount') }}" />
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm">$</span>
                                </div>
                                <input type="number" 
                                       name="amount" 
                                       id="amount" 
                                       step="0.01"
                                       min="0.01"
                                       max="{{ $outstandingBalance }}"
                                       class="block w-full pl-7 pr-12 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                                       placeholder="0.00">
                            </div>
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Maximum: ${{ number_format($outstandingBalance, 2) }}
                            </p>
                            <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                        </div>

                        <!-- Payment Summary -->
                        <div class="mb-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <h4 class="font-semibold mb-3">Payment Summary</h4>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Payment Amount</span>
                                    <span id="summary-amount">${{ $nextPayment ? number_format($nextPayment['amount'], 2) : '0.00' }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Outstanding After Payment</span>
                                    <span id="summary-remaining">${{ $nextPayment ? number_format($outstandingBalance - $nextPayment['amount'], 2) : number_format($outstandingBalance, 2) }}</span>
                                </div>
                                <div class="flex justify-between font-semibold pt-2 border-t">
                                    <span>New Outstanding Balance</span>
                                    <span id="summary-new-balance">${{ $nextPayment ? number_format($outstandingBalance - $nextPayment['amount'], 2) : number_format($outstandingBalance, 2) }}</span>
                                </div>
                            </div>
                        </div>

                        @if ($errors->has('error'))
                            <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                                <p class="text-sm text-red-600 dark:text-red-400">{{ $errors->first('error') }}</p>
                            </div>
                        @endif

                        <div class="flex items-center justify-end space-x-3">
                            <a href="{{ route('lending.loan', $loan->loan_uuid) }}" 
                               class="px-4 py-2 bg-gray-300 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-600 transition">
                                Cancel
                            </a>
                            <button type="submit" 
                                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                                Process Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        const outstandingBalance = {{ $outstandingBalance }};
        const nextPaymentAmount = {{ $nextPayment ? $nextPayment['amount'] : 0 }};
        
        // Payment type change handler
        document.querySelectorAll('input[name="payment_type"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const amountSection = document.getElementById('amount-section');
                const amountInput = document.getElementById('amount');
                const summaryAmount = document.getElementById('summary-amount');
                const summaryRemaining = document.getElementById('summary-remaining');
                const summaryNewBalance = document.getElementById('summary-new-balance');
                
                if (this.value === 'partial') {
                    amountSection.style.display = 'block';
                    amountInput.required = true;
                    updateSummary(0);
                } else {
                    amountSection.style.display = 'none';
                    amountInput.required = false;
                    
                    let paymentAmount = 0;
                    if (this.value === 'scheduled') {
                        paymentAmount = nextPaymentAmount;
                    } else if (this.value === 'full') {
                        paymentAmount = outstandingBalance;
                    }
                    
                    updateSummary(paymentAmount);
                }
            });
        });
        
        // Amount input handler
        document.getElementById('amount').addEventListener('input', function() {
            const amount = parseFloat(this.value) || 0;
            updateSummary(amount);
        });
        
        // Update payment summary
        function updateSummary(amount) {
            const summaryAmount = document.getElementById('summary-amount');
            const summaryRemaining = document.getElementById('summary-remaining');
            const summaryNewBalance = document.getElementById('summary-new-balance');
            
            summaryAmount.textContent = `$${amount.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`;
            
            const remaining = Math.max(0, outstandingBalance - amount);
            summaryRemaining.textContent = `$${remaining.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`;
            summaryNewBalance.textContent = `$${remaining.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',')}`;
        }
    </script>
    @endpush
</x-app-layout>