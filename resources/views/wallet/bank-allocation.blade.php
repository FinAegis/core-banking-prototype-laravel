<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Bank Allocation') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 lg:p-8" x-data="bankAllocation()">
                    <div class="mb-8">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                            Configure Your Bank Distribution
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            Choose how your funds are distributed across our partner banks. This provides deposit insurance protection across multiple jurisdictions.
                        </p>
                    </div>

                    <!-- Current Allocation -->
                    <div class="mb-8">
                        <h4 class="text-md font-medium text-gray-900 dark:text-white mb-4">Current Allocation</h4>
                        <div class="space-y-4">
                            <template x-for="bank in banks" :key="bank.code">
                                <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-12 h-12 rounded-full bg-white dark:bg-gray-600 flex items-center justify-center shadow">
                                                <span class="text-xs font-medium" x-text="bank.code.substring(0, 2).toUpperCase()"></span>
                                            </div>
                                            <div>
                                                <h5 class="font-medium text-gray-900 dark:text-white" x-text="bank.name"></h5>
                                                <p class="text-sm text-gray-500 dark:text-gray-400" x-text="bank.country"></p>
                                            </div>
                                        </div>
                                        <div class="text-right">
                                            <div class="text-2xl font-bold text-gray-900 dark:text-white">
                                                <span x-text="bank.allocation"></span>%
                                            </div>
                                            <p class="text-sm text-gray-500 dark:text-gray-400">
                                                ≈ $<span x-text="((totalBalance * bank.allocation / 100) / 100).toFixed(2)"></span>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <div class="flex items-center justify-between text-sm">
                                            <span class="text-gray-600 dark:text-gray-400">Allocation</span>
                                            <input 
                                                type="range" 
                                                :min="bank.min_allocation" 
                                                :max="bank.max_allocation" 
                                                v-model="bank.allocation"
                                                @input="updateAllocation(bank)"
                                                class="w-1/2"
                                            />
                                        </div>
                                        <div class="mt-2 flex items-center space-x-4 text-xs text-gray-500 dark:text-gray-400">
                                            <span>Min: <span x-text="bank.min_allocation"></span>%</span>
                                            <span>Max: <span x-text="bank.max_allocation"></span>%</span>
                                            <span class="ml-auto">
                                                <span x-show="bank.is_primary" class="bg-indigo-100 dark:bg-indigo-900 text-indigo-700 dark:text-indigo-300 px-2 py-1 rounded">Primary</span>
                                            </span>
                                        </div>
                                    </div>
                                    <div class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                                        <p>Deposit Protection: <span class="font-medium" x-text="bank.deposit_protection"></span></p>
                                    </div>
                                </div>
                            </template>
                        </div>

                        <!-- Total Check -->
                        <div class="mt-4 p-4 rounded-lg" :class="totalAllocation === 100 ? 'bg-green-50 dark:bg-green-900/20' : 'bg-red-50 dark:bg-red-900/20'">
                            <div class="flex items-center justify-between">
                                <span class="font-medium" :class="totalAllocation === 100 ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300'">
                                    Total Allocation
                                </span>
                                <span class="text-xl font-bold" :class="totalAllocation === 100 ? 'text-green-700 dark:text-green-300' : 'text-red-700 dark:text-red-300'">
                                    <span x-text="totalAllocation"></span>%
                                </span>
                            </div>
                            <p x-show="totalAllocation !== 100" class="text-sm mt-1 text-red-600 dark:text-red-400">
                                Allocation must equal exactly 100%
                            </p>
                        </div>
                    </div>

                    <!-- Bank Details -->
                    <div class="mb-8">
                        <h4 class="text-md font-medium text-gray-900 dark:text-white mb-4">Partner Bank Details</h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="border dark:border-gray-700 rounded-lg p-4">
                                <h5 class="font-medium text-gray-900 dark:text-white mb-2">Security Features</h5>
                                <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                        </svg>
                                        Government deposit insurance up to €100,000 per bank
                                    </li>
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                        </svg>
                                        Funds held in segregated client accounts
                                    </li>
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-green-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                        </svg>
                                        Real-time balance synchronization
                                    </li>
                                </ul>
                            </div>
                            <div class="border dark:border-gray-700 rounded-lg p-4">
                                <h5 class="font-medium text-gray-900 dark:text-white mb-2">Distribution Benefits</h5>
                                <ul class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-blue-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                        </svg>
                                        Maximize deposit insurance coverage
                                    </li>
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-blue-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                        </svg>
                                        Reduce single bank exposure risk
                                    </li>
                                    <li class="flex items-start">
                                        <svg class="w-5 h-5 text-blue-500 mr-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                        </svg>
                                        Access to multiple banking networks
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex justify-end space-x-4">
                        <button type="button" @click="resetToDefault()" class="px-4 py-2 text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white">
                            Reset to Default
                        </button>
                        <button 
                            type="button" 
                            @click="saveAllocation()"
                            :disabled="totalAllocation !== 100 || saving"
                            class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 disabled:bg-gray-400 disabled:cursor-not-allowed"
                        >
                            <span x-show="!saving">Save Allocation</span>
                            <span x-show="saving">Saving...</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function bankAllocation() {
            return {
                banks: [
                    {
                        code: 'paysera',
                        name: 'Paysera LT',
                        country: 'Lithuania',
                        allocation: 40,
                        min_allocation: 20,
                        max_allocation: 60,
                        is_primary: true,
                        deposit_protection: '€100,000'
                    },
                    {
                        code: 'deutsche',
                        name: 'Deutsche Bank',
                        country: 'Germany',
                        allocation: 30,
                        min_allocation: 10,
                        max_allocation: 40,
                        is_primary: false,
                        deposit_protection: '€100,000'
                    },
                    {
                        code: 'santander',
                        name: 'Santander',
                        country: 'Spain',
                        allocation: 30,
                        min_allocation: 10,
                        max_allocation: 40,
                        is_primary: false,
                        deposit_protection: '€100,000'
                    }
                ],
                totalBalance: 0,
                saving: false,
                
                get totalAllocation() {
                    return this.banks.reduce((sum, bank) => sum + bank.allocation, 0);
                },
                
                init() {
                    // Load current allocation from API
                    this.loadCurrentAllocation();
                    // Load total balance
                    this.loadTotalBalance();
                },
                
                loadCurrentAllocation() {
                    // In production, this would fetch from /api/user/bank-preferences
                    // For now, using default values
                },
                
                loadTotalBalance() {
                    fetch('/api/accounts/{{ auth()->user()->accounts->first()->uuid ?? '' }}/balances')
                        .then(response => response.json())
                        .then(data => {
                            // Convert USD equivalent to cents
                            this.totalBalance = parseFloat(data.data.summary.total_usd_equivalent.replace(/,/g, '')) * 100;
                        });
                },
                
                updateAllocation(bank) {
                    // Auto-adjust other banks if needed
                    const total = this.totalAllocation;
                    if (total > 100) {
                        const excess = total - 100;
                        const otherBanks = this.banks.filter(b => b.code !== bank.code);
                        const adjustment = excess / otherBanks.length;
                        
                        otherBanks.forEach(b => {
                            b.allocation = Math.max(b.min_allocation, b.allocation - adjustment);
                        });
                    }
                },
                
                resetToDefault() {
                    this.banks[0].allocation = 40;
                    this.banks[1].allocation = 30;
                    this.banks[2].allocation = 30;
                },
                
                async saveAllocation() {
                    if (this.totalAllocation !== 100 || this.saving) return;
                    
                    this.saving = true;
                    
                    try {
                        // In production, POST to /api/user/bank-preferences
                        const allocations = {};
                        this.banks.forEach(bank => {
                            allocations[bank.code] = bank.allocation;
                        });
                        
                        // Simulate API call
                        await new Promise(resolve => setTimeout(resolve, 1000));
                        
                        // Show success message
                        alert('Bank allocation saved successfully!');
                    } catch (error) {
                        alert('Failed to save allocation. Please try again.');
                    } finally {
                        this.saving = false;
                    }
                }
            };
        }
    </script>
</x-app-layout>