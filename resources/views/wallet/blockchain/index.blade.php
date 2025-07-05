<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Blockchain Wallets') }}
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('wallet.blockchain.create') }}" 
                   class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                    Generate New Address
                </a>
                <a href="{{ route('wallet.blockchain.backup') }}" 
                   class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                    Export Backup
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Total Blockchain Balance -->
            <div class="bg-gradient-to-r from-purple-600 to-indigo-600 rounded-lg shadow-lg p-6 mb-6 text-white">
                <h3 class="text-lg font-semibold mb-4">Total Blockchain Portfolio</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    @php
                        $totalUSD = 0;
                        foreach ($balances as $addressId => $balance) {
                            if (!isset($balance['error'])) {
                                $address = $addresses->firstWhere('uuid', $addressId);
                                $chain = $supportedChains[$address->chain];
                                // Mock USD conversion - in production, use real exchange rates
                                $usdValue = $balance['balance'] * ($chain['symbol'] === 'BTC' ? 30000 : ($chain['symbol'] === 'ETH' ? 2000 : 1));
                                $totalUSD += $usdValue;
                            }
                        }
                    @endphp
                    <div class="md:col-span-2">
                        <p class="text-sm opacity-90">Total Value (USD)</p>
                        <p class="text-4xl font-bold">${{ number_format($totalUSD, 2) }}</p>
                    </div>
                    <div>
                        <p class="text-sm opacity-90">Active Addresses</p>
                        <p class="text-2xl font-bold">{{ $addresses->where('is_active', true)->count() }}</p>
                    </div>
                    <div>
                        <p class="text-sm opacity-90">Total Transactions</p>
                        <p class="text-2xl font-bold">{{ $recentTransactions->count() }}</p>
                    </div>
                </div>
            </div>

            <!-- Blockchain Addresses -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Your Blockchain Addresses</h3>
                    
                    @if($addresses->isEmpty())
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                            <p class="mt-2 text-gray-600 dark:text-gray-400">No blockchain addresses yet</p>
                            <a href="{{ route('wallet.blockchain.create') }}" 
                               class="mt-4 inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                                Generate Your First Address
                            </a>
                        </div>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($addresses as $address)
                                @php
                                    $balance = $balances[$address->uuid] ?? ['balance' => 0, 'error' => true];
                                    $chain = $supportedChains[$address->chain];
                                @endphp
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-lg transition">
                                    <div class="flex items-start justify-between mb-3">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center mr-3">
                                                @if($address->chain === 'bitcoin')
                                                    <span class="text-orange-500 font-bold">₿</span>
                                                @elseif($address->chain === 'ethereum')
                                                    <span class="text-blue-500 font-bold">Ξ</span>
                                                @elseif($address->chain === 'polygon')
                                                    <span class="text-purple-500 font-bold">M</span>
                                                @else
                                                    <span class="text-yellow-500 font-bold">B</span>
                                                @endif
                                            </div>
                                            <div>
                                                <h4 class="font-semibold">{{ $address->label }}</h4>
                                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $chain['name'] }}</p>
                                            </div>
                                        </div>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $address->is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                            {{ $address->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <p class="text-xs text-gray-600 dark:text-gray-400">Address</p>
                                        <p class="font-mono text-sm break-all">{{ substr($address->address, 0, 16) }}...{{ substr($address->address, -16) }}</p>
                                    </div>
                                    
                                    <div class="mb-4">
                                        <p class="text-xs text-gray-600 dark:text-gray-400">Balance</p>
                                        @if(isset($balance['error']))
                                            <p class="text-sm text-red-600">Error loading balance</p>
                                        @else
                                            <p class="text-lg font-semibold">
                                                {{ number_format($balance['balance'], 8) }} {{ $chain['symbol'] }}
                                            </p>
                                            @if($balance['pending'] > 0)
                                                <p class="text-xs text-yellow-600">
                                                    +{{ number_format($balance['pending'], 8) }} pending
                                                </p>
                                            @endif
                                        @endif
                                    </div>
                                    
                                    <div class="flex space-x-2">
                                        <a href="{{ route('wallet.blockchain.show', $address->uuid) }}" 
                                           class="flex-1 text-center px-3 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition text-sm">
                                            View
                                        </a>
                                        <a href="{{ route('wallet.blockchain.send', $address->uuid) }}" 
                                           class="flex-1 text-center px-3 py-1 bg-indigo-600 text-white rounded hover:bg-indigo-700 transition text-sm">
                                            Send
                                        </a>
                                        <button onclick="copyAddress('{{ $address->address }}')" 
                                                class="flex-1 px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 transition text-sm">
                                            Copy
                                        </button>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- Recent Transactions -->
            @if($recentTransactions->isNotEmpty())
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Recent Blockchain Transactions</h3>
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
                                            Chain
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Amount
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Action
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($recentTransactions as $transaction)
                                        @php
                                            $chain = $supportedChains[$transaction->chain];
                                        @endphp
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                {{ $transaction->created_at->format('M d, Y H:i') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $transaction->type === 'send' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' }}">
                                                    {{ ucfirst($transaction->type) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                {{ $chain['name'] }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                {{ $transaction->type === 'send' ? '-' : '+' }}{{ number_format($transaction->amount, 8) }} {{ $chain['symbol'] }}
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
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                                <a href="{{ route('wallet.blockchain.transaction', $transaction->uuid) }}" 
                                                   class="text-indigo-600 hover:text-indigo-900">View</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    @push('scripts')
    <script>
        function copyAddress(address) {
            navigator.clipboard.writeText(address).then(function() {
                // Show success message
                alert('Address copied to clipboard!');
            }, function(err) {
                console.error('Could not copy address: ', err);
            });
        }
    </script>
    @endpush
</x-app-layout>