<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Transaction History') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 lg:p-8" x-data="transactionHistory()">
                    <!-- Filters -->
                    <div class="mb-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Asset</label>
                            <select x-model="filters.asset" @change="loadTransactions()" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <option value="">All Assets</option>
                                <option value="GCU">GCU</option>
                                <option value="USD">USD</option>
                                <option value="EUR">EUR</option>
                                <option value="GBP">GBP</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Type</label>
                            <select x-model="filters.type" @change="loadTransactions()" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <option value="">All Types</option>
                                <option value="deposit">Deposits</option>
                                <option value="withdrawal">Withdrawals</option>
                                <option value="transfer_in">Transfers In</option>
                                <option value="transfer_out">Transfers Out</option>
                                <option value="conversion">Conversions</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Date Range</label>
                            <select x-model="filters.range" @change="loadTransactions()" class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white">
                                <option value="7">Last 7 days</option>
                                <option value="30">Last 30 days</option>
                                <option value="90">Last 90 days</option>
                                <option value="365">Last year</option>
                                <option value="">All time</option>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Search</label>
                            <input 
                                type="text" 
                                x-model="filters.search" 
                                @input.debounce.300ms="loadTransactions()"
                                placeholder="Reference, description..."
                                class="w-full rounded-md border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                            >
                        </div>
                    </div>

                    <!-- Summary Cards -->
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Total In</p>
                            <p class="text-xl font-bold text-green-600 dark:text-green-400">
                                +$<span x-text="formatAmount(summary.total_in)">0.00</span>
                            </p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Total Out</p>
                            <p class="text-xl font-bold text-red-600 dark:text-red-400">
                                -$<span x-text="formatAmount(summary.total_out)">0.00</span>
                            </p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Net Change</p>
                            <p class="text-xl font-bold" :class="summary.net_change >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                <span x-text="summary.net_change >= 0 ? '+' : ''"></span>$<span x-text="formatAmount(Math.abs(summary.net_change))">0.00</span>
                            </p>
                        </div>
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Transactions</p>
                            <p class="text-xl font-bold text-gray-900 dark:text-white" x-text="summary.count">0</p>
                        </div>
                    </div>

                    <!-- Transactions Table -->
                    <div class="bg-white dark:bg-gray-800 shadow overflow-hidden sm:rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Date</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Description</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Asset</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Amount</th>
                                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Balance</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Status</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                <template x-for="transaction in transactions" :key="transaction.id">
                                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-700">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                            <div>
                                                <div x-text="formatDate(transaction.created_at)"></div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400" x-text="formatTime(transaction.created_at)"></div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full" :class="getTypeClass(transaction.type)">
                                                <span x-text="getTypeLabel(transaction.type)"></span>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-900 dark:text-white">
                                            <div x-text="transaction.description"></div>
                                            <div x-show="transaction.reference" class="text-xs text-gray-500 dark:text-gray-400">
                                                Ref: <span x-text="transaction.reference"></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-white">
                                            <div class="flex items-center">
                                                <span class="mr-2" x-text="transaction.asset_code"></span>
                                                <span class="text-xs text-gray-500 dark:text-gray-400" x-text="transaction.asset_symbol"></span>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium" :class="transaction.amount >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                            <span x-text="transaction.amount >= 0 ? '+' : ''"></span><span x-text="formatCurrencyAmount(transaction.amount, transaction.asset_code)"></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-900 dark:text-white">
                                            <span x-text="formatCurrencyAmount(transaction.balance_after, transaction.asset_code)"></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-center">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100">
                                                Completed
                                            </span>
                                        </td>
                                    </tr>
                                </template>
                                <tr x-show="transactions.length === 0">
                                    <td colspan="7" class="px-6 py-12 text-center text-gray-500 dark:text-gray-400">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                        </svg>
                                        <p class="mt-2">No transactions found</p>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div x-show="totalPages > 1" class="mt-4 flex items-center justify-between">
                        <div class="text-sm text-gray-700 dark:text-gray-300">
                            Showing <span x-text="((currentPage - 1) * perPage) + 1"></span> to 
                            <span x-text="Math.min(currentPage * perPage, totalTransactions)"></span> of 
                            <span x-text="totalTransactions"></span> transactions
                        </div>
                        <div class="flex space-x-2">
                            <button 
                                @click="previousPage()" 
                                :disabled="currentPage === 1"
                                class="px-3 py-1 text-sm bg-gray-200 dark:bg-gray-700 rounded hover:bg-gray-300 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Previous
                            </button>
                            <template x-for="page in pageNumbers" :key="page">
                                <button 
                                    @click="goToPage(page)"
                                    :class="currentPage === page ? 'bg-indigo-600 text-white' : 'bg-gray-200 dark:bg-gray-700 hover:bg-gray-300 dark:hover:bg-gray-600'"
                                    class="px-3 py-1 text-sm rounded"
                                    x-text="page"
                                ></button>
                            </template>
                            <button 
                                @click="nextPage()" 
                                :disabled="currentPage === totalPages"
                                class="px-3 py-1 text-sm bg-gray-200 dark:bg-gray-700 rounded hover:bg-gray-300 dark:hover:bg-gray-600 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                Next
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function transactionHistory() {
            return {
                transactions: [],
                filters: {
                    asset: '',
                    type: '',
                    range: '30',
                    search: ''
                },
                summary: {
                    total_in: 0,
                    total_out: 0,
                    net_change: 0,
                    count: 0
                },
                currentPage: 1,
                perPage: 20,
                totalTransactions: 0,
                
                get totalPages() {
                    return Math.ceil(this.totalTransactions / this.perPage);
                },
                
                get pageNumbers() {
                    const pages = [];
                    const start = Math.max(1, this.currentPage - 2);
                    const end = Math.min(this.totalPages, start + 4);
                    
                    for (let i = start; i <= end; i++) {
                        pages.push(i);
                    }
                    return pages;
                },
                
                init() {
                    this.loadTransactions();
                },
                
                async loadTransactions() {
                    try {
                        // Build query parameters
                        const params = new URLSearchParams({
                            page: this.currentPage,
                            per_page: this.perPage
                        });
                        
                        if (this.filters.asset) params.append('asset', this.filters.asset);
                        if (this.filters.type) params.append('type', this.filters.type);
                        if (this.filters.range) params.append('days', this.filters.range);
                        if (this.filters.search) params.append('search', this.filters.search);
                        
                        const accountUuid = '{{ auth()->user()->accounts->first()->uuid ?? '' }}';
                        const response = await fetch(`/api/accounts/${accountUuid}/transactions?${params}`);
                        const data = await response.json();
                        
                        this.transactions = data.data || [];
                        this.totalTransactions = data.meta?.total || 0;
                        this.calculateSummary();
                        
                    } catch (error) {
                        console.error('Failed to load transactions:', error);
                        // For demo, load mock data
                        this.loadMockTransactions();
                    }
                },
                
                loadMockTransactions() {
                    // Mock transaction data for demonstration
                    this.transactions = [
                        {
                            id: 1,
                            created_at: new Date().toISOString(),
                            type: 'deposit',
                            description: 'Initial GCU deposit',
                            reference: 'DEP-001',
                            asset_code: 'GCU',
                            asset_symbol: 'Ǥ',
                            amount: 100000, // 1000.00 GCU
                            balance_after: 100000
                        },
                        {
                            id: 2,
                            created_at: new Date(Date.now() - 86400000).toISOString(),
                            type: 'conversion',
                            description: 'Convert USD to GCU',
                            reference: 'CNV-001',
                            asset_code: 'USD',
                            asset_symbol: '$',
                            amount: -110000, // -1100.00 USD
                            balance_after: 0
                        },
                        {
                            id: 3,
                            created_at: new Date(Date.now() - 172800000).toISOString(),
                            type: 'transfer_in',
                            description: 'Transfer from John Doe',
                            reference: 'TRF-001',
                            asset_code: 'EUR',
                            asset_symbol: '€',
                            amount: 50000, // 500.00 EUR
                            balance_after: 50000
                        }
                    ];
                    this.totalTransactions = 3;
                    this.calculateSummary();
                },
                
                calculateSummary() {
                    this.summary = this.transactions.reduce((acc, tx) => {
                        if (tx.amount > 0) {
                            acc.total_in += tx.amount;
                        } else {
                            acc.total_out += Math.abs(tx.amount);
                        }
                        acc.count++;
                        return acc;
                    }, { total_in: 0, total_out: 0, net_change: 0, count: 0 });
                    
                    this.summary.net_change = this.summary.total_in - this.summary.total_out;
                },
                
                formatAmount(amount) {
                    return (amount / 100).toFixed(2);
                },
                
                formatCurrencyAmount(amount, assetCode) {
                    const value = Math.abs(amount / 100);
                    return value.toFixed(2) + ' ' + assetCode;
                },
                
                formatDate(dateString) {
                    return new Date(dateString).toLocaleDateString();
                },
                
                formatTime(dateString) {
                    return new Date(dateString).toLocaleTimeString();
                },
                
                getTypeLabel(type) {
                    const labels = {
                        'deposit': 'Deposit',
                        'withdrawal': 'Withdrawal',
                        'transfer_in': 'Transfer In',
                        'transfer_out': 'Transfer Out',
                        'conversion': 'Conversion'
                    };
                    return labels[type] || type;
                },
                
                getTypeClass(type) {
                    const classes = {
                        'deposit': 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100',
                        'withdrawal': 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100',
                        'transfer_in': 'bg-blue-100 text-blue-800 dark:bg-blue-800 dark:text-blue-100',
                        'transfer_out': 'bg-orange-100 text-orange-800 dark:bg-orange-800 dark:text-orange-100',
                        'conversion': 'bg-purple-100 text-purple-800 dark:bg-purple-800 dark:text-purple-100'
                    };
                    return classes[type] || 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-100';
                },
                
                goToPage(page) {
                    this.currentPage = page;
                    this.loadTransactions();
                },
                
                previousPage() {
                    if (this.currentPage > 1) {
                        this.currentPage--;
                        this.loadTransactions();
                    }
                },
                
                nextPage() {
                    if (this.currentPage < this.totalPages) {
                        this.currentPage++;
                        this.loadTransactions();
                    }
                }
            };
        }
    </script>
</x-app-layout>