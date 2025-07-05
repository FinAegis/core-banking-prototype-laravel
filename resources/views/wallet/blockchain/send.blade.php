<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Send {{ $supportedChains[$address->chain]['symbol'] }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <!-- Balance Overview -->
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 rounded-lg shadow-lg p-6 mb-6 text-white">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <p class="text-sm opacity-90">Available Balance</p>
                        <p class="text-2xl font-bold">{{ number_format($balance['available'], 8) }} {{ $supportedChains[$address->chain]['symbol'] }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm opacity-90">From</p>
                        <p class="font-medium">{{ $address->label }}</p>
                        <p class="font-mono text-xs opacity-75">{{ substr($address->address, 0, 16) }}...{{ substr($address->address, -8) }}</p>
                    </div>
                </div>
            </div>

            <!-- Send Form -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('wallet.blockchain.send.process', $address->uuid) }}" id="sendForm">
                        @csrf

                        <!-- Recipient Address -->
                        <div class="mb-6">
                            <x-label for="recipient_address" value="{{ __('Recipient Address') }}" />
                            <x-input id="recipient_address" 
                                     type="text" 
                                     name="recipient_address" 
                                     class="mt-1 block w-full font-mono" 
                                     placeholder="Enter {{ $supportedChains[$address->chain]['name'] }} address"
                                     required />
                            <x-input-error :messages="$errors->get('recipient_address')" class="mt-2" />
                        </div>

                        <!-- Amount -->
                        <div class="mb-6">
                            <x-label for="amount" value="{{ __('Amount') }}" />
                            <div class="mt-1 relative rounded-md shadow-sm">
                                <x-input id="amount" 
                                         type="number" 
                                         name="amount" 
                                         class="block w-full pr-20" 
                                         placeholder="0.00000000"
                                         step="0.00000001"
                                         min="0.00000001"
                                         max="{{ $balance['available'] }}"
                                         required />
                                <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 sm:text-sm">{{ $supportedChains[$address->chain]['symbol'] }}</span>
                                </div>
                            </div>
                            <div class="mt-2 flex justify-between text-sm">
                                <span class="text-gray-600 dark:text-gray-400" id="usdValue">≈ $0.00 USD</span>
                                <button type="button" 
                                        onclick="setMaxAmount()" 
                                        class="text-indigo-600 hover:text-indigo-700 font-medium">
                                    Send Max
                                </button>
                            </div>
                            <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                        </div>

                        <!-- Network Fee -->
                        <div class="mb-6">
                            <x-label value="{{ __('Network Fee') }}" />
                            <div class="mt-2 grid grid-cols-3 gap-3">
                                @foreach($networkFees as $level => $fee)
                                    <label class="relative flex cursor-pointer rounded-lg border bg-white dark:bg-gray-700 p-4 shadow-sm focus:outline-none">
                                        <input type="radio" name="fee_level" value="{{ $level }}" class="sr-only" {{ $level === 'medium' ? 'checked' : '' }} required>
                                        <div class="flex flex-1 flex-col">
                                            <span class="block text-sm font-medium text-gray-900 dark:text-gray-100 capitalize">
                                                {{ $level }}
                                            </span>
                                            <span class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                {{ $fee['time'] }}
                                            </span>
                                            <span class="mt-2 text-sm font-medium text-gray-900 dark:text-gray-100">
                                                {{ number_format($fee['amount'], 8) }} {{ $supportedChains[$address->chain]['symbol'] }}
                                            </span>
                                        </div>
                                        <div class="absolute -inset-px rounded-lg border-2 pointer-events-none" aria-hidden="true"></div>
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('fee_level')" class="mt-2" />
                        </div>

                        <!-- Memo/Note (Optional) -->
                        <div class="mb-6">
                            <x-label for="memo" value="{{ __('Memo/Note (Optional)') }}" />
                            <x-input id="memo" 
                                     type="text" 
                                     name="memo" 
                                     class="mt-1 block w-full" 
                                     placeholder="Optional transaction note"
                                     maxlength="255" />
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                This note is for your records only and won't be sent with the transaction
                            </p>
                            <x-input-error :messages="$errors->get('memo')" class="mt-2" />
                        </div>

                        <!-- Transaction Summary -->
                        <div class="mb-6 bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <h4 class="font-medium mb-3">Transaction Summary</h4>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Amount to send</span>
                                    <span id="summaryAmount">0.00000000 {{ $supportedChains[$address->chain]['symbol'] }}</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Network fee</span>
                                    <span id="summaryFee">0.00200000 {{ $supportedChains[$address->chain]['symbol'] }}</span>
                                </div>
                                <div class="border-t pt-2 flex justify-between font-medium">
                                    <span>Total deducted</span>
                                    <span id="summaryTotal">0.00200000 {{ $supportedChains[$address->chain]['symbol'] }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Password Confirmation -->
                        <div class="mb-6">
                            <x-label for="password" value="{{ __('Confirm Your Password') }}" />
                            <x-input id="password" 
                                     type="password" 
                                     name="password" 
                                     class="mt-1 block w-full" 
                                     required />
                            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                Enter your account password to authorize this transaction
                            </p>
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>

                        @if ($errors->has('error'))
                            <div class="mb-4 p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                                <p class="text-sm text-red-600 dark:text-red-400">{{ $errors->first('error') }}</p>
                            </div>
                        @endif

                        <!-- Security Notice -->
                        <div class="mb-6 bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-amber-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-amber-700 dark:text-amber-300">
                                        Double-check the recipient address. Blockchain transactions are irreversible.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center justify-end space-x-3">
                            <a href="{{ route('wallet.blockchain.show', $address->uuid) }}" 
                               class="px-4 py-2 bg-gray-300 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-600 transition">
                                Cancel
                            </a>
                            <button type="submit" 
                                    id="sendButton"
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition disabled:opacity-50 disabled:cursor-not-allowed">
                                Send {{ $supportedChains[$address->chain]['symbol'] }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        const balance = {{ $balance['available'] }};
        const symbol = '{{ $supportedChains[$address->chain]['symbol'] }}';
        const networkFees = @json($networkFees);
        const usdRate = {{ $supportedChains[$address->chain]['symbol'] === 'BTC' ? 30000 : ($supportedChains[$address->chain]['symbol'] === 'ETH' ? 2000 : 1) }};
        
        // Update USD value when amount changes
        document.getElementById('amount').addEventListener('input', function() {
            const amount = parseFloat(this.value) || 0;
            const usdValue = amount * usdRate;
            document.getElementById('usdValue').textContent = `≈ $${usdValue.toFixed(2)} USD`;
            updateSummary();
        });
        
        // Update fee selection styling
        document.querySelectorAll('input[type="radio"][name="fee_level"]').forEach(radio => {
            radio.addEventListener('change', function() {
                // Reset all
                document.querySelectorAll('input[type="radio"][name="fee_level"]').forEach(r => {
                    r.closest('label').classList.remove('border-indigo-600', 'ring-2', 'ring-indigo-600');
                    r.closest('label').classList.add('border');
                });
                
                // Highlight selected
                if (this.checked) {
                    this.closest('label').classList.add('border-indigo-600', 'ring-2', 'ring-indigo-600');
                    this.closest('label').classList.remove('border');
                    updateSummary();
                }
            });
        });
        
        // Set initial fee selection styling
        document.querySelector('input[type="radio"][name="fee_level"]:checked')?.closest('label').classList.add('border-indigo-600', 'ring-2', 'ring-indigo-600');
        
        // Set max amount
        function setMaxAmount() {
            const selectedFee = document.querySelector('input[name="fee_level"]:checked')?.value || 'medium';
            const fee = networkFees[selectedFee].amount;
            const maxAmount = Math.max(0, balance - fee);
            document.getElementById('amount').value = maxAmount.toFixed(8);
            document.getElementById('amount').dispatchEvent(new Event('input'));
        }
        
        // Update transaction summary
        function updateSummary() {
            const amount = parseFloat(document.getElementById('amount').value) || 0;
            const selectedFee = document.querySelector('input[name="fee_level"]:checked')?.value || 'medium';
            const fee = networkFees[selectedFee].amount;
            const total = amount + fee;
            
            document.getElementById('summaryAmount').textContent = `${amount.toFixed(8)} ${symbol}`;
            document.getElementById('summaryFee').textContent = `${fee.toFixed(8)} ${symbol}`;
            document.getElementById('summaryTotal').textContent = `${total.toFixed(8)} ${symbol}`;
            
            // Enable/disable send button based on balance
            const sendButton = document.getElementById('sendButton');
            if (total > balance) {
                sendButton.disabled = true;
                sendButton.textContent = 'Insufficient Balance';
            } else {
                sendButton.disabled = false;
                sendButton.textContent = `Send ${symbol}`;
            }
        }
        
        // Initial summary update
        updateSummary();
        
        // Form submission confirmation
        document.getElementById('sendForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const amount = parseFloat(document.getElementById('amount').value);
            const recipient = document.getElementById('recipient_address').value;
            
            if (confirm(`Are you sure you want to send ${amount.toFixed(8)} ${symbol} to ${recipient}?`)) {
                this.submit();
            }
        });
    </script>
    @endpush
</x-app-layout>