<template>
    <AppLayout title="Transaction Status Tracking">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Transaction Status Tracking
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-gray-500 dark:text-gray-400 text-sm">Total Transactions</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ statistics.total }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-gray-500 dark:text-gray-400 text-sm">Success Rate</div>
                        <div class="text-2xl font-bold text-green-600 dark:text-green-400">{{ statistics.success_rate }}%</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-gray-500 dark:text-gray-400 text-sm">Pending</div>
                        <div class="text-2xl font-bold text-yellow-600 dark:text-yellow-400">{{ statistics.pending }}</div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-gray-500 dark:text-gray-400 text-sm">Avg. Completion</div>
                        <div class="text-lg font-bold text-gray-900 dark:text-gray-100">{{ statistics.avg_completion_time_formatted }}</div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 mb-8">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Filters</h3>
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                            <select v-model="filters.status" @change="applyFilters" 
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                <option value="all">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="processing">Processing</option>
                                <option value="completed">Completed</option>
                                <option value="failed">Failed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Type</label>
                            <select v-model="filters.type" @change="applyFilters" 
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                <option value="all">All Types</option>
                                <option value="deposit">Deposit</option>
                                <option value="withdrawal">Withdrawal</option>
                                <option value="transfer">Transfer</option>
                                <option value="exchange">Exchange</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Account</label>
                            <select v-model="filters.account" @change="applyFilters" 
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                <option value="all">All Accounts</option>
                                <option v-for="account in accounts" :key="account.uuid" :value="account.uuid">
                                    {{ account.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From Date</label>
                            <input type="date" v-model="filters.date_from" @change="applyFilters"
                                   class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To Date</label>
                            <input type="date" v-model="filters.date_to" @change="applyFilters"
                                   class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                        </div>
                    </div>
                </div>

                <!-- Pending Transactions -->
                <div v-if="pendingTransactions.length > 0" class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-8">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                            Pending Transactions
                            <span class="ml-2 text-sm text-gray-500">({{ pendingTransactions.length }})</span>
                        </h3>
                        <div class="space-y-4">
                            <div v-for="transaction in pendingTransactions" :key="transaction.id" 
                                 class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="flex items-center space-x-4">
                                        <div :class="getTransactionIcon(transaction.type)" 
                                             class="w-10 h-10 rounded-full flex items-center justify-center">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path v-if="transaction.type === 'deposit'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"></path>
                                                <path v-else-if="transaction.type === 'withdrawal'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"></path>
                                                <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4"></path>
                                            </svg>
                                        </div>
                                        <div>
                                            <div class="font-medium text-gray-900 dark:text-gray-100">
                                                {{ formatTransactionType(transaction.type) }}
                                            </div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ transaction.reference || transaction.id }}
                                            </div>
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-medium text-gray-900 dark:text-gray-100">
                                            {{ formatAmount(transaction.amount, transaction.currency) }}
                                        </div>
                                        <div class="text-sm text-gray-500 dark:text-gray-400">
                                            {{ formatDate(transaction.created_at) }}
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Progress Bar -->
                                <div class="mt-3">
                                    <div class="flex items-center justify-between text-sm mb-1">
                                        <span class="text-gray-600 dark:text-gray-400">{{ getStatusText(transaction.status) }}</span>
                                        <span class="text-gray-600 dark:text-gray-400">{{ transaction.progress_percentage }}%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-blue-600 h-2 rounded-full transition-all duration-500" 
                                             :style="`width: ${transaction.progress_percentage}%`"></div>
                                    </div>
                                    <div v-if="transaction.estimated_completion" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Est. completion: {{ formatEstimatedTime(transaction.estimated_completion) }}
                                    </div>
                                </div>
                                
                                <!-- Actions -->
                                <div class="mt-3 flex items-center space-x-4">
                                    <Link :href="route('transactions.status.show', transaction.id)" 
                                          class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                                        View Details
                                    </Link>
                                    <button v-if="canCancel(transaction)" 
                                            @click="cancelTransaction(transaction.id)"
                                            class="text-sm text-red-600 hover:text-red-800 dark:text-red-400 dark:hover:text-red-300">
                                        Cancel
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Completed Transactions -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                            Recent Transactions
                            <span class="ml-2 text-sm text-gray-500">({{ completedTransactions.length }})</span>
                        </h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Transaction
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Account
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Amount
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Date
                                        </th>
                                        <th class="relative px-6 py-3">
                                            <span class="sr-only">Actions</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-for="transaction in completedTransactions" :key="transaction.id">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div :class="getTransactionIcon(transaction.type)" 
                                                     class="w-8 h-8 rounded-full flex items-center justify-center mr-3">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path v-if="transaction.type === 'deposit'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"></path>
                                                        <path v-else-if="transaction.type === 'withdrawal'" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"></path>
                                                        <path v-else stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4"></path>
                                                    </svg>
                                                </div>
                                                <div>
                                                    <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                        {{ formatTransactionType(transaction.type) }}
                                                    </div>
                                                    <div class="text-sm text-gray-500 dark:text-gray-400">
                                                        {{ transaction.reference || transaction.id.substring(0, 8) }}
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ transaction.account_name }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ formatAmount(transaction.amount, transaction.currency) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span :class="getStatusClass(transaction.status)" 
                                                  class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full">
                                                {{ getStatusText(transaction.status) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ formatDate(transaction.created_at) }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <Link :href="route('transactions.status.show', transaction.id)" 
                                                  class="text-blue-600 hover:text-blue-900 dark:text-blue-400 dark:hover:text-blue-300">
                                                View
                                            </Link>
                                            <button v-if="canRetry(transaction)" 
                                                    @click="retryTransaction(transaction.id)"
                                                    class="ml-3 text-green-600 hover:text-green-900 dark:text-green-400 dark:hover:text-green-300">
                                                Retry
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Auto-refresh indicator -->
        <div class="fixed bottom-4 right-4 bg-gray-900 text-white px-4 py-2 rounded-lg shadow-lg flex items-center space-x-2">
            <div class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></div>
            <span class="text-sm">Live updates enabled</span>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    accounts: Array,
    pendingTransactions: Array,
    completedTransactions: Array,
    statistics: Object,
    filters: Object,
});

