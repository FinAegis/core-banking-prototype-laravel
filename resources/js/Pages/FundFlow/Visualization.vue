<template>
    <AppLayout title="Fund Flow Visualization">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                Fund Flow Visualization
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-gray-500 dark:text-gray-400 text-sm">Total Inflow</div>
                                <div class="text-2xl font-bold text-green-600 dark:text-green-400">
                                    {{ formatCurrency(statistics.total_inflow) }}
                                </div>
                            </div>
                            <div class="text-green-600 dark:text-green-400">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-gray-500 dark:text-gray-400 text-sm">Total Outflow</div>
                                <div class="text-2xl font-bold text-red-600 dark:text-red-400">
                                    {{ formatCurrency(statistics.total_outflow) }}
                                </div>
                            </div>
                            <div class="text-red-600 dark:text-red-400">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 13l-5 5m0 0l-5-5m5 5V6"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <div class="text-gray-500 dark:text-gray-400 text-sm">Net Flow</div>
                                <div :class="statistics.net_flow >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'" 
                                     class="text-2xl font-bold">
                                    {{ formatCurrency(statistics.net_flow) }}
                                </div>
                            </div>
                            <div :class="statistics.net_flow >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                                </svg>
                            </div>
                        </div>
                    </div>
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6">
                        <div class="text-gray-500 dark:text-gray-400 text-sm">Total Flows</div>
                        <div class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ statistics.total_flows }}</div>
                        <div class="text-sm text-gray-500 dark:text-gray-400 mt-1">
                            {{ statistics.active_days }} active days
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 mb-8">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Time Period</label>
                            <select v-model="filters.period" @change="applyFilters" 
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                <option value="24hours">Last 24 Hours</option>
                                <option value="7days">Last 7 Days</option>
                                <option value="30days">Last 30 Days</option>
                                <option value="90days">Last 90 Days</option>
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
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Flow Type</label>
                            <select v-model="filters.flow_type" @change="applyFilters" 
                                    class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300">
                                <option value="all">All Types</option>
                                <option value="deposit">Deposits</option>
                                <option value="withdrawal">Withdrawals</option>
                                <option value="transfer">Transfers</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Flow Chart -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Daily Fund Flow</h3>
                            <div class="h-64">
                                <canvas ref="flowChart"></canvas>
                            </div>
                        </div>
                    </div>

                    <!-- Network Visualization -->
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Account Network</h3>
                            <div class="h-64 relative" ref="networkContainer">
                                <div class="absolute inset-0 flex items-center justify-center text-gray-500 dark:text-gray-400">
                                    <svg class="animate-spin h-8 w-8 mr-3" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    Loading network visualization...
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Flows -->
                <div class="mt-8 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6">
                        <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">Recent Fund Flows</h3>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Flow
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            From
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            To
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Amount
                                        </th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Date
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-for="flow in flowData.slice(0, 10)" :key="flow.id">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div :class="getFlowIcon(flow.type)" 
                                                     class="w-8 h-8 rounded-full flex items-center justify-center mr-3">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" 
                                                              :d="getFlowPath(flow.type)"></path>
                                                    </svg>
                                                </div>
                                                <div class="text-sm font-medium text-gray-900 dark:text-gray-100">
                                                    {{ formatFlowType(flow.type) }}
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ flow.from.name }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 dark:text-gray-100">
                                            {{ flow.to.name }}
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <span :class="flow.type === 'deposit' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                                {{ formatCurrency(flow.amount, flow.currency) }}
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ formatDate(flow.timestamp) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        
                        <div v-if="flowData.length > 10" class="mt-4 text-center">
                            <button @click="showAllFlows = true" 
                                    class="text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 text-sm font-medium">
                                View all {{ flowData.length }} flows →
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Export Button -->
                <div class="mt-6 flex justify-end">
                    <button @click="exportData" 
                            class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition">
                        Export Flow Data
                    </button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import { ref, onMounted, watch, nextTick } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Chart from 'chart.js/auto';
import * as d3 from 'd3';

const props = defineProps({
    accounts: Array,
    flowData: Array,
    statistics: Object,
    networkData: Object,
    chartData: Array,
    filters: Object,
});

const filters = ref({ ...props.filters });
const flowChart = ref(null);
const networkContainer = ref(null);
const showAllFlows = ref(false);

let chartInstance = null;
let networkSimulation = null;

onMounted(() => {
    initializeChart();
    initializeNetwork();
});

watch(() => props.chartData, () => {
    updateChart();
});

watch(() => props.networkData, () => {
    updateNetwork();
});

const initializeChart = () => {
    const ctx = flowChart.value.getContext('2d');
    
    chartInstance = new Chart(ctx, {
        type: 'line',
        data: {
            labels: props.chartData.map(d => formatChartDate(d.date)),
            datasets: [
                {
                    label: 'Inflow',
                    data: props.chartData.map(d => d.inflow / 100),
                    borderColor: 'rgb(34, 197, 94)',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    tension: 0.4,
                },
                {
                    label: 'Outflow',
                    data: props.chartData.map(d => d.outflow / 100),
                    borderColor: 'rgb(239, 68, 68)',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    tension: 0.4,
                },
                {
                    label: 'Net Flow',
                    data: props.chartData.map(d => d.net / 100),
                    borderColor: 'rgb(59, 130, 246)',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                },
                tooltip: {
                    mode: 'index',
                    intersect: false,
                },
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        callback: function(value) {
                            return '$' + value.toLocaleString();
                        },
                    },
                },
            },
        },
    });
};

