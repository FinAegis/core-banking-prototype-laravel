<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('DeFi Portfolio') }}
            </h2>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                Manage your decentralized finance positions
            </p>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <!-- Total DeFi Value -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total DeFi Value</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">$0.00</p>
                            </div>
                            <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-full">
                                <svg class="w-6 h-6 text-green-600 dark:text-green-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"></path>
                                </svg>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            Across all protocols
                        </p>
                    </div>
                </div>

                <!-- Active Positions -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active Positions</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">0</p>
                            </div>
                            <div class="p-3 bg-blue-100 dark:bg-blue-900/30 rounded-full">
                                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"></path>
                                </svg>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            Lending, staking &amp; LP
                        </p>
                    </div>
                </div>

                <!-- Average APY -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Average APY</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">0.00%</p>
                            </div>
                            <div class="p-3 bg-emerald-100 dark:bg-emerald-900/30 rounded-full">
                                <svg class="w-6 h-6 text-emerald-600 dark:text-emerald-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.25 18L9 11.25l4.306 4.307a11.95 11.95 0 015.814-5.519l2.74-1.22m0 0l-5.94-2.28m5.94 2.28l-2.28 5.941"></path>
                                </svg>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            Weighted across positions
                        </p>
                    </div>
                </div>

                <!-- Health Score -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Health Score</p>
                                <p class="text-2xl font-bold text-green-600 dark:text-green-400">Healthy</p>
                            </div>
                            <div class="p-3 bg-rose-100 dark:bg-rose-900/30 rounded-full">
                                <svg class="w-6 h-6 text-rose-600 dark:text-rose-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12z"></path>
                                </svg>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            No liquidation risk
                        </p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-8">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <!-- Swap Tokens -->
                        <a href="#" class="flex flex-col items-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition group">
                            <div class="p-3 bg-indigo-100 dark:bg-indigo-900/30 rounded-full mb-2 group-hover:bg-indigo-200 dark:group-hover:bg-indigo-900/50 transition">
                                <svg class="w-6 h-6 text-indigo-600 dark:text-indigo-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"></path>
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Swap Tokens</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400 mt-1">DEX aggregator</span>
                        </a>

                        <!-- Supply Assets -->
                        <a href="#" class="flex flex-col items-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition group">
                            <div class="p-3 bg-green-100 dark:bg-green-900/30 rounded-full mb-2 group-hover:bg-green-200 dark:group-hover:bg-green-900/50 transition">
                                <svg class="w-6 h-6 text-green-600 dark:text-green-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Supply Assets</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400 mt-1">Lending protocols</span>
                        </a>

                        <!-- Stake Tokens -->
                        <a href="#" class="flex flex-col items-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition group">
                            <div class="p-3 bg-purple-100 dark:bg-purple-900/30 rounded-full mb-2 group-hover:bg-purple-200 dark:group-hover:bg-purple-900/50 transition">
                                <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"></path>
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Stake Tokens</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400 mt-1">Earn rewards</span>
                        </a>

                        <!-- Flash Loan -->
                        <a href="#" class="flex flex-col items-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition group">
                            <div class="p-3 bg-yellow-100 dark:bg-yellow-900/30 rounded-full mb-2 group-hover:bg-yellow-200 dark:group-hover:bg-yellow-900/50 transition">
                                <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z"></path>
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Flash Loan</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400 mt-1">Atomic loans</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Active Positions (2 columns) -->
                <div class="lg:col-span-2">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Active Positions</h3>
                                <a href="#" class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300">
                                    View history
                                </a>
                            </div>

                            <!-- Empty State -->
                            <div class="text-center py-12" id="positions-empty">
                                <svg class="w-16 h-16 text-gray-300 dark:text-gray-600 mx-auto mb-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125"></path>
                                </svg>
                                <h4 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No Active Positions</h4>
                                <p class="text-sm text-gray-500 dark:text-gray-400 mb-6 max-w-sm mx-auto">
                                    Get started by supplying assets to a lending protocol, staking tokens, or providing liquidity to earn yield.
                                </p>
                                <div class="flex justify-center gap-3">
                                    <a href="#" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition">
                                        <svg class="w-4 h-4 mr-2" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v6m3-3H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                        Supply Assets
                                    </a>
                                    <a href="#" class="inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 text-sm font-medium rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                        <svg class="w-4 h-4 mr-2" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7.5 21L3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"></path>
                                        </svg>
                                        Swap Tokens
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Sidebar (1 column) -->
                <div class="lg:col-span-1 space-y-6">
                    <!-- Supported Protocols -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Supported Protocols</h3>
                            <ul class="space-y-3">
                                <li>
                                    <a href="#" class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                        <div class="flex items-center">
                                            <span class="w-3 h-3 rounded-full bg-pink-500 mr-3 flex-shrink-0"></span>
                                            <div>
                                                <p class="text-sm font-medium text-gray-900 dark:text-white">Uniswap</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">DEX &amp; Liquidity</p>
                                            </div>
                                        </div>
                                        <svg class="w-4 h-4 text-gray-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </a>
                                </li>
                                <li>
                                    <a href="#" class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                        <div class="flex items-center">
                                            <span class="w-3 h-3 rounded-full bg-purple-500 mr-3 flex-shrink-0"></span>
                                            <div>
                                                <p class="text-sm font-medium text-gray-900 dark:text-white">Aave</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Lending &amp; Borrowing</p>
                                            </div>
                                        </div>
                                        <svg class="w-4 h-4 text-gray-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </a>
                                </li>
                                <li>
                                    <a href="#" class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                        <div class="flex items-center">
                                            <span class="w-3 h-3 rounded-full bg-red-500 mr-3 flex-shrink-0"></span>
                                            <div>
                                                <p class="text-sm font-medium text-gray-900 dark:text-white">Curve</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Stablecoin Pools</p>
                                            </div>
                                        </div>
                                        <svg class="w-4 h-4 text-gray-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </a>
                                </li>
                                <li>
                                    <a href="#" class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                        <div class="flex items-center">
                                            <span class="w-3 h-3 rounded-full bg-sky-500 mr-3 flex-shrink-0"></span>
                                            <div>
                                                <p class="text-sm font-medium text-gray-900 dark:text-white">Lido</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Liquid Staking</p>
                                            </div>
                                        </div>
                                        <svg class="w-4 h-4 text-gray-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </a>
                                </li>
                                <li>
                                    <a href="#" class="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                        <div class="flex items-center">
                                            <span class="w-3 h-3 rounded-full bg-emerald-500 mr-3 flex-shrink-0"></span>
                                            <div>
                                                <p class="text-sm font-medium text-gray-900 dark:text-white">Compound</p>
                                                <p class="text-xs text-gray-500 dark:text-gray-400">Money Markets</p>
                                            </div>
                                        </div>
                                        <svg class="w-4 h-4 text-gray-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                        </svg>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Yield Overview -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Yield Overview</h3>
                            <!-- Placeholder Chart Area -->
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-8 text-center">
                                <svg class="w-12 h-12 text-gray-300 dark:text-gray-500 mx-auto mb-3" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"></path>
                                </svg>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">No Data Yet</p>
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                    Yield history and performance charts will appear here once you have active positions.
                                </p>
                            </div>
                            <!-- Summary Stats -->
                            <div class="mt-4 grid grid-cols-2 gap-4">
                                <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Total Earned</p>
                                    <p class="text-lg font-semibold text-gray-900 dark:text-white">$0.00</p>
                                </div>
                                <div class="text-center p-3 bg-gray-50 dark:bg-gray-700 rounded-lg">
                                    <p class="text-xs text-gray-500 dark:text-gray-400">30d Yield</p>
                                    <p class="text-lg font-semibold text-gray-900 dark:text-white">$0.00</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</x-app-layout>
