<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Price Alignment') }}
            </h2>
            <a href="{{ route('exchange.external.index') }}" 
               class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                Back to External Exchanges
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Alignment Settings -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Price Alignment Settings</h3>
                    
                    <form method="POST" action="{{ route('exchange.external.price-alignment.update') }}">
                        @csrf
                        @method('PUT')
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <div class="mb-4">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="auto_align" value="1" 
                                               class="rounded border-gray-300 text-indigo-600"
                                               {{ old('auto_align', false) ? 'checked' : '' }}>
                                        <span class="ml-2 text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Enable Automatic Price Alignment
                                        </span>
                                    </label>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        Automatically adjust prices to match market averages
                                    </p>
                                </div>
                                
                                <div class="mb-4">
                                    <x-label for="max_spread" value="{{ __('Maximum Spread (%)') }}" />
                                    <x-input id="max_spread" type="number" name="max_spread" 
                                             class="mt-1 block w-full" value="1.5" 
                                             step="0.1" min="0" max="10" required />
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        Maximum allowed deviation from market average
                                    </p>
                                </div>
                                
                                <div class="mb-4">
                                    <x-label for="update_frequency" value="{{ __('Update Frequency (seconds)') }}" />
                                    <select id="update_frequency" name="update_frequency" 
                                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm">
                                        <option value="60">1 minute</option>
                                        <option value="300" selected>5 minutes</option>
                                        <option value="900">15 minutes</option>
                                        <option value="1800">30 minutes</option>
                                        <option value="3600">1 hour</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div>
                                <x-label value="{{ __('Reference Exchanges') }}" />
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">
                                    Select exchanges to use for price reference
                                </p>
                                <div class="space-y-2">
                                    <label class="flex items-center">
                                        <input type="checkbox" name="exchanges[]" value="binance" 
                                               class="rounded border-gray-300" checked>
                                        <span class="ml-2 text-sm">Binance</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="exchanges[]" value="kraken" 
                                               class="rounded border-gray-300" checked>
                                        <span class="ml-2 text-sm">Kraken</span>
                                    </label>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="exchanges[]" value="coinbase" 
                                               class="rounded border-gray-300" checked>
                                        <span class="ml-2 text-sm">Coinbase</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-6">
                            <button type="submit" 
                                    class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                                Save Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Price Discrepancies -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Current Price Discrepancies</h3>
                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead>
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Pair
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Our Price
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Market Avg
                                    </th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Deviation
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
                                @foreach($priceDiscrepancies as $pair => $discrepancy)
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            {{ $pair }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            ${{ number_format($discrepancy['our_price'], 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            ${{ number_format($discrepancy['market_average'], 2) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            <span class="{{ abs($discrepancy['deviation_percentage']) > 1 ? 'text-red-600' : 'text-green-600' }}">
                                                {{ $discrepancy['deviation_percentage'] > 0 ? '+' : '' }}{{ number_format($discrepancy['deviation_percentage'], 2) }}%
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if(abs($discrepancy['deviation_percentage']) > 2)
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                    High Deviation
                                                </span>
                                            @elseif(abs($discrepancy['deviation_percentage']) > 1)
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                    Moderate
                                                </span>
                                            @else
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                    Aligned
                                                </span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                            @if(abs($discrepancy['deviation_percentage']) > 1)
                                                <button onclick="alignPrice('{{ $pair }}', {{ $discrepancy['market_average'] }})" 
                                                        class="text-indigo-600 hover:text-indigo-900">
                                                    Align Now
                                                </button>
                                            @else
                                                <span class="text-gray-400">No action needed</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recommended Adjustments -->
            @if(count($recommendedAdjustments) > 0)
                <div class="bg-amber-50 dark:bg-amber-900/20 border border-amber-200 dark:border-amber-800 rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4 text-amber-800 dark:text-amber-200">
                            Recommended Price Adjustments
                        </h3>
                        
                        <div class="space-y-3">
                            @foreach($recommendedAdjustments as $adjustment)
                                <div class="flex items-center justify-between p-3 bg-white dark:bg-gray-800 rounded-lg">
                                    <div class="flex-1">
                                        <p class="font-medium">{{ $adjustment['pair'] }}</p>
                                        <p class="text-sm text-gray-600 dark:text-gray-400">
                                            Current: ${{ number_format($adjustment['current_price'], 2) }} 
                                            → Recommended: ${{ number_format($adjustment['recommended_price'], 2) }}
                                            ({{ $adjustment['action'] }} {{ number_format($adjustment['deviation_percentage'], 1) }}%)
                                        </p>
                                    </div>
                                    <button onclick="applyAdjustment('{{ json_encode($adjustment) }}')"
                                            class="ml-4 px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition text-sm">
                                        Apply
                                    </button>
                                </div>
                            @endforeach
                        </div>
                        
                        <div class="mt-4">
                            <button onclick="applyAllAdjustments()"
                                    class="w-full px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 transition">
                                Apply All Recommendations
                            </button>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Alignment History -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-semibold mb-4">Price Alignment History</h3>
                    
                    @if($alignmentHistory->isEmpty())
                        <p class="text-gray-600 dark:text-gray-400">No price alignment history yet.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Date
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Pair
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Old Price
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            New Price
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Change
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Method
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($alignmentHistory as $history)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                {{ \Carbon\Carbon::parse($history->created_at)->format('M d, H:i') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                {{ $history->pair }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                ${{ number_format($history->old_price, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                ${{ number_format($history->new_price, 2) }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <span class="{{ $history->change_percentage > 0 ? 'text-green-600' : 'text-red-600' }}">
                                                    {{ $history->change_percentage > 0 ? '+' : '' }}{{ number_format($history->change_percentage, 2) }}%
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full {{ $history->method === 'automatic' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800' }}">
                                                    {{ ucfirst($history->method) }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function alignPrice(pair, marketPrice) {
            if (confirm(`Align ${pair} price to $${marketPrice.toFixed(2)}?`)) {
                // Implement price alignment
                alert('Price alignment initiated for ' + pair);
            }
        }
        
        function applyAdjustment(adjustmentJson) {
            const adjustment = JSON.parse(adjustmentJson);
            if (confirm(`Apply price adjustment for ${adjustment.pair}?\n\nCurrent: $${adjustment.current_price.toFixed(2)}\nNew: $${adjustment.recommended_price.toFixed(2)}`)) {
                // Implement adjustment
                alert('Price adjustment applied for ' + adjustment.pair);
            }
        }
        
        function applyAllAdjustments() {
            if (confirm('Apply all recommended price adjustments?')) {
                // Implement bulk adjustments
                alert('All price adjustments applied');
            }
        }
        
        // Auto-refresh every minute
        setInterval(function() {
            if (document.visibilityState === 'visible') {
                window.location.reload();
            }
        }, 60000);
    </script>
    @endpush
</x-app-layout>