const filters = ref({ ...props.filters });
let refreshInterval = null;

onMounted(() => {
    // Auto-refresh pending transactions every 5 seconds
    refreshInterval = setInterval(() => {
        refreshPendingTransactions();
    }, 5000);
});

onUnmounted(() => {
    if (refreshInterval) {
        clearInterval(refreshInterval);
    }
});

const applyFilters = () => {
    router.get(route('transactions.status'), filters.value, {
        preserveState: true,
        preserveScroll: true,
    });
};

const refreshPendingTransactions = () => {
    // Only refresh if there are pending transactions
    if (props.pendingTransactions.length > 0) {
        router.reload({
            only: ['pendingTransactions', 'statistics'],
            preserveScroll: true,
        });
    }
};

const formatAmount = (amount, currency) => {
    const formatter = new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency || 'USD',
    });
    return formatter.format(amount / 100);
};

const formatDate = (dateString) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    
    if (diffMins < 1) return 'Just now';
    if (diffMins < 60) return `${diffMins} min ago`;
    if (diffMins < 1440) return `${Math.floor(diffMins / 60)} hours ago`;
    
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatEstimatedTime = (dateString) => {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = date - now;
    const diffMins = Math.floor(diffMs / 60000);
    
    if (diffMins < 1) return 'Any moment';
    if (diffMins < 60) return `${diffMins} min`;
    if (diffMins < 1440) return `${Math.floor(diffMins / 60)} hours`;
    
    return `${Math.floor(diffMins / 1440)} days`;
};

const formatTransactionType = (type) => {
    const types = {
        deposit: 'Deposit',
        withdrawal: 'Withdrawal',
        transfer: 'Transfer',
        exchange: 'Exchange',
        payment: 'Payment',
    };
    return types[type] || type;
};

const getTransactionIcon = (type) => {
    const icons = {
        deposit: 'bg-green-100 text-green-600 dark:bg-green-900 dark:text-green-400',
        withdrawal: 'bg-red-100 text-red-600 dark:bg-red-900 dark:text-red-400',
        transfer: 'bg-blue-100 text-blue-600 dark:bg-blue-900 dark:text-blue-400',
        exchange: 'bg-purple-100 text-purple-600 dark:bg-purple-900 dark:text-purple-400',
    };
    return icons[type] || 'bg-gray-100 text-gray-600 dark:bg-gray-900 dark:text-gray-400';
};

const getStatusClass = (status) => {
    const classes = {
        completed: 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200',
        pending: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200',
        processing: 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200',
        failed: 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200',
        cancelled: 'bg-gray-100 text-gray-800 dark:bg-gray-900 dark:text-gray-200',
    };
    return classes[status] || 'bg-gray-100 text-gray-800';
};

const getStatusText = (status) => {
    const texts = {
        completed: 'Completed',
        pending: 'Pending',
        processing: 'Processing',
        failed: 'Failed',
        cancelled: 'Cancelled',
        hold: 'On Hold',
    };
    return texts[status] || status;
};

const canCancel = (transaction) => {
    return transaction.status === 'pending' && transaction.type !== 'deposit';
};

const canRetry = (transaction) => {
    return transaction.status === 'failed' && !transaction.retried_at;
};

const cancelTransaction = async (transactionId) => {
    if (confirm('Are you sure you want to cancel this transaction?')) {
        try {
            await axios.post(route('transactions.status.cancel', transactionId));
            router.reload();
        } catch (error) {
            alert('Failed to cancel transaction');
        }
    }
};

const retryTransaction = async (transactionId) => {
    if (confirm('Do you want to retry this transaction?')) {
        try {
            await axios.post(route('transactions.status.retry', transactionId));
            router.reload();
        } catch (error) {
            alert('Failed to retry transaction');
        }
    }
};
</script>