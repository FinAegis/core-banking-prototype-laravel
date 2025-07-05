<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('External Exchange Integration') }}
            </h2>
            <div class="flex space-x-2">
                <a href="{{ route('exchange.external.arbitrage') }}" 
                   class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                    Arbitrage Opportunities
                </a>
                <a href="{{ route('exchange.external.price-alignment') }}" 
                   class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                    Price Alignment
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Connected Exchanges -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">Connected Exchanges</h3>
                        <button onclick="showConnectModal()" 
                                class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition text-sm">
                            Connect Exchange
                        </button>
                    </div>

                    @if($connectedExchanges->isEmpty())
                        <div class="text-center py-8">
                            <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                            </svg>
                            <p class="mt-2 text-gray-600 dark:text-gray-400">No exchanges connected yet</p>
                            <button onclick="showConnectModal()" 
                                    class="mt-4 inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                                Connect Your First Exchange
                            </button>
                        </div>
                    @else
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            @foreach($connectedExchanges as $exchange)
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center">
                                            <div class="w-10 h-10 rounded-full bg-gray-100 dark:bg-gray-700 flex items-center justify-center mr-3">
                                                @if($exchange['exchange'] === 'binance')
                                                    <span class="text-yellow-500 font-bold">B</span>
                                                @elseif($exchange['exchange'] === 'kraken')
                                                    <span class="text-purple-500 font-bold">K</span>
                                                @else
                                                    <span class="text-blue-500 font-bold">C</span>
                                                @endif
                                            </div>
                                            <div>
                                                <h4 class="font-semibold">{{ ucfirst($exchange['exchange']) }}</h4>
                                                @if($exchange['testnet'])
                                                    <span class="text-xs text-yellow-600 dark:text-yellow-400">Testnet</span>
                                                @endif
                                            </div>
                                        </div>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $exchange['status'] === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' }}">
                                            {{ ucfirst($exchange['status']) }}
                                        </span>
                                    </div>
                                    
                                    <div class="space-y-2 text-sm">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600 dark:text-gray-400">Connected</span>
                                            <span>{{ \Carbon\Carbon::parse($exchange['connected_at'])->diffForHumans() }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600 dark:text-gray-400">Last Sync</span>
                                            <span>{{ $exchange['last_sync'] ? \Carbon\Carbon::parse($exchange['last_sync'])->diffForHumans() : 'Never' }}</span>
                                        </div>
                                    </div>
                                    
                                    <div class="mt-4 flex space-x-2">
                                        <button onclick="syncExchange('{{ $exchange['exchange'] }}')" 
                                                class="flex-1 px-3 py-1 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded hover:bg-gray-200 dark:hover:bg-gray-600 transition text-sm">
                                            Sync
                                        </button>
                                        <form method="POST" action="{{ route('exchange.external.disconnect', $exchange['exchange']) }}" class="flex-1">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" 
                                                    class="w-full px-3 py-1 bg-red-600 text-white rounded hover:bg-red-700 transition text-sm"
                                                    onclick="return confirm('Disconnect from {{ ucfirst($exchange['exchange']) }}?')">
                                                Disconnect
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <!-- Price Comparisons -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Price Comparisons</h3>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Pair
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Internal
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Binance
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Kraken
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Coinbase
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Average
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Spread
                                    </th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                @foreach($priceComparisons as $pair => $prices)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            {{ $pair }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            @if($prices['internal'])
                                                ${{ number_format($prices['internal'], 2) }}
                                                @if($prices['average'] && abs($prices['internal'] - $prices['average']) / $prices['average'] > 0.01)
                                                    <span class="text-xs text-red-600">
                                                        ({{ number_format(($prices['internal'] - $prices['average']) / $prices['average'] * 100, 1) }}%)
                                                    </span>
                                                @endif
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            {{ $prices['binance'] ? '$' . number_format($prices['binance'], 2) : '-' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            {{ $prices['kraken'] ? '$' . number_format($prices['kraken'], 2) : '-' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            {{ $prices['coinbase'] ? '$' . number_format($prices['coinbase'], 2) : '-' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            {{ $prices['average'] ? '$' . number_format($prices['average'], 2) : '-' }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            @if($prices['spread'])
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $prices['spread'] > 1 ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' }}">
                                                    {{ number_format($prices['spread'], 2) }}%
                                                </span>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Arbitrage Opportunities -->
            @if($arbitrageOpportunities->isNotEmpty())
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold">Active Arbitrage Opportunities</h3>
                            <a href="{{ route('exchange.external.arbitrage') }}" 
                               class="text-indigo-600 hover:text-indigo-700 text-sm font-medium">
                                View All →
                            </a>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            @foreach($arbitrageOpportunities->take(4) as $opportunity)
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 {{ $opportunity['profit_percentage'] > 1 ? 'border-green-500' : '' }}">
                                    <div class="flex justify-between items-start mb-2">
                                        <h4 class="font-medium">{{ $opportunity['pair'] }}</h4>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            +{{ number_format($opportunity['profit_percentage'], 2) }}%
                                        </span>
                                    </div>
                                    <div class="text-sm space-y-1">
                                        <div class="flex justify-between">
                                            <span class="text-gray-600 dark:text-gray-400">Buy at {{ $opportunity['buy_exchange'] }}</span>
                                            <span>${{ number_format($opportunity['buy_price'], 2) }}</span>
                                        </div>
                                        <div class="flex justify-between">
                                            <span class="text-gray-600 dark:text-gray-400">Sell at {{ $opportunity['sell_exchange'] }}</span>
                                            <span>${{ number_format($opportunity['sell_price'], 2) }}</span>
                                        </div>
                                        <div class="flex justify-between font-medium text-green-600">
                                            <span>Profit per unit</span>
                                            <span>${{ number_format($opportunity['profit_per_unit'], 2) }}</span>
                                        </div>
                                    </div>
                                    <a href="{{ route('exchange.external.arbitrage') }}?opportunity={{ $opportunity['id'] }}" 
                                       class="mt-3 w-full inline-flex justify-center items-center px-3 py-1 bg-green-600 text-white rounded hover:bg-green-700 transition text-sm">
                                        Execute Trade
                                    </a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            <!-- External Balances -->
            @if(!empty($externalBalances))
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">External Exchange Balances</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            @foreach($externalBalances as $exchange => $balances)
                                <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                    <h4 class="font-medium mb-3">{{ ucfirst($exchange) }}</h4>
                                    
                                    @if(isset($balances['error']))
                                        <p class="text-sm text-red-600">Error loading balances</p>
                                    @else
                                        <div class="space-y-2">
                                            @foreach($balances as $asset => $balance)
                                                @if($balance['total'] > 0)
                                                    <div class="flex justify-between text-sm">
                                                        <span>{{ $asset }}</span>
                                                        <div class="text-right">
                                                            <span class="font-medium">{{ number_format($balance['total'], 8) }}</span>
                                                            @if(isset($balance['usd_value']))
                                                                <p class="text-xs text-gray-600 dark:text-gray-400">
                                                                    ≈ ${{ number_format($balance['usd_value'], 2) }}
                                                                </p>
                                                            @endif
                                                        </div>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <!-- Connect Exchange Modal -->
    <div id="connectModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white dark:bg-gray-800">
            <h3 class="text-lg font-bold text-gray-900 dark:text-gray-100 mb-4">Connect External Exchange</h3>
            
            <form method="POST" action="{{ route('exchange.external.connect') }}">
                @csrf
                
                <div class="mb-4">
                    <x-label for="exchange" value="{{ __('Select Exchange') }}" />
                    <select id="exchange" name="exchange" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm" required>
                        <option value="">Choose an exchange</option>
                        <option value="binance">Binance</option>
                        <option value="kraken">Kraken</option>
                        <option value="coinbase">Coinbase</option>
                    </select>
                </div>
                
                <div class="mb-4">
                    <x-label for="api_key" value="{{ __('API Key') }}" />
                    <x-input id="api_key" type="text" name="api_key" class="mt-1 block w-full" required />
                </div>
                
                <div class="mb-4">
                    <x-label for="api_secret" value="{{ __('API Secret') }}" />
                    <x-input id="api_secret" type="password" name="api_secret" class="mt-1 block w-full" required />
                </div>
                
                <div class="mb-4">
                    <label class="flex items-center">
                        <input type="checkbox" name="testnet" value="1" class="rounded border-gray-300">
                        <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">Use Testnet (if available)</span>
                    </label>
                </div>
                
                <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg p-3 mb-4">
                    <p class="text-sm text-amber-700 dark:text-amber-300">
                        <strong>Security Notice:</strong> Only create API keys with trading permissions. Never enable withdrawal permissions.
                    </p>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <button type="button" onclick="hideConnectModal()" 
                            class="px-4 py-2 bg-gray-300 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-600 transition">
                        Cancel
                    </button>
                    <button type="submit" 
                            class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                        Connect
                    </button>
                </div>
            </form>
        </div>
    </div>

    @push('scripts')
    <script>
        function showConnectModal() {
            document.getElementById('connectModal').classList.remove('hidden');
        }
        
        function hideConnectModal() {
            document.getElementById('connectModal').classList.add('hidden');
        }
        
        function syncExchange(exchange) {
            // Implement sync functionality
            alert('Syncing ' + exchange + '...');
        }
        
        // Auto-refresh prices every 30 seconds
        setInterval(function() {
            if (document.visibilityState === 'visible') {
                window.location.reload();
            }
        }, 30000);
    </script>
    @endpush
</x-app-layout>