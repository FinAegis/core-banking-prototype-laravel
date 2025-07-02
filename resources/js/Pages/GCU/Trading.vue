<template>
    <AppLayout title="GCU Trading">
        <template #header>
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Buy & Sell GCU
            </h2>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Platform Banners -->
                <platform-banners />

                <!-- GCU Summary Card -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mb-8">
                    <div class="p-6">
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Current GCU Value</h3>
                                <p class="mt-1 text-2xl font-semibold text-gray-900">
                                    {{ formatCurrency(gcuValue, 'USD') }}
                                </p>
                                <p class="text-sm" :class="gcuChange24h >= 0 ? 'text-green-600' : 'text-red-600'">
                                    {{ gcuChange24h >= 0 ? '+' : '' }}{{ gcuChange24h }}%
                                </p>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Your GCU Balance</h3>
                                <p class="mt-1 text-2xl font-semibold text-gray-900">
                                    {{ formatAmount(gcuBalance, 'GCU') }}
                                </p>
                                <p class="text-sm text-gray-600">
                                    ≈ {{ formatCurrency(gcuBalance * gcuValue, 'USD') }}
                                </p>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">24h Volume</h3>
                                <p class="mt-1 text-2xl font-semibold text-gray-900">
                                    {{ formatCurrency(volume24h, 'USD') }}
                                </p>
                            </div>
                            <div>
                                <h3 class="text-sm font-medium text-gray-500">Total Supply</h3>
                                <p class="mt-1 text-2xl font-semibold text-gray-900">
                                    {{ formatAmount(totalSupply, 'GCU') }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Trading Interface -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <!-- Buy GCU -->
                    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Buy GCU</h3>
                            
                            <form @submit.prevent="buyGCU">
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">
                                            Amount to Spend
                                        </label>
                                        <div class="mt-1 relative rounded-md shadow-sm">
                                            <input
                                                v-model="buyForm.amount"
                                                type="number"
                                                step="0.01"
                                                min="100"
                                                :max="tradingLimits.daily_buy_limit - tradingLimits.daily_buy_used"
                                                class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pr-12 sm:text-sm border-gray-300 rounded-md"
                                                placeholder="0.00"
                                                @input="updateBuyQuote"
                                            >
                                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                <span class="text-gray-500 sm:text-sm">{{ buyForm.currency }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">
                                            Currency
                                        </label>
                                        <select
                                            v-model="buyForm.currency"
                                            @change="updateBuyQuote"
                                            class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                        >
                                            <option value="EUR">EUR</option>
                                            <option value="USD">USD</option>
                                            <option value="GBP">GBP</option>
                                            <option value="CHF">CHF</option>
                                        </select>
                                    </div>

                                    <div v-if="buyQuote" class="bg-gray-50 p-4 rounded-md">
                                        <div class="space-y-2 text-sm">
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">You will receive:</span>
                                                <span class="font-medium">{{ formatAmount(buyQuote.output_amount, 'GCU') }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Exchange rate:</span>
                                                <span>1 {{ buyForm.currency }} = {{ buyQuote.exchange_rate }} GCU</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Trading fee ({{ buyQuote.fee_percentage }}%):</span>
                                                <span>{{ formatCurrency(buyQuote.fee_amount, buyForm.currency) }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="buyError" class="text-red-600 text-sm">
                                        {{ buyError }}
                                    </div>

                                    <button
                                        type="submit"
                                        :disabled="!buyForm.amount || buyForm.amount < 100 || buyProcessing"
                                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:bg-gray-300 disabled:cursor-not-allowed"
                                    >
                                        <span v-if="!buyProcessing">Buy GCU</span>
                                        <span v-else>Processing...</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Sell GCU -->
                    <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg">
                        <div class="p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-4">Sell GCU</h3>
                            
                            <form @submit.prevent="sellGCU">
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">
                                            Amount of GCU to Sell
                                        </label>
                                        <div class="mt-1 relative rounded-md shadow-sm">
                                            <input
                                                v-model="sellForm.amount"
                                                type="number"
                                                step="0.0001"
                                                min="10"
                                                :max="gcuBalance"
                                                class="focus:ring-indigo-500 focus:border-indigo-500 block w-full pr-12 sm:text-sm border-gray-300 rounded-md"
                                                placeholder="0.0000"
                                                @input="updateSellQuote"
                                            >
                                            <div class="absolute inset-y-0 right-0 pr-3 flex items-center pointer-events-none">
                                                <span class="text-gray-500 sm:text-sm">GCU</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-sm font-medium text-gray-700">
                                            Receive Currency
                                        </label>
                                        <select
                                            v-model="sellForm.currency"
                                            @change="updateSellQuote"
                                            class="mt-1 block w-full py-2 px-3 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm"
                                        >
                                            <option value="EUR">EUR</option>
                                            <option value="USD">USD</option>
                                            <option value="GBP">GBP</option>
                                            <option value="CHF">CHF</option>
                                        </select>
                                    </div>

                                    <div v-if="sellQuote" class="bg-gray-50 p-4 rounded-md">
                                        <div class="space-y-2 text-sm">
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">You will receive:</span>
                                                <span class="font-medium">{{ formatCurrency(sellQuote.output_amount, sellForm.currency) }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Exchange rate:</span>
                                                <span>1 GCU = {{ sellQuote.exchange_rate }} {{ sellForm.currency }}</span>
                                            </div>
                                            <div class="flex justify-between">
                                                <span class="text-gray-600">Trading fee ({{ sellQuote.fee_percentage }}%):</span>
                                                <span>{{ formatCurrency(sellQuote.fee_amount, sellForm.currency) }}</span>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="sellError" class="text-red-600 text-sm">
                                        {{ sellError }}
                                    </div>

                                    <button
                                        type="submit"
                                        :disabled="!sellForm.amount || sellForm.amount < 10 || sellForm.amount > gcuBalance || sellProcessing"
                                        class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-red-600 hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:bg-gray-300 disabled:cursor-not-allowed"
                                    >
                                        <span v-if="!sellProcessing">Sell GCU</span>
                                        <span v-else>Processing...</span>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Trading Limits -->
                <div class="bg-white overflow-hidden shadow-xl sm:rounded-lg mt-8">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">Your Trading Limits</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2">Daily Limits</h4>
                                <div class="space-y-2">
                                    <div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">Buy:</span>
                                            <span>{{ formatCurrency(tradingLimits.daily_buy_used, tradingLimits.limits_currency) }} / {{ formatCurrency(tradingLimits.daily_buy_limit, tradingLimits.limits_currency) }}</span>
                                        </div>
                                        <div class="mt-1 bg-gray-200 rounded-full h-2">
                                            <div 
                                                class="bg-indigo-600 h-2 rounded-full" 
                                                :style="`width: ${(tradingLimits.daily_buy_used / tradingLimits.daily_buy_limit) * 100}%`"
                                            ></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">Sell:</span>
                                            <span>{{ formatCurrency(tradingLimits.daily_sell_used, tradingLimits.limits_currency) }} / {{ formatCurrency(tradingLimits.daily_sell_limit, tradingLimits.limits_currency) }}</span>
                                        </div>
                                        <div class="mt-1 bg-gray-200 rounded-full h-2">
                                            <div 
                                                class="bg-red-600 h-2 rounded-full" 
                                                :style="`width: ${(tradingLimits.daily_sell_used / tradingLimits.daily_sell_limit) * 100}%`"
                                            ></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <h4 class="text-sm font-medium text-gray-700 mb-2">Monthly Limits</h4>
                                <div class="space-y-2">
                                    <div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">Buy:</span>
                                            <span>{{ formatCurrency(tradingLimits.monthly_buy_used, tradingLimits.limits_currency) }} / {{ formatCurrency(tradingLimits.monthly_buy_limit, tradingLimits.limits_currency) }}</span>
                                        </div>
                                        <div class="mt-1 bg-gray-200 rounded-full h-2">
                                            <div 
                                                class="bg-indigo-600 h-2 rounded-full" 
                                                :style="`width: ${(tradingLimits.monthly_buy_used / tradingLimits.monthly_buy_limit) * 100}%`"
                                            ></div>
                                        </div>
                                    </div>
                                    <div>
                                        <div class="flex justify-between text-sm">
                                            <span class="text-gray-600">Sell:</span>
                                            <span>{{ formatCurrency(tradingLimits.monthly_sell_used, tradingLimits.limits_currency) }} / {{ formatCurrency(tradingLimits.monthly_sell_limit, tradingLimits.limits_currency) }}</span>
                                        </div>
                                        <div class="mt-1 bg-gray-200 rounded-full h-2">
                                            <div 
                                                class="bg-red-600 h-2 rounded-full" 
                                                :style="`width: ${(tradingLimits.monthly_sell_used / tradingLimits.monthly_sell_limit) * 100}%`"
                                            ></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="mt-4 text-sm text-gray-600">
                            <p>KYC Level: {{ kycLevelName }} (Level {{ tradingLimits.kyc_level }})</p>
                            <p class="mt-1">
                                <a href="#" class="text-indigo-600 hover:text-indigo-500">Increase your limits</a>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script>
import { defineComponent, ref, computed, onMounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import PlatformBanners from '@/Components/PlatformBanners.vue'
import axios from 'axios'
import { debounce } from 'lodash'

export default defineComponent({
    components: {
        AppLayout,
        PlatformBanners,
    },

    setup() {
        // State
        const gcuValue = ref(1.0975)
        const gcuChange24h = ref(0.25)
        const gcuBalance = ref(0)
        const volume24h = ref(1234567.89)
        const totalSupply = ref(10000000)
        
        const buyForm = ref({
            amount: '',
            currency: 'EUR',
        })
        
        const sellForm = ref({
            amount: '',
            currency: 'EUR',
        })
        
        const buyQuote = ref(null)
        const sellQuote = ref(null)
        const buyError = ref('')
        const sellError = ref('')
        const buyProcessing = ref(false)
        const sellProcessing = ref(false)
        
        const tradingLimits = ref({
            daily_buy_limit: 10000,
            daily_sell_limit: 10000,
            daily_buy_used: 0,
            daily_sell_used: 0,
            monthly_buy_limit: 100000,
            monthly_sell_limit: 100000,
            monthly_buy_used: 0,
            monthly_sell_used: 0,
            minimum_buy_amount: 100,
            minimum_sell_amount: 10,
            kyc_level: 2,
            limits_currency: 'EUR',
        })

        // Computed
        const kycLevelName = computed(() => {
            const levels = {
                0: 'Unverified',
                1: 'Basic',
                2: 'Verified',
                3: 'Enhanced',
                4: 'Corporate',
            }
            return levels[tradingLimits.value.kyc_level] || 'Unknown'
        })

        // Methods
        const formatCurrency = (amount, currency) => {
            return new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: currency,
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            }).format(amount)
        }

        const formatAmount = (amount, currency) => {
            if (currency === 'GCU') {
                return new Intl.NumberFormat('en-US', {
                    minimumFractionDigits: 4,
                    maximumFractionDigits: 4,
                }).format(amount) + ' GCU'
            }
            return formatCurrency(amount, currency)
        }

        const loadGCUData = async () => {
            try {
                const response = await axios.get('/api/v2/gcu')
                const data = response.data.data
                gcuValue.value = data.current_value
                gcuChange24h.value = data.statistics['24h_change']
                totalSupply.value = data.statistics.total_supply
            } catch (error) {
                console.error('Failed to load GCU data:', error)
            }
        }

        const loadBalance = async () => {
            try {
                const response = await axios.get('/api/v2/accounts')
                const primaryAccount = response.data.data.find(acc => acc.is_primary)
                if (primaryAccount) {
                    const balanceResponse = await axios.get(`/api/v2/accounts/${primaryAccount.uuid}/balances`)
                    const gcuBalanceData = balanceResponse.data.data.find(b => b.asset_code === 'GCU')
                    gcuBalance.value = gcuBalanceData ? gcuBalanceData.balance : 0
                }
            } catch (error) {
                console.error('Failed to load balance:', error)
            }
        }

        const loadTradingLimits = async () => {
            try {
                const response = await axios.get('/api/v2/gcu/trading-limits')
                tradingLimits.value = response.data.data
            } catch (error) {
                console.error('Failed to load trading limits:', error)
            }
        }

        const getQuote = async (operation, amount, currency) => {
            if (!amount || amount <= 0) return null
            
            try {
                const response = await axios.get('/api/v2/gcu/quote', {
                    params: {
                        operation,
                        amount,
                        currency,
                    }
                })
                return response.data.data
            } catch (error) {
                console.error('Failed to get quote:', error)
                return null
            }
        }

        const updateBuyQuote = debounce(async () => {
            buyError.value = ''
            if (!buyForm.value.amount || buyForm.value.amount < 100) {
                buyQuote.value = null
                return
            }
            
            buyQuote.value = await getQuote('buy', buyForm.value.amount, buyForm.value.currency)
        }, 500)

        const updateSellQuote = debounce(async () => {
            sellError.value = ''
            if (!sellForm.value.amount || sellForm.value.amount < 10) {
                sellQuote.value = null
                return
            }
            
            sellQuote.value = await getQuote('sell', sellForm.value.amount, sellForm.value.currency)
        }, 500)

        const buyGCU = async () => {
            buyError.value = ''
            buyProcessing.value = true
            
            try {
                const response = await axios.post('/api/v2/gcu/buy', {
                    amount: parseFloat(buyForm.value.amount),
                    currency: buyForm.value.currency,
                })
                
                // Show success message
                alert(`Successfully purchased ${response.data.data.received_amount} GCU!`)
                
                // Reset form
                buyForm.value.amount = ''
                buyQuote.value = null
                
                // Reload data
                await Promise.all([
                    loadBalance(),
                    loadTradingLimits(),
                ])
            } catch (error) {
                buyError.value = error.response?.data?.message || 'Failed to complete purchase'
            } finally {
                buyProcessing.value = false
            }
        }

        const sellGCU = async () => {
            sellError.value = ''
            sellProcessing.value = true
            
            try {
                const response = await axios.post('/api/v2/gcu/sell', {
                    amount: parseFloat(sellForm.value.amount),
                    currency: sellForm.value.currency,
                })
                
                // Show success message
                alert(`Successfully sold ${response.data.data.sold_amount} GCU for ${response.data.data.received_amount} ${response.data.data.received_currency}!`)
                
                // Reset form
                sellForm.value.amount = ''
                sellQuote.value = null
                
                // Reload data
                await Promise.all([
                    loadBalance(),
                    loadTradingLimits(),
                ])
            } catch (error) {
                sellError.value = error.response?.data?.message || 'Failed to complete sale'
            } finally {
                sellProcessing.value = false
            }
        }

        // Lifecycle
        onMounted(async () => {
            await Promise.all([
                loadGCUData(),
                loadBalance(),
                loadTradingLimits(),
            ])
        })

        return {
            gcuValue,
            gcuChange24h,
            gcuBalance,
            volume24h,
            totalSupply,
            buyForm,
            sellForm,
            buyQuote,
            sellQuote,
            buyError,
            sellError,
            buyProcessing,
            sellProcessing,
            tradingLimits,
            kycLevelName,
            formatCurrency,
            formatAmount,
            updateBuyQuote,
            updateSellQuote,
            buyGCU,
            sellGCU,
        }
    },
})
</script>