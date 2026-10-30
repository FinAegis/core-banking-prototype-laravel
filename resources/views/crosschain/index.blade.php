<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ __('Cross-Chain Portfolio') }}
                </h2>
                <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                    Bridge assets, swap across chains, and manage your multi-chain portfolio
                </p>
            </div>
            <div class="flex items-center space-x-3">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                    <svg class="w-2 h-2 mr-1" aria-hidden="true" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                    All Networks Online
                </span>
            </div>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                <!-- Multi-Chain Balance -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Multi-Chain Balance</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">$0.00</p>
                            </div>
                            <div class="p-3 bg-teal-100 dark:bg-teal-900 rounded-full">
                                <svg class="w-6 h-6 text-teal-600 dark:text-teal-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                                </svg>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            Aggregated across all chains
                        </p>
                    </div>
                </div>

                <!-- Active Bridges -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Active Bridges</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">0</p>
                            </div>
                            <div class="p-3 bg-blue-100 dark:bg-blue-900 rounded-full">
                                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                </svg>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            In-progress bridge transfers
                        </p>
                    </div>
                </div>

                <!-- Pending Transfers -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Pending Transfers</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">0</p>
                            </div>
                            <div class="p-3 bg-yellow-100 dark:bg-yellow-900 rounded-full">
                                <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            Awaiting confirmation
                        </p>
                    </div>
                </div>

                <!-- Networks Connected -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                    <div class="p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Networks Connected</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white">5</p>
                            </div>
                            <div class="p-3 bg-purple-100 dark:bg-purple-900 rounded-full">
                                <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"></path>
                                </svg>
                            </div>
                        </div>
                        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">
                            Supported blockchains
                        </p>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-8">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Quick Actions</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <!-- Bridge Assets -->
                        <a href="#" class="flex flex-col items-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition group">
                            <div class="p-3 bg-teal-100 dark:bg-teal-900 rounded-full mb-2 group-hover:bg-teal-200 dark:group-hover:bg-teal-800 transition">
                                <svg class="w-6 h-6 text-teal-600 dark:text-teal-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Bridge Assets</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400 mt-1">Cross-chain bridge</span>
                        </a>

                        <!-- Cross-Chain Swap -->
                        <a href="#" class="flex flex-col items-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition group">
                            <div class="p-3 bg-blue-100 dark:bg-blue-900 rounded-full mb-2 group-hover:bg-blue-200 dark:group-hover:bg-blue-800 transition">
                                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Cross-Chain Swap</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400 mt-1">Swap across chains</span>
                        </a>

                        <!-- View Portfolio -->
                        <a href="#" class="flex flex-col items-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition group">
                            <div class="p-3 bg-purple-100 dark:bg-purple-900 rounded-full mb-2 group-hover:bg-purple-200 dark:group-hover:bg-purple-800 transition">
                                <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z"></path>
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">View Portfolio</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400 mt-1">Multi-chain breakdown</span>
                        </a>

                        <!-- Protocol rate data -->
                        <a href="#" class="flex flex-col items-center p-4 bg-gray-50 dark:bg-gray-700 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600 transition group">
                            <div class="p-3 bg-green-100 dark:bg-green-900 rounded-full mb-2 group-hover:bg-green-200 dark:group-hover:bg-green-800 transition">
                                <svg class="w-6 h-6 text-green-600 dark:text-green-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                </svg>
                            </div>
                            <span class="text-sm font-medium text-gray-700 dark:text-gray-300">Protocol rate data</span>
                            <span class="text-xs text-gray-500 dark:text-gray-400 mt-1">(informational)</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Recent Bridge Transactions (2 columns) -->
                <div class="lg:col-span-2">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Recent Bridge Transactions</h3>
                                <a href="#" class="text-sm text-indigo-600 hover:text-indigo-700 dark:text-indigo-400 dark:hover:text-indigo-300">
                                    View all &rarr;
                                </a>
                            </div>

                            <!-- Table Header (visible on md+) -->
                            <div class="hidden md:block">
                                <div class="border-b border-gray-200 dark:border-gray-700">
                                    <div class="grid grid-cols-6 gap-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        <div>Date</div>
                                        <div>Route</div>
                                        <div>Token</div>
                                        <div class="text-right">Amount</div>
                                        <div class="text-center">Status</div>
                                        <div>Provider</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Empty State -->
                            <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-8 text-center">
                                <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                                </svg>
                                <p class="text-gray-600 dark:text-gray-400">No bridge transactions yet</p>
                                <p class="text-sm text-gray-500 dark:text-gray-500 mt-1">Your cross-chain transfer history will appear here</p>
                                <a href="#" class="inline-flex items-center mt-4 px-4 py-2 bg-teal-600 text-white text-sm font-medium rounded-lg hover:bg-teal-700 transition">
                                    <svg class="w-4 h-4 mr-2" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                    Start Your First Bridge
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Sidebar (1 column) -->
                <div class="lg:col-span-1 space-y-6">
                    <!-- Supported Networks -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Supported Networks</h3>
                            <ul class="space-y-3">
                                <li class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <span class="w-3 h-3 rounded-full bg-blue-500 mr-3"></span>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">Ethereum</span>
                                    </div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Mainnet</span>
                                </li>
                                <li class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <span class="w-3 h-3 rounded-full bg-purple-500 mr-3"></span>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">Polygon</span>
                                    </div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">PoS</span>
                                </li>
                                <li class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <span class="w-3 h-3 rounded-full bg-sky-500 mr-3"></span>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">Arbitrum</span>
                                    </div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">One</span>
                                </li>
                                <li class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <span class="w-3 h-3 rounded-full bg-red-500 mr-3"></span>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">Optimism</span>
                                    </div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">L2</span>
                                </li>
                                <li class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <span class="w-3 h-3 rounded-full bg-yellow-500 mr-3"></span>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">BSC</span>
                                    </div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">BNB Chain</span>
                                </li>
                                <li class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <span class="w-3 h-3 rounded-full bg-green-500 mr-3"></span>
                                        <span class="text-sm font-medium text-gray-900 dark:text-white">Solana</span>
                                    </div>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">Mainnet</span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- Bridge Providers -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Bridge Providers</h3>
                            <ul class="space-y-4">
                                <li class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-indigo-100 dark:bg-indigo-900 rounded-full flex items-center justify-center mr-3">
                                            <svg class="w-4 h-4 text-indigo-600 dark:text-indigo-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">Wormhole</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Cross-chain messaging</p>
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        <svg class="w-2 h-2 mr-1" aria-hidden="true" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                                        Active
                                    </span>
                                </li>
                                <li class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center mr-3">
                                            <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">LayerZero</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Omnichain interop</p>
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        <svg class="w-2 h-2 mr-1" aria-hidden="true" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                                        Active
                                    </span>
                                </li>
                                <li class="flex items-center justify-between">
                                    <div class="flex items-center">
                                        <div class="w-8 h-8 bg-orange-100 dark:bg-orange-900 rounded-full flex items-center justify-center mr-3">
                                            <svg class="w-4 h-4 text-orange-600 dark:text-orange-400" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-sm font-medium text-gray-900 dark:text-white">Axelar</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">Universal overlay</p>
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200">
                                        <svg class="w-2 h-2 mr-1" aria-hidden="true" fill="currentColor" viewBox="0 0 8 8"><circle cx="4" cy="4" r="3"/></svg>
                                        Active
                                    </span>
                                </li>
                            </ul>
                        </div>
                    </div>

                    <!-- DeFi Protocols Info -->
                    <div class="bg-teal-50 dark:bg-teal-900/20 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-teal-900 dark:text-teal-100 mb-2">DeFi Protocols</h3>
                        <p class="text-sm text-teal-700 dark:text-teal-300 mb-4">
                            Access leading DeFi protocols across multiple chains from a single interface.
                        </p>
                        <div class="space-y-2">
                            <a href="#" class="flex items-center text-sm text-teal-600 dark:text-teal-400 hover:text-teal-800 dark:hover:text-teal-200">
                                <svg class="w-4 h-4 mr-2" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                                </svg>
                                Uniswap &mdash; DEX Aggregation
                            </a>
                            <a href="#" class="flex items-center text-sm text-teal-600 dark:text-teal-400 hover:text-teal-800 dark:hover:text-teal-200">
                                <svg class="w-4 h-4 mr-2" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                                </svg>
                                Aave &mdash; Lending & Borrowing
                            </a>
                            <a href="#" class="flex items-center text-sm text-teal-600 dark:text-teal-400 hover:text-teal-800 dark:hover:text-teal-200">
                                <svg class="w-4 h-4 mr-2" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                                </svg>
                                Curve &mdash; Stable Swaps
                            </a>
                            <a href="#" class="flex items-center text-sm text-teal-600 dark:text-teal-400 hover:text-teal-800 dark:hover:text-teal-200">
                                <svg class="w-4 h-4 mr-2" aria-hidden="true" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                                </svg>
                                Lido &mdash; Liquid Staking
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
