<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Invest in CGO') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <!-- Current Round Info -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg mb-8">
                <div class="p-6">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-4">Current Investment Round</h3>
                    
                    @if($currentRound)
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                                <p class="text-gray-600 dark:text-gray-400 text-sm">Round Number</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">#{{ $currentRound->round_number }}</p>
                            </div>
                            
                            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                                <p class="text-gray-600 dark:text-gray-400 text-sm">Share Price</p>
                                <p class="text-2xl font-bold text-indigo-600">${{ number_format($currentRound->share_price, 2) }}</p>
                            </div>
                            
                            <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                                <p class="text-gray-600 dark:text-gray-400 text-sm">Available Shares</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ number_format($currentRound->remaining_shares, 0) }}</p>
                            </div>
                        </div>
                        
                        <!-- Progress Bar -->
                        <div class="mt-6">
                            <div class="flex justify-between text-sm text-gray-600 dark:text-gray-400 mb-2">
                                <span>Round Progress</span>
                                <span>{{ number_format($currentRound->progress_percentage, 1) }}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-3">
                                <div class="bg-indigo-600 h-3 rounded-full" style="width: {{ $currentRound->progress_percentage }}%"></div>
                            </div>
                        </div>
                    @else
                        <p class="text-gray-600 dark:text-gray-400">No active investment round at the moment.</p>
                    @endif
                </div>
            </div>

            <!-- Investment Form -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-6">Make Your Investment</h3>
                    
                    <form id="investmentForm" action="{{ route('cgo.invest') }}" method="POST">
                        @csrf
                        
                        <!-- Investment Amount -->
                        <div class="mb-6">
                            <label for="amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Investment Amount (USD)
                            </label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-500">$</span>
                                <input type="number" name="amount" id="amount" min="100" step="0.01" required
                                    class="pl-8 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500"
                                    placeholder="1,000.00"
                                    value="{{ old('amount') }}">
                            </div>
                            @error('amount')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                            
                            <!-- Live Calculation -->
                            <div id="shareCalculation" class="mt-3 p-4 bg-gray-50 dark:bg-gray-900 rounded-lg hidden">
                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                    You will receive approximately <span id="shareCount" class="font-bold text-indigo-600">0</span> shares
                                </p>
                                <p class="text-xs text-gray-500 dark:text-gray-500 mt-1">
                                    Ownership percentage: <span id="ownershipPercentage">0.0000%</span>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Payment Method -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Payment Method
                            </label>
                            <div class="space-y-2">
                                <label class="flex items-center">
                                    <input type="radio" name="payment_method" value="crypto" class="mr-2" checked>
                                    <span>Cryptocurrency (BTC, ETH, USDT)</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="payment_method" value="bank_transfer" class="mr-2">
                                    <span>Bank Transfer</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="payment_method" value="card" class="mr-2">
                                    <span>Credit/Debit Card</span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Crypto Selection (shown when crypto is selected) -->
                        <div id="cryptoSelection" class="mb-6">
                            <label for="crypto_currency" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Select Cryptocurrency
                            </label>
                            <select name="crypto_currency" id="crypto_currency" class="block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                <option value="BTC">Bitcoin (BTC)</option>
                                <option value="ETH">Ethereum (ETH)</option>
                                <option value="USDT">Tether (USDT)</option>
                                <option value="USDC">USD Coin (USDC)</option>
                            </select>
                        </div>
                        
                        <!-- Terms Agreement -->
                        <div class="mb-6">
                            <label class="flex items-start">
                                <input type="checkbox" name="terms" required class="mt-1 mr-2">
                                <span class="text-sm text-gray-600 dark:text-gray-400">
                                    I agree to the <a href="/legal/cgo-terms" class="text-indigo-600 hover:text-indigo-500">CGO Terms and Conditions</a>
                                    and understand that this is an investment in the development of FinAegis platform.
                                </span>
                            </label>
                        </div>
                        
                        <!-- Investment Tier Display -->
                        <div id="tierDisplay" class="mb-6 p-4 border-2 border-gray-200 rounded-lg hidden">
                            <h4 class="font-semibold mb-2">Your Investment Tier: <span id="tierName"></span></h4>
                            <ul id="tierBenefits" class="text-sm text-gray-600 dark:text-gray-400 space-y-1"></ul>
                        </div>
                        
                        <!-- Submit Button -->
                        <div class="flex justify-end">
                            <button type="submit" class="bg-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-700 transition">
                                Proceed to Payment
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Recent Investments -->
            <div class="mt-8 bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-4">Your Investment History</h3>
                    
                    @if($userInvestments->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Shares</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Certificate</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach($userInvestments as $investment)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">{{ $investment->created_at->format('M d, Y') }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">${{ number_format($investment->amount, 2) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">{{ number_format($investment->shares_purchased, 4) }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-{{ $investment->status_color }}-100 text-{{ $investment->status_color }}-800">
                                                    {{ ucfirst($investment->status) }}
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                @if($investment->certificate_number)
                                                    <a href="{{ route('cgo.certificate', $investment->uuid) }}" class="text-indigo-600 hover:text-indigo-900">
                                                        Download
                                                    </a>
                                                @else
                                                    -
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-gray-600 dark:text-gray-400">You haven't made any investments yet.</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
    
    <script>
        const sharePrice = {{ $currentRound->share_price ?? 10 }};
        const totalShares = 1000000; // 1 million total shares
        
        document.getElementById('amount').addEventListener('input', function(e) {
            const amount = parseFloat(e.target.value) || 0;
            
            if (amount >= 100) {
                const shares = amount / sharePrice;
                const ownership = (shares / totalShares) * 100;
                
                document.getElementById('shareCount').textContent = shares.toFixed(4);
                document.getElementById('ownershipPercentage').textContent = ownership.toFixed(6) + '%';
                document.getElementById('shareCalculation').classList.remove('hidden');
                
                // Update tier display
                let tier = 'bronze';
                let tierBenefits = ['Digital ownership certificate', 'Early access to new features', 'Monthly investor updates'];
                
                if (amount >= 10000) {
                    tier = 'gold';
                    tierBenefits = [...tierBenefits, 'Physical certificate option', 'Voting rights', 'Quarterly calls', 'Direct team access', 'Advisory board consideration', 'Lifetime premium features'];
                } else if (amount >= 1000) {
                    tier = 'silver';
                    tierBenefits = [...tierBenefits, 'Physical certificate option', 'Voting rights on platform decisions', 'Quarterly investor calls'];
                }
                
                document.getElementById('tierName').textContent = tier.charAt(0).toUpperCase() + tier.slice(1);
                document.getElementById('tierBenefits').innerHTML = tierBenefits.map(b => `<li>• ${b}</li>`).join('');
                document.getElementById('tierDisplay').classList.remove('hidden');
            } else {
                document.getElementById('shareCalculation').classList.add('hidden');
                document.getElementById('tierDisplay').classList.add('hidden');
            }
        });
        
        // Show/hide crypto selection
        document.querySelectorAll('input[name="payment_method"]').forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'crypto') {
                    document.getElementById('cryptoSelection').style.display = 'block';
                } else {
                    document.getElementById('cryptoSelection').style.display = 'none';
                }
            });
        });
    </script>
</x-app-layout>