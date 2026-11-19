<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('GCU Demo Conversion (simulated)') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            {{-- F4 GCU demo notice (see docs/REGULATORY-CLAIMS.md) --}}
            <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-200" role="note">
                <p class="font-semibold">Simulated demo — no real value; not an offered token.</p>
                <p class="mt-1">The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under MiCA (Title III); no such authorisation is held.</p>
            </div>

            <!-- Trading Interface -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column - Price Chart and Order Book -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Price Chart -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4">GCU/USD Illustrative Demo Value</h3>
                            <div class="bg-gray-100 dark:bg-gray-700 rounded h-64 flex items-center justify-center">
                                <p class="text-gray-500 dark:text-gray-400">Price chart is not yet available</p>
                            </div>
                            <div class="mt-4 grid grid-cols-4 gap-4 text-sm">
                                <div>
                                    <p class="text-gray-500 dark:text-gray-400">Illustrative demo value</p>
                                    <p class="font-semibold">${{ $currentPrice ? number_format($currentPrice->price_usd, 4) : '1.1000' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Trades -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4">Your Simulated Conversions (demo)</h3>
                            @if($recentTrades->count() > 0)
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead>
                                            <tr>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Time</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Amount</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Price</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                            @foreach($recentTrades as $trade)
                                                <tr>
                                                    <td class="px-3 py-2 text-sm">{{ \Carbon\Carbon::parse($trade->created_at)->format('H:i:s') }}</td>
                                                    <td class="px-3 py-2 text-sm">
                                                        <span class="text-{{ $trade->amount > 0 ? 'green' : 'red' }}-600">
                                                            {{ $trade->amount > 0 ? 'Buy' : 'Sell' }}
                                                        </span>
                                                    </td>
                                                    <td class="px-3 py-2 text-sm">{{ number_format(abs($trade->amount) / 100, 2) }} GCU</td>
                                                    <td class="px-3 py-2 text-sm">$1.1000</td>
                                                    <td class="px-3 py-2 text-sm">
                                                        <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                                            {{ ucfirst($trade->status) }}
                                                        </span>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-gray-500 dark:text-gray-400">No simulated conversions yet</p>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Right Column - Trading Form -->
                <div class="lg:col-span-1 space-y-6">
                    <!-- Balance Overview -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4">Your Demo Balances (simulated)</h3>
                            <div class="space-y-4">
                                <div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">GCU demo balance</span>
                                        <span class="font-semibold">Ǥ{{ number_format($gcuBalance / 100, 2) }}</span>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between items-center">
                                        <span class="text-sm text-gray-600 dark:text-gray-400">USD demo balance</span>
                                        <span class="font-semibold">${{ number_format($usdBalance / 100, 2) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Trading Form -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4">GCU demo conversion (simulated)</h3>
                            
                            <!-- Tab Selection -->
                            <div class="flex mb-4">
                                <button type="button" class="flex-1 py-2 px-4 bg-green-600 text-white rounded-l-lg focus:outline-none" id="buyTab">
                                    Buy (demo)
                                </button>
                                <button type="button" class="flex-1 py-2 px-4 bg-gray-200 text-gray-700 rounded-r-lg focus:outline-none" id="sellTab">
                                    Sell (demo)
                                </button>
                            </div>

                            <!-- Buy Form -->
                            <form id="buyForm" action="{{ route('api.exchange') }}" method="POST">
                                @csrf
                                <input type="hidden" name="from_currency" value="USD">
                                <input type="hidden" name="to_currency" value="GCU">
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Amount (USD)
                                        </label>
                                        <input type="number" name="amount" step="0.01" min="0.01" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                               placeholder="100.00">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            You will receive (GCU, simulated)
                                        </label>
                                        <input type="text" readonly 
                                               class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-lg"
                                               placeholder="0.00">
                                    </div>
                                    
                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                        <p>Illustrative demo rate: 1 GCU = ${{ $currentPrice ? number_format($currentPrice->price_usd, 4) : '1.1000' }}</p>
                                    </div>
                                    
                                    <button type="submit" class="w-full py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                                        Buy (demo)
                                    </button>
                                </div>
                            </form>

                            <!-- Sell Form (hidden by default) -->
                            <form id="sellForm" action="{{ route('api.exchange') }}" method="POST" class="hidden">
                                @csrf
                                <input type="hidden" name="from_currency" value="GCU">
                                <input type="hidden" name="to_currency" value="USD">
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Amount (GCU)
                                        </label>
                                        <input type="number" name="amount" step="0.01" min="0.01" 
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                               placeholder="100.00">
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            You will receive (USD, simulated)
                                        </label>
                                        <input type="text" readonly 
                                               class="w-full px-3 py-2 bg-gray-100 border border-gray-300 rounded-lg"
                                               placeholder="0.00">
                                    </div>
                                    
                                    <div class="text-sm text-gray-600 dark:text-gray-400">
                                        <p>Illustrative demo rate: 1 GCU = ${{ $currentPrice ? number_format($currentPrice->price_usd, 4) : '1.1000' }}</p>
                                    </div>
                                    
                                    <button type="submit" class="w-full py-3 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                                        Sell (demo)
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // Tab switching
        document.getElementById('buyTab').addEventListener('click', function() {
            document.getElementById('buyForm').classList.remove('hidden');
            document.getElementById('sellForm').classList.add('hidden');
            this.classList.add('bg-green-600', 'text-white');
            this.classList.remove('bg-gray-200', 'text-gray-700');
            document.getElementById('sellTab').classList.remove('bg-red-600', 'text-white');
            document.getElementById('sellTab').classList.add('bg-gray-200', 'text-gray-700');
        });
        
        document.getElementById('sellTab').addEventListener('click', function() {
            document.getElementById('sellForm').classList.remove('hidden');
            document.getElementById('buyForm').classList.add('hidden');
            this.classList.add('bg-red-600', 'text-white');
            this.classList.remove('bg-gray-200', 'text-gray-700');
            document.getElementById('buyTab').classList.remove('bg-green-600', 'text-white');
            document.getElementById('buyTab').classList.add('bg-gray-200', 'text-gray-700');
        });
        
        // Calculate exchange amounts
        const exchangeRate = {{ $currentPrice ? $currentPrice->price_usd : 1.1 }};
        const fee = 0.001; // 0.1%
        
        document.querySelector('#buyForm input[name="amount"]').addEventListener('input', function(e) {
            const usdAmount = parseFloat(e.target.value) || 0;
            const gcuAmount = (usdAmount / exchangeRate) * (1 - fee);
            document.querySelector('#buyForm input[readonly]').value = gcuAmount.toFixed(2);
        });
        
        document.querySelector('#sellForm input[name="amount"]').addEventListener('input', function(e) {
            const gcuAmount = parseFloat(e.target.value) || 0;
            const usdAmount = (gcuAmount * exchangeRate) * (1 - fee);
            document.querySelector('#sellForm input[readonly]').value = usdAmount.toFixed(2);
        });
    </script>
    @endpush
</x-app-layout>