const updateChart = () => {
    if (!chartInstance) return;
    
    chartInstance.data.labels = props.chartData.map(d => formatChartDate(d.date));
    chartInstance.data.datasets[0].data = props.chartData.map(d => d.inflow / 100);
    chartInstance.data.datasets[1].data = props.chartData.map(d => d.outflow / 100);
    chartInstance.data.datasets[2].data = props.chartData.map(d => d.net / 100);
    chartInstance.update();
};

const initializeNetwork = () => {
    // Simple network visualization using D3.js
    // In production, you might use a more sophisticated library like vis.js or cytoscape.js
    const container = networkContainer.value;
    const width = container.offsetWidth;
    const height = 256;
    
    // Clear existing content
    d3.select(container).selectAll('*').remove();
    
    const svg = d3.select(container)
        .append('svg')
        .attr('width', width)
        .attr('height', height);
    
    // Create force simulation
    networkSimulation = d3.forceSimulation(props.networkData.nodes)
        .force('link', d3.forceLink(props.networkData.edges).id(d => d.id).distance(100))
        .force('charge', d3.forceManyBody().strength(-300))
        .force('center', d3.forceCenter(width / 2, height / 2));
    
    // Create links
    const link = svg.append('g')
        .selectAll('line')
        .data(props.networkData.edges)
        .enter().append('line')
        .attr('stroke', '#999')
        .attr('stroke-opacity', 0.6)
        .attr('stroke-width', d => Math.sqrt(d.value / 10000));
    
    // Create nodes
    const node = svg.append('g')
        .selectAll('circle')
        .data(props.networkData.nodes)
        .enter().append('circle')
        .attr('r', d => d.type === 'account' ? 10 : 8)
        .attr('fill', d => d.type === 'account' ? '#3B82F6' : '#6B7280')
        .call(d3.drag()
            .on('start', dragstarted)
            .on('drag', dragged)
            .on('end', dragended));
    
    // Add labels
    const label = svg.append('g')
        .selectAll('text')
        .data(props.networkData.nodes)
        .enter().append('text')
        .text(d => d.label)
        .attr('font-size', '10px')
        .attr('dx', 12)
        .attr('dy', 4);
    
    // Update positions on tick
    networkSimulation.on('tick', () => {
        link
            .attr('x1', d => d.source.x)
            .attr('y1', d => d.source.y)
            .attr('x2', d => d.target.x)
            .attr('y2', d => d.target.y);
        
        node
            .attr('cx', d => d.x)
            .attr('cy', d => d.y);
        
        label
            .attr('x', d => d.x)
            .attr('y', d => d.y);
    });
};

const updateNetwork = () => {
    // Re-initialize network with new data
    if (networkContainer.value) {
        initializeNetwork();
    }
};

const dragstarted = (event, d) => {
    if (!event.active) networkSimulation.alphaTarget(0.3).restart();
    d.fx = d.x;
    d.fy = d.y;
};

const dragged = (event, d) => {
    d.fx = event.x;
    d.fy = event.y;
};

const dragended = (event, d) => {
    if (!event.active) networkSimulation.alphaTarget(0);
    d.fx = null;
    d.fy = null;
};

const applyFilters = () => {
    router.get(route('fund-flow.index'), filters.value, {
        preserveState: true,
        preserveScroll: true,
    });
};

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
        hour: '2-digit',
        minute: '2-digit',
    });
};

const formatChartDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
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

const getFlowIcon = (type) => {
    const icons = {
        deposit: 'bg-green-100 text-green-600 dark:bg-green-900 dark:text-green-400',
        withdrawal: 'bg-red-100 text-red-600 dark:bg-red-900 dark:text-red-400',
        transfer: 'bg-blue-100 text-blue-600 dark:bg-blue-900 dark:text-blue-400',
        exchange: 'bg-purple-100 text-purple-600 dark:bg-purple-900 dark:text-purple-400',
    };
    return icons[type] || 'bg-gray-100 text-gray-600';
};

const getFlowPath = (type) => {
    const paths = {
        deposit: 'M7 11l5-5m0 0l5 5m-5-5v12',
        withdrawal: 'M17 13l-5 5m0 0l-5-5m5 5V6',
        transfer: 'M8 7h12m0 0l-4-4m4 4l-4 4',
        exchange: 'M8 7h12m0 0l-4-4m4 4l-4 4M16 17H4m0 0l4 4m-4-4l4-4',
    };
    return paths[type] || 'M8 7h12m0 0l-4-4m4 4l-4 4';
};

const exportData = async () => {
    try {
        const response = await axios.get(route('fund-flow.data'), {
            params: filters.value,
        });
        
        // Convert to CSV
        const csv = convertToCSV(response.data.flows);
        
        // Download
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `fund-flow-${filters.value.period}.csv`;
        a.click();
        window.URL.revokeObjectURL(url);
    } catch (error) {
        console.error('Export failed:', error);
    }
};

const convertToCSV = (data) => {
    const headers = ['Type', 'From', 'To', 'Amount', 'Currency', 'Date'];
    const rows = data.map(flow => [
        flow.type,
        flow.from.name,
        flow.to.name,
        flow.amount / 100,
        flow.currency,
        new Date(flow.timestamp).toISOString(),
    ]);
    
    return [headers, ...rows].map(row => row.join(',')).join('\n');
};
</script>

<style scoped>
/* Add any custom styles for the network visualization */
</style>