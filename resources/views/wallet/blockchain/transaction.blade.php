<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Transaction Details') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <!-- Transaction Status -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-lg font-semibold">Transaction Status</h3>
                        @if($transaction->status === 'confirmed')
                            <span class="px-3 py-1 text-sm font-semibold rounded-full bg-green-100 text-green-800">
                                <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                Confirmed
                            </span>
                        @elseif($transaction->status === 'pending')
                            <span class="px-3 py-1 text-sm font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                <svg class="w-4 h-4 inline mr-1 animate-spin" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                                Pending
                            </span>
                        @else
                            <span class="px-3 py-1 text-sm font-semibold rounded-full bg-red-100 text-red-800">
                                <svg class="w-4 h-4 inline mr-1" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
                                </svg>
                                Failed
                            </span>
                        @endif
                    </div>

                    <!-- Progress Bar for Pending Transactions -->
                    @if($transaction->status === 'pending' && isset($blockchainData['confirmations']))
                        @php
                            $requiredConfirmations = $transaction->chain === 'bitcoin' ? 6 : 12;
                            $currentConfirmations = $blockchainData['confirmations'] ?? 0;
                            $progress = min(100, ($currentConfirmations / $requiredConfirmations) * 100);
                        @endphp
                        <div class="mb-4">
                            <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-1">
                                <span>Confirmations</span>
                                <span>{{ $currentConfirmations }} / {{ $requiredConfirmations }}</span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                <div class="bg-indigo-600 h-2 rounded-full transition-all duration-500" style="width: {{ $progress }}%"></div>
                            </div>
                        </div>
                    @endif

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Transaction Info -->
                        <div class="space-y-4">
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Transaction Hash</p>
                                <div class="flex items-center mt-1">
                                    <p class="font-mono text-sm break-all">{{ $transaction->tx_hash }}</p>
                                    <button onclick="copyToClipboard('{{ $transaction->tx_hash }}')" 
                                            class="ml-2 text-indigo-600 hover:text-indigo-700">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Type</p>
                                <p class="mt-1">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $transaction->type === 'send' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                        {{ ucfirst($transaction->type) }}
                                    </span>
                                </p>
                            </div>

                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Blockchain</p>
                                <p class="font-medium mt-1">{{ $supportedChains[$transaction->chain]['name'] }}</p>
                            </div>

                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Date & Time</p>
                                <p class="font-medium mt-1">{{ $transaction->created_at->format('M d, Y H:i:s') }}</p>
                            </div>

                            @if($transaction->metadata && isset($transaction->metadata['memo']))
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Memo</p>
                                    <p class="mt-1">{{ $transaction->metadata['memo'] }}</p>
                                </div>
                            @endif
                        </div>

                        <!-- Amount Info -->
                        <div class="space-y-4">
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400">Amount</p>
                                <p class="text-2xl font-bold mt-1">
                                    {{ $transaction->type === 'send' ? '-' : '+' }}{{ number_format($transaction->amount, 8) }} {{ $supportedChains[$transaction->chain]['symbol'] }}
                                </p>
                                @php
                                    $usdRate = $supportedChains[$transaction->chain]['symbol'] === 'BTC' ? 30000 : 
                                              ($supportedChains[$transaction->chain]['symbol'] === 'ETH' ? 2000 : 1);
                                    $usdValue = $transaction->amount * $usdRate;
                                @endphp
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    ≈ ${{ number_format($usdValue, 2) }} USD at time of transaction
                                </p>
                            </div>

                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Network Fee</p>
                                <p class="font-medium mt-1">{{ number_format($transaction->fee, 8) }} {{ $supportedChains[$transaction->chain]['symbol'] }}</p>
                            </div>

                            @if(isset($blockchainData['block_height']))
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Block Height</p>
                                    <p class="font-medium mt-1">{{ number_format($blockchainData['block_height']) }}</p>
                                </div>
                            @endif

                            @if(isset($blockchainData['gas_used']) && $transaction->chain === 'ethereum')
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Gas Used</p>
                                    <p class="font-medium mt-1">{{ number_format($blockchainData['gas_used']) }}</p>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- From/To Addresses -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Transaction Flow</h3>
                    
                    <div class="space-y-4">
                        <!-- From Address -->
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">From</p>
                            <div class="flex items-center justify-between">
                                <p class="font-mono text-sm break-all">{{ $transaction->from_address }}</p>
                                <button onclick="copyToClipboard('{{ $transaction->from_address }}')" 
                                        class="ml-2 text-indigo-600 hover:text-indigo-700">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                </button>
                            </div>
                            @if($transaction->address->address === $transaction->from_address)
                                <p class="text-xs text-indigo-600 mt-1">Your Address ({{ $transaction->address->label }})</p>
                            @endif
                        </div>

                        <!-- Arrow -->
                        <div class="flex justify-center">
                            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                            </svg>
                        </div>

                        <!-- To Address -->
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <p class="text-sm text-gray-600 dark:text-gray-400 mb-1">To</p>
                            <div class="flex items-center justify-between">
                                <p class="font-mono text-sm break-all">{{ $transaction->to_address }}</p>
                                <button onclick="copyToClipboard('{{ $transaction->to_address }}')" 
                                        class="ml-2 text-indigo-600 hover:text-indigo-700">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                    </svg>
                                </button>
                            </div>
                            @if($transaction->address->address === $transaction->to_address)
                                <p class="text-xs text-indigo-600 mt-1">Your Address ({{ $transaction->address->label }})</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <div class="flex justify-between items-center">
                        <a href="{{ $supportedChains[$transaction->chain]['explorer'] }}/tx/{{ $transaction->tx_hash }}" 
                           target="_blank"
                           class="inline-flex items-center px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"></path>
                            </svg>
                            View on Block Explorer
                        </a>
                        
                        <a href="{{ route('wallet.blockchain.show', $transaction->address->uuid) }}" 
                           class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                            Back to Address
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // Show success notification
                const notification = document.createElement('div');
                notification.className = 'fixed bottom-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
                notification.textContent = 'Copied to clipboard!';
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.remove();
                }, 3000);
            }, function(err) {
                console.error('Could not copy text: ', err);
            });
        }

        // Auto-refresh for pending transactions
        @if($transaction->status === 'pending')
            setInterval(function() {
                window.location.reload();
            }, 30000); // Refresh every 30 seconds
        @endif
    </script>
    @endpush
</x-app-layout>