<template>
    <AppLayout title="Transaction Details">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    Transaction Details
                </h2>
                <Link :href="route('transactions.status')" 
                      class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                    ← Back to Status Tracking
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <!-- Main Transaction Details -->
                    <div class="lg:col-span-2 space-y-6">
                        <!-- Transaction Overview -->
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                                    Transaction Overview
                                </h3>
                                
                                <div class="space-y-4">
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Type</span>
                                        <span class="font-medium text-gray-900 dark:text-gray-100">
                                            {{ formatTransactionType(transaction.type) }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Reference</span>
                                        <span class="font-mono text-sm text-gray-900 dark:text-gray-100">
                                            {{ transaction.reference || transaction.id }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Amount</span>
                                        <span class="text-xl font-bold text-gray-900 dark:text-gray-100">
                                            {{ formatAmount(transaction.amount, transaction.currency) }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Status</span>
                                        <span :class="getStatusClass(transaction.status)" 
                                              class="px-3 py-1 inline-flex text-sm leading-5 font-semibold rounded-full">
                                            {{ getStatusText(transaction.status) }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Account</span>
                                        <span class="text-gray-900 dark:text-gray-100">
                                            {{ transaction.account_name }}
                                        </span>
                                    </div>
                                    <div class="flex items-center justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Created</span>
                                        <span class="text-gray-900 dark:text-gray-100">
                                            {{ formatFullDate(transaction.created_at) }}
                                        </span>
                                    </div>
                                    <div v-if="transaction.updated_at !== transaction.created_at" 
                                         class="flex items-center justify-between">
                                        <span class="text-gray-600 dark:text-gray-400">Last Updated</span>
                                        <span class="text-gray-900 dark:text-gray-100">
                                            {{ formatFullDate(transaction.updated_at) }}
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Actions -->
                                <div v-if="canPerformActions" class="mt-6 pt-6 border-t border-gray-200 dark:border-gray-700 flex space-x-4">
                                    <button v-if="canCancel" 
                                            @click="cancelTransaction"
                                            class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition">
                                        Cancel Transaction
                                    </button>
                                    <button v-if="canRetry" 
                                            @click="retryTransaction"
                                            class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                                        Retry Transaction
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Transaction Timeline -->
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                                    Transaction Timeline
                                </h3>
                                
                                <div class="relative">
                                    <div class="absolute left-4 top-0 bottom-0 w-0.5 bg-gray-200 dark:bg-gray-700"></div>
                                    
                                    <div v-for="(event, index) in timeline" :key="index" class="relative flex items-start mb-6">
                                        <div :class="getTimelineIconClass(event.status)" 
                                             class="relative z-10 w-8 h-8 rounded-full flex items-center justify-center">
                                            <svg v-if="event.status === 'completed'" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                            </svg>
                                            <svg v-else-if="event.status === 'error'" class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                            </svg>
                                            <div v-else class="w-2 h-2 bg-current rounded-full"></div>
                                        </div>
                                        
                                        <div class="ml-6 flex-1">
                                            <div class="flex items-center justify-between">
                                                <h4 class="font-medium text-gray-900 dark:text-gray-100">
                                                    {{ event.description }}
                                                </h4>
                                                <span class="text-sm text-gray-500 dark:text-gray-400">
                                                    {{ formatTimelineDate(event.timestamp) }}
                                                </span>
                                            </div>
                                            <p v-if="event.details" class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                                {{ event.details }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar -->
                    <div class="space-y-6">
                        <!-- Status Card -->
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                                    Current Status
                                </h3>
                                
                                <div class="text-center">
                                    <div :class="getStatusIconClass(transaction.status)" 
                                         class="mx-auto w-16 h-16 rounded-full flex items-center justify-center mb-4">
                                        <svg v-if="transaction.status === 'completed'" class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                                        </svg>
                                        <svg v-else-if="transaction.status === 'failed'" class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                        </svg>
                                        <div v-else class="animate-spin rounded-full h-8 w-8 border-b-2 border-current"></div>
                                    </div>
                                    
                                    <div class="text-xl font-bold text-gray-900 dark:text-gray-100 mb-2">
                                        {{ getStatusText(transaction.status) }}
                                    </div>
                                    
                                    <div v-if="estimatedCompletion && transaction.status === 'processing'" 
                                         class="text-sm text-gray-600 dark:text-gray-400">
                                        Est. completion: {{ formatEstimatedTime(estimatedCompletion) }}
                                    </div>
                                </div>
                                
                                <button @click="refreshStatus" 
                                        class="mt-4 w-full px-4 py-2 bg-gray-100 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-200 dark:hover:bg-gray-600 transition">
                                    Refresh Status
                                </button>
                            </div>
                        </div>

                        <!-- Related Transactions -->
                        <div v-if="relatedTransactions.length > 0" 
                             class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                                    Related Transactions
                                </h3>
                                
                                <div class="space-y-3">
                                    <div v-for="related in relatedTransactions" :key="related.transaction.id">
                                        <Link :href="route('transactions.status.show', related.transaction.id)" 
                                              class="block p-3 border border-gray-200 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 transition">
                                            <div class="flex items-center justify-between">
                                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    {{ related.type }}
                                                </span>
                                                <span :class="getStatusClass(related.transaction.status)" 
                                                      class="text-xs px-2 py-1 rounded-full">
                                                    {{ getStatusText(related.transaction.status) }}
                                                </span>
                                            </div>
                                            <div class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                {{ related.transaction.reference }}
                                            </div>
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Information -->
                        <div v-if="transaction.metadata" 
                             class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6">
                                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                                    Additional Information
                                </h3>
                                
                                <div class="space-y-2 text-sm">
                                    <div v-for="(value, key) in parseMetadata(transaction.metadata)" :key="key" 
                                         class="flex items-start justify-between">
                                        <span class="text-gray-600 dark:text-gray-400 capitalize">
                                            {{ formatMetadataKey(key) }}
                                        </span>
                                        <span class="text-gray-900 dark:text-gray-100 text-right">
                                            {{ value }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import axios from 'axios';

const props = defineProps({
    transaction: Object,
    timeline: Array,
    relatedTransactions: Array,
});

const estimatedCompletion = ref(null);
let statusInterval = null;

onMounted(() => {
    // Auto-refresh status for pending transactions
    if (['pending', 'processing'].includes(props.transaction.status)) {
        statusInterval = setInterval(() => {
            checkStatus();
        }, 3000);
    }
});

onUnmounted(() => {
    if (statusInterval) {
        clearInterval(statusInterval);
    }
});

const canPerformActions = computed(() => {
    return ['pending', 'processing', 'failed'].includes(props.transaction.status);
});

const canCancel = computed(() => {
    return props.transaction.status === 'pending';
});

const canRetry = computed(() => {
    return props.transaction.status === 'failed' && !props.transaction.retried_at;
});

const checkStatus = async () => {
    try {
        const response = await axios.get(route('transactions.status.status', props.transaction.id));
        
        if (response.data.status !== props.transaction.status) {
            // Status changed, reload page
            router.reload();
        } else {
            estimatedCompletion.value = response.data.estimated_completion;
        }
    } catch (error) {
        console.error('Failed to check status:', error);
    }
};

const refreshStatus = () => {
    checkStatus();
};

const cancelTransaction = async () => {
    if (confirm('Are you sure you want to cancel this transaction?')) {
        try {
            await axios.post(route('transactions.status.cancel', props.transaction.id));
            router.reload();
        } catch (error) {
            alert('Failed to cancel transaction');
        }
    }
};

const retryTransaction = async () => {
    if (confirm('Do you want to retry this transaction?')) {
        try {
            await axios.post(route('transactions.status.retry', props.transaction.id));
            router.visit(route('transactions.status'));
        } catch (error) {
            alert('Failed to retry transaction');
        }
    }
};

const formatAmount = (amount, currency) => {
    const formatter = new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency || 'USD',
    });
    return formatter.format(amount / 100);
};

const formatFullDate = (dateString) => {
    return new Date(dateString).toLocaleString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatTimelineDate = (dateString) => {
    const date = new Date(dateString);
    return date.toLocaleString('en-US', {
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
    if (diffMins < 60) return `${diffMins} minutes`;
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

const getStatusIconClass = (status) => {
    const classes = {
        completed: 'bg-green-100 text-green-600 dark:bg-green-900 dark:text-green-400',
        pending: 'bg-yellow-100 text-yellow-600 dark:bg-yellow-900 dark:text-yellow-400',
        processing: 'bg-blue-100 text-blue-600 dark:bg-blue-900 dark:text-blue-400',
        failed: 'bg-red-100 text-red-600 dark:bg-red-900 dark:text-red-400',
        cancelled: 'bg-gray-100 text-gray-600 dark:bg-gray-900 dark:text-gray-400',
    };
    return classes[status] || 'bg-gray-100 text-gray-600';
};

const getTimelineIconClass = (status) => {
    const classes = {
        completed: 'bg-green-500 text-white',
        active: 'bg-blue-500 text-white',
        error: 'bg-red-500 text-white',
        pending: 'bg-gray-300 text-gray-600',
    };
    return classes[status] || 'bg-gray-300 text-gray-600';
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

const parseMetadata = (metadata) => {
    if (!metadata) return {};
    
    try {
        return typeof metadata === 'string' ? JSON.parse(metadata) : metadata;
    } catch {
        return {};
    }
};

const formatMetadataKey = (key) => {
    return key.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
};
</script>