<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ $address->label }}
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('wallet.blockchain.send', $address->uuid) }}" 
                   class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                    Send {{ $supportedChains[$address->chain]['symbol'] }}
                </a>
                <button onclick="copyAddress('{{ $address->address }}')" 
                        class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                    Copy Address
                </button>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Address Details -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Address Info -->
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Address Information</h3>
                            <div class="space-y-3">
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Blockchain</p>
                                    <p class="font-medium">{{ $supportedChains[$address->chain]['name'] }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Address</p>
                                    <p class="font-mono text-sm break-all">{{ $address->address }}</p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Status</p>
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $address->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                        {{ $address->is_active ? 'Active' : 'Inactive' }}
                                    </span>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400">Created</p>
                                    <p class="font-medium">{{ $address->created_at->format('M d, Y H:i') }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Balance Info -->
                        <div>
                            <h3 class="text-lg font-semibold mb-4">Balance</h3>
                            @if(isset($balance['error']))
                                <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                                    <p class="text-red-600 dark:text-red-400">Error loading balance</p>
                                </div>
                            @else
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                    <div class="mb-4">
                                        <p class="text-sm text-gray-600 dark:text-gray-400">Available Balance</p>
                                        <p class="text-2xl font-bold">
                                            {{ number_format($balance['available'], 8) }} {{ $supportedChains[$address->chain]['symbol'] }}
                                        </p>
                                        @php
                                            // Mock USD conversion
                                            $usdRate = $supportedChains[$address->chain]['symbol'] === 'BTC' ? 30000 : 
                                                      ($supportedChains[$address->chain]['symbol'] === 'ETH' ? 2000 : 1);
                                            $usdValue = $balance['available'] * $usdRate;
                                        @endphp
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            ≈ ${{ number_format($usdValue, 2) }} USD
                                        </p>
                                    </div>
                                    @if($balance['pending'] > 0)
                                        <div>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">Pending</p>
                                            <p class="text-lg font-semibold text-yellow-600">
                                                +{{ number_format($balance['pending'], 8) }} {{ $supportedChains[$address->chain]['symbol'] }}
                                            </p>
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </div>

                    <!-- QR Code -->
                    <div class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                        <h4 class="text-md font-semibold mb-3">Receive {{ $supportedChains[$address->chain]['symbol'] }}</h4>
                        <div class="flex items-center space-x-4">
                            <div class="bg-white p-4 rounded-lg border border-gray-200">
                                <!-- QR Code placeholder - in production, generate actual QR code -->
                                <div class="w-48 h-48 bg-gray-100 flex items-center justify-center">
                                    <span class="text-gray-400">QR Code</span>
                                </div>
                            </div>
                            <div class="flex-1">
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    Share this address to receive {{ $supportedChains[$address->chain]['symbol'] }}
                                </p>
                                <div class="bg-gray-100 dark:bg-gray-700 rounded-lg p-3">
                                    <p class="font-mono text-sm break-all">{{ $address->address }}</p>
                                </div>
                                <button onclick="copyAddress('{{ $address->address }}')" 
                                        class="mt-2 text-indigo-600 hover:text-indigo-700 text-sm font-medium">
                                    Copy to clipboard
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Transaction Statistics -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Transaction Statistics</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Total Transactions</p>
                            <p class="text-2xl font-bold">{{ $statistics['total_transactions'] }}</p>
                        </div>
                        <div class="bg-green-50 dark:bg-green-900/20 rounded-lg p-4">
                            <p class="text-sm text-green-600 dark:text-green-400">Total Received</p>
                            <p class="text-xl font-bold text-green-700 dark:text-green-300">
                                {{ number_format($statistics['total_received'], 8) }}
                            </p>
                        </div>
                        <div class="bg-red-50 dark:bg-red-900/20 rounded-lg p-4">
                            <p class="text-sm text-red-600 dark:text-red-400">Total Sent</p>
                            <p class="text-xl font-bold text-red-700 dark:text-red-300">
                                {{ number_format($statistics['total_sent'], 8) }}
                            </p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Total Fees</p>
                            <p class="text-xl font-bold">{{ number_format($statistics['total_fees'], 8) }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Recent Transactions</h3>
                    
                    @if($transactions->isEmpty())
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            <p class="mt-2 text-gray-600 dark:text-gray-400">No transactions yet</p>
                        </div>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Date
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Type
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Amount
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Hash
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($transactions as $transaction)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                {{ $transaction->created_at->format('M d, H:i') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $transaction->type === 'send' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                                    {{ ucfirst($transaction->type) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                {{ $transaction->type === 'send' ? '-' : '+' }}{{ number_format($transaction->amount, 8) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if($transaction->status === 'confirmed')
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                        Confirmed
                                                    </span>
                                                @elseif($transaction->status === 'pending')
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                        Pending
                                                    </span>
                                                @else
                                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                        Failed
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono">
                                                {{ substr($transaction->tx_hash, 0, 10) }}...
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                                <a href="{{ route('wallet.blockchain.transaction', $transaction->uuid) }}" 
                                                   class="text-indigo-600 hover:text-indigo-900">View</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <div class="mt-4">
                            {{ $transactions->links() }}
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function copyAddress(address) {
            navigator.clipboard.writeText(address).then(function() {
                // Show success notification
                const notification = document.createElement('div');
                notification.className = 'fixed bottom-4 right-4 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg z-50';
                notification.textContent = 'Address copied to clipboard!';
                document.body.appendChild(notification);
                
                setTimeout(() => {
                    notification.remove();
                }, 3000);
            }, function(err) {
                console.error('Could not copy address: ', err);
            });
        }
    </script>
    @endpush
</x-app-layout>