<div class="p-6 lg:p-8 bg-white dark:bg-gray-800 dark:bg-gradient-to-bl dark:from-gray-700/50 dark:via-transparent border-b border-gray-200 dark:border-gray-700">
    <div class="flex items-center gap-4">
        <div class="flex-shrink-0">
            <div class="w-16 h-16 bg-indigo-500 rounded-full flex items-center justify-center">
                <span class="text-2xl font-bold text-white">Ǥ</span>
            </div>
        </div>
        <div>
            <h1 class="text-2xl font-medium text-gray-900 dark:text-white">
                GCU Wallet
            </h1>
            <p class="text-gray-500 dark:text-gray-400">
                Your gateway to the Global Currency Unit
            </p>
        </div>
    </div>
</div>

<div class="bg-gray-200 dark:bg-gray-800 bg-opacity-25 p-6 lg:p-8">
    <!-- Balance Overview -->
    <div class="mb-8">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Balance Overview</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <!-- GCU Balance -->
            <div class="bg-white dark:bg-gray-700 rounded-lg p-6 shadow">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">GCU Balance</span>
                    <span class="text-2xl font-bold text-indigo-600 dark:text-indigo-400">Ǥ</span>
                </div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white" 
                     x-data="{ balance: '0.00' }" 
                     x-init="@if(auth()->user()->accounts->first())fetch('/api/accounts/{{ auth()->user()->accounts->first()->uuid }}/balances?asset=GCU', { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']').getAttribute('content') }, credentials: 'same-origin' }).then(response => response.json()).then(data => { if (data.data && data.data.balances) { const gcuBalance = data.data.balances.find(b => b.asset_code === 'GCU'); balance = gcuBalance ? (gcuBalance.balance / 100).toFixed(2) : '0.00'; } }).catch(error => { console.error('Error fetching GCU balance:', error); balance = '0.00'; })@else balance = '0.00'; @endif" 
                     x-text="balance">
                    0.00
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">≈ $<span x-text="(parseFloat(balance) * 1.1).toFixed(2)">0.00</span> USD</p>
            </div>

            <!-- Total Value -->
            <div class="bg-white dark:bg-gray-700 rounded-lg p-6 shadow">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Value</span>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white" 
                     x-data="{ total: '0.00' }" 
                     x-init="@if(auth()->user()->accounts->first())fetch('/api/accounts/{{ auth()->user()->accounts->first()->uuid }}/balances', { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']').getAttribute('content') }, credentials: 'same-origin' }).then(response => response.json()).then(data => { if (data.data && data.data.summary) { total = data.data.summary.total_usd_equivalent || '0.00'; } }).catch(error => { console.error('Error fetching total balance:', error); total = '0.00'; })@else total = '0.00'; @endif" 
                     x-text="'$' + total">
                    $0.00
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Across all currencies</p>
            </div>

            <!-- Voting Power -->
            <div class="bg-white dark:bg-gray-700 rounded-lg p-6 shadow">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-gray-500 dark:text-gray-400">Voting Power</span>
                    <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>
                <div class="text-2xl font-bold text-gray-900 dark:text-white" 
                     x-data="{ power: 0 }" 
                     x-init="@if(auth()->user()->accounts->first())fetch('/api/accounts/{{ auth()->user()->accounts->first()->uuid }}/balances?asset=GCU', { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']').getAttribute('content') }, credentials: 'same-origin' }).then(response => response.json()).then(data => { if (data.data && data.data.balances) { const gcuBalance = data.data.balances.find(b => b.asset_code === 'GCU'); power = gcuBalance ? Math.floor(gcuBalance.balance / 100) : 0; } }).catch(error => { console.error('Error fetching voting power:', error); power = 0; })@else power = 0; @endif" 
                     x-text="power">
                    0
                </div>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">For governance votes</p>
            </div>
        </div>
    </div>

    <!-- Asset Breakdown -->
    <div class="mb-8">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Your Assets</h2>
        <div class="bg-white dark:bg-gray-700 rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                    <thead class="bg-gray-50 dark:bg-gray-800">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Asset</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Balance</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">USD Value</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white dark:bg-gray-700 divide-y divide-gray-200 dark:divide-gray-600" 
                           x-data="{ balances: [] }" 
                           x-init="@if(auth()->user()->accounts->first())fetch('/api/accounts/{{ auth()->user()->accounts->first()->uuid }}/balances', { headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=\'csrf-token\']').getAttribute('content') }, credentials: 'same-origin' }).then(response => response.json()).then(data => { if (data.data && data.data.balances) { balances = data.data.balances.filter(b => b.balance > 0); } }).catch(error => { console.error('Error fetching balances:', error); balances = []; })@else balances = []; @endif">
                        <template x-for="balance in balances" :key="balance.asset_code">
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 bg-gray-100 dark:bg-gray-600 rounded-full flex items-center justify-center">
                                            <span class="text-sm font-medium text-gray-600 dark:text-gray-300" x-text="balance.asset.symbol || balance.asset_code.charAt(0)"></span>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900 dark:text-white" x-text="balance.asset.name"></div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400" x-text="balance.asset_code"></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-900 dark:text-white" x-text="balance.formatted"></td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm text-gray-500 dark:text-gray-400">
                                    <span x-text="'$' + ((balance.balance / Math.pow(10, balance.asset.precision)) * (balance.asset_code === 'USD' ? 1 : 0.85)).toFixed(2)"></span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-medium">
                                    <a href="#" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">Send</a>
                                    <span class="text-gray-400 mx-2">|</span>
                                    <a href="#" class="text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">Convert</a>
                                </td>
                            </tr>
                        </template>
                        <!-- Show message when no balances -->
                        <tr x-show="balances.length === 0">
                            <td colspan="4" class="px-6 py-8 text-center text-gray-500 dark:text-gray-400">
                                <div class="flex flex-col items-center">
                                    <svg class="w-12 h-12 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                    </svg>
                                    <p class="text-base font-medium mb-1">No assets yet</p>
                                    <p class="text-sm">
                                        @if(auth()->user()->accounts->count() == 0)
                                            Create an account to get started
                                        @else
                                            Deposit funds to see your balances here
                                        @endif
                                    </p>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Transactions -->
    <div>
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Transactions</h2>
            <a href="/wallet/transactions" class="text-sm text-indigo-600 hover:text-indigo-900 dark:text-indigo-400 dark:hover:text-indigo-300">View all →</a>
        </div>
        <div class="bg-white dark:bg-gray-700 rounded-lg shadow">
            <div class="p-6 text-center text-gray-500 dark:text-gray-400">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                </svg>
                <p class="mt-2">No recent transactions</p>
                <p class="text-sm mt-1">Your transaction history will appear here</p>
            </div>
        </div>
    </div>
</div>