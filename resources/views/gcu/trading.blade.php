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
                <p class="font-semibold">This is a simulated demo. The unit has no real value and is not an offered token.</p>
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
                                <p class="text-gray-500 dark:text-gray-400">The price chart is not available yet.</p>
                            </div>
                            <div class="mt-4 grid grid-cols-4 gap-4 text-sm">
                                <div>
                                    <p class="text-gray-500 dark:text-gray-400">Illustrative demo value</p>
                                    <p class="font-semibold">{{ $currentValue ? '$' . number_format($currentValue->value, 4) : 'Not available yet' }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Trades -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-4">Your Simulated Conversions (demo)</h3>
                            @if($recentConversions->count() > 0)
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                        <thead>
                                            <tr>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Time</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Type</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Amount</th>
                                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                            @foreach($recentConversions as $conversion)
                                                @php($isBuy = $conversion->type === 'transfer_in')
                                                <tr>
                                                    <td class="px-3 py-2 text-sm">{{ $conversion->created_at?->format('Y-m-d H:i') }}</td>
                                                    <td class="px-3 py-2 text-sm">
                                                        <span class="{{ $isBuy ? 'text-green-600' : 'text-red-600' }}">
                                                            {{ $isBuy ? 'Buy' : 'Sell' }}
                                                        </span>
                                                    </td>
                                                    <td class="px-3 py-2 text-sm">{{ number_format(abs($conversion->amount) / 100, 2) }} GCU</td>
                                                    <td class="px-3 py-2 text-sm">
                                                        <span class="px-2 py-1 text-xs rounded-full bg-green-100 text-green-800">
                                                            {{ ucfirst($conversion->status) }}
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
                            <form id="buyForm" data-endpoint="{{ url('/api/v2/gcu/buy') }}">
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Amount (USD)
                                        </label>
                                        <input type="number" name="amount" step="0.01" min="100" required
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
                                        <p>Illustrative demo rate: 1 GCU = {{ $currentValue ? '$' . number_format($currentValue->value, 4) : 'not available yet' }}</p>
                                        <p>The demo conversion fee is 1%. The smallest purchase is 100 USD.</p>
                                    </div>

                                    <p data-role="message" class="hidden text-sm text-red-600" role="alert"></p>

                                    <button type="submit" class="w-full py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                                        Buy (demo)
                                    </button>
                                </div>
                            </form>

                            <!-- Sell Form (hidden by default) -->
                            <form id="sellForm" data-endpoint="{{ url('/api/v2/gcu/sell') }}" class="hidden">
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                            Amount (GCU)
                                        </label>
                                        <input type="number" name="amount" step="0.01" min="10" required
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-indigo-500 focus:border-indigo-500"
                                               placeholder="10.00">
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
                                        <p>Illustrative demo rate: 1 GCU = {{ $currentValue ? '$' . number_format($currentValue->value, 4) : 'not available yet' }}</p>
                                        <p>The demo conversion fee is 1%. The smallest sale is 10 GCU.</p>
                                    </div>

                                    <p data-role="message" class="hidden text-sm text-red-600" role="alert"></p>

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
        
        // Estimate with the same 1% fee as /api/v2/gcu/quote. The API books
        // the conversion and returns the exact amounts.
        const gcuValueUsd = {{ $currentValue ? (float) $currentValue->value : 'null' }};
        const fee = 0.01;

        document.querySelector('#buyForm input[name="amount"]').addEventListener('input', function(e) {
            const usdAmount = parseFloat(e.target.value) || 0;
            document.querySelector('#buyForm input[readonly]').value = gcuValueUsd
                ? ((usdAmount * (1 - fee)) / gcuValueUsd).toFixed(2)
                : '';
        });

        document.querySelector('#sellForm input[name="amount"]').addEventListener('input', function(e) {
            const gcuAmount = parseFloat(e.target.value) || 0;
            document.querySelector('#sellForm input[readonly]').value = gcuValueUsd
                ? (gcuAmount * gcuValueUsd * (1 - fee)).toFixed(2)
                : '';
        });

        // Both forms call the GCU demo API with the signed-in web session.
        document.querySelectorAll('#buyForm, #sellForm').forEach(function(form) {
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                const message = form.querySelector('[data-role="message"]');
                const button = form.querySelector('button[type="submit"]');
                const fallback = 'The demo conversion did not go through. Please try again.';
                message.classList.add('hidden');
                button.disabled = true;

                try {
                    const response = await fetch(form.dataset.endpoint, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                        },
                        body: JSON.stringify({
                            amount: form.querySelector('input[name="amount"]').value,
                            currency: 'USD',
                        }),
                    });

                    if (response.ok) {
                        window.location.reload();
                        return;
                    }

                    const body = await response.json().catch(function() { return {}; });
                    message.textContent = body.message || fallback;
                } catch (error) {
                    message.textContent = fallback;
                }

                message.classList.remove('hidden');
                button.disabled = false;
            });
        });
    </script>
    @endpush
</x-app-layout>