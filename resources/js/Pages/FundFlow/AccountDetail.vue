<template>
    <AppLayout title="Account Fund Flow">
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                    {{ account.name }} - Fund Flow Analysis
                </h2>
                <Link :href="route('fund-flow.index')" 
                      class="text-sm text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300">
                    ← Back to Fund Flow
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Account Summary -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-8">
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                            <div>
                                <div class="text-gray-500 dark:text-gray-400 text-sm">Current Balance</div>
                                <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                    {{ formatCurrency(totalBalance) }}
                                </div>
                            </div>
                            <div>
                                <div class="text-gray-500 dark:text-gray-400 text-sm">Total Inflow</div>
                                <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                                    {{ formatCurrency(flowBalance.total_inflow) }}
                                </div>
                            </div>
                            <div>
                                <div class="text-gray-500 dark:text-gray-400 text-sm">Total Outflow</div>
                                <div class="text-2xl font-bold text-red-600 dark:text-red-400">
                                    {{ formatCurrency(flowBalance.total_outflow) }}
                                </div>
                            </div>
                            <div>
                                <div class="text-gray-500 dark:text-gray-400 text-sm">Net Flow</div>
                                <div :class="flowBalance.net_flow >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'" 
                                     class="text-2xl font-bold">
                                    {{ formatCurrency(flowBalance.net_flow) }}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Inflows -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                                Recent Inflows
                                <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">({{ inflows.length }})</span>
                            </h3>
                            <div class="space-y-3 max-h-96 overflow-y-auto">
                                <div v-for="flow in inflows" :key="flow.id" 
                                     class="border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-3">
                                            <div class="bg-green-100 text-green-600 dark:bg-green-900 dark:text-green-400 w-8 h-8 rounded-full flex items-center justify-center">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    {{ flow.description || 'Deposit' }}
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ formatDate(flow.created_at) }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm font-medium text-green-600 dark:text-green-400">
                                                +{{ formatCurrency(flow.amount, flow.currency) }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ flow.reference }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="inflows.length === 0" class="text-center text-gray-500 dark:text-gray-400 py-8">
                                    No inflows found
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Outflows -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                                Recent Outflows
                                <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">({{ outflows.length }})</span>
                            </h3>
                            <div class="space-y-3 max-h-96 overflow-y-auto">
                                <div v-for="flow in outflows" :key="flow.id" 
                                     class="border border-gray-200 dark:border-gray-700 rounded-lg p-3">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-3">
                                            <div class="bg-red-100 text-red-600 dark:bg-red-900 dark:text-red-400 w-8 h-8 rounded-full flex items-center justify-center">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    {{ flow.description || formatFlowType(flow.type) }}
                                                </div>
                                                <div class="text-xs text-gray-500 dark:text-gray-400">
                                                    {{ formatDate(flow.created_at) }}
                                                </div>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-sm font-medium text-red-600 dark:text-red-400">
                                                -{{ formatCurrency(Math.abs(flow.amount), flow.currency) }}
                                            </div>
                                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                                {{ flow.reference }}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="outflows.length === 0" class="text-center text-gray-500 dark:text-gray-400 py-8">
                                    No outflows found
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Flow Ratio Visualization -->
                <div class="mt-8 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Flow Analysis</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Flow Distribution</h4>
                                <div class="space-y-2">
                                    <div>
                                        <div class="flex justify-between text-sm mb-1">
                                            <span class="text-gray-600 dark:text-gray-400">Inflows</span>
                                            <span class="text-gray-900 dark:text-gray-100">
                                                {{ ((flowBalance.total_inflow / (flowBalance.total_inflow + flowBalance.total_outflow)) * 100).toFixed(1) }}%
                                            </span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-green-600 h-2 rounded-full" 
                                                 :style="`width: ${(flowBalance.total_inflow / (flowBalance.total_inflow + flowBalance.total_outflow)) * 100}%`"></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between text-sm mb-1">
                                            <span class="text-gray-600 dark:text-gray-400">Outflows</span>
                                            <span class="text-gray-900 dark:text-gray-100">
                                                {{ ((flowBalance.total_outflow / (flowBalance.total_inflow + flowBalance.total_outflow)) * 100).toFixed(1) }}%
                                            </span>
                                        </div>
                                        <div class="w-full bg-gray-200 rounded-full h-2">
                                            <div class="bg-red-600 h-2 rounded-full" 
                                                 :style="`width: ${(flowBalance.total_outflow / (flowBalance.total_inflow + flowBalance.total_outflow)) * 100}%`"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Key Metrics</h4>
                                <dl class="space-y-2">
                                    <div class="flex justify-between">
                                        <dt class="text-sm text-gray-600 dark:text-gray-400">Flow Ratio</dt>
                                        <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ flowBalance.flow_ratio || 'N/A' }}
                                        </dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-sm text-gray-600 dark:text-gray-400">Avg. Inflow</dt>
                                        <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ formatCurrency(inflows.length > 0 ? flowBalance.total_inflow / inflows.length : 0) }}
                                        </dd>
                                    </div>
                                    <div class="flex justify-between">
                                        <dt class="text-sm text-gray-600 dark:text-gray-400">Avg. Outflow</dt>
                                        <dd class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ formatCurrency(outflows.length > 0 ? flowBalance.total_outflow / outflows.length : 0) }}
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    account: Object,
    inflows: Array,
    outflows: Array,
    flowBalance: Object,
    counterparties: Object,
});

const totalBalance = computed(() => {
    return props.account.balances.reduce((sum, balance) => sum + balance.balance, 0);
});

const formatCurrency = (amount, currency = 'USD') => {
    const formatter = new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: currency,
    });
    return formatter.format(amount / 100);
};

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatFlowType = (type) => {
    const types = {
        deposit: 'Deposit',
        withdrawal: 'Withdrawal',
        transfer: 'Transfer',
        exchange: 'Exchange',
    };
    return types[type] || type;
};
</script>