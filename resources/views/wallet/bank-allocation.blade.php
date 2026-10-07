<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Bank Allocation (demo)') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 lg:p-8" x-data="bankAllocation()">
                    {{-- F4 GCU demo notice (see docs/REGULATORY-CLAIMS.md) --}}
                    <div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-700 dark:bg-amber-900/20 dark:text-amber-200" role="note">
                        <p class="font-semibold">Simulated demo — no real value; not an offered token.</p>
                        <p class="mt-1">No funds are held at any bank and no deposit insurance applies. Bank names shown are illustrative placeholders; no partnership or endorsement is implied.</p>
                    </div>

                    <div class="mb-8">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                            Demo Custodian Allocation (simulated)
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            Demo: simulate how balances could be allocated across custodians. No funds are held at any bank and no deposit insurance applies.
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
                                                <span class="text-xs font-medium" x-text="bank.name.slice(-1).toUpperCase()"></span>
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
                                        </div>
                                    </div>
                                    <div class="mt-3">
                                        <div class="flex items-center justify-between text-sm">
                                            <span class="text-gray-600 dark:text-gray-400">Allocation</span>
                                            <input 
                                                type="range" 
                                                :min="bank.min_allocation" 
                                                :max="bank.max_allocation" 
                                                x-model="bank.allocation"
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
                            <span x-show="!saving">Save demo allocation</span>
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
                        code: 'PAYSERA',
                        name: 'Demo Bank A',
                        country: 'Illustrative placeholder',
                        allocation: 40,
                        min_allocation: 20,
                        max_allocation: 60,
                        is_primary: true
                    },
                    {
                        code: 'DEUTSCHE',
                        name: 'Demo Bank B',
                        country: 'Illustrative placeholder',
                        allocation: 30,
                        min_allocation: 10,
                        max_allocation: 40,
                        is_primary: false
                    },
                    {
                        code: 'SANTANDER',
                        name: 'Demo Bank C',
                        country: 'Illustrative placeholder',
                        allocation: 30,
                        min_allocation: 10,
                        max_allocation: 40,
                        is_primary: false
                    }
                ],
                totalBalance: 0,
                saving: false,
                
                get totalAllocation() {
                    return this.banks.reduce((sum, bank) => sum + parseInt(bank.allocation), 0);
                },
                
                init() {
                    // Load current allocation from API
                    this.loadCurrentAllocation();
                    // Load total balance
                    this.loadTotalBalance();
                },
                
                async loadCurrentAllocation() {
                    try {
                        const response = await fetch('/api/bank-allocations', {
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                                @if(session('api_token'))
                                'Authorization': 'Bearer {{ session("api_token") }}'
                                @endif
                            },
                            credentials: 'same-origin'
                        });
                        
                        if (!response.ok) {
                            throw new Error('Failed to fetch bank allocations');
                        }
                        
                        const data = await response.json();
                        
                        if (data.data && data.data.allocations && data.data.allocations.length > 0) {
                            // Update banks with current allocations
                            data.data.allocations.forEach(allocation => {
                                const bank = this.banks.find(b => b.code === allocation.bank_code);
                                if (bank) {
                                    bank.allocation = allocation.allocation_percentage;
                                    bank.is_primary = allocation.is_primary;
                                }
                            });
                        }
                    } catch (error) {
                        console.error('Error loading bank allocations:', error);
                        // Keep default values
                    }
                },
                
                loadTotalBalance() {
                    @if(auth()->user()->accounts->first())
                        fetch('/api/accounts/{{ auth()->user()->accounts->first()->uuid }}/balances', {
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            },
                            credentials: 'same-origin'
                        })
                            .then(response => {
                                if (!response.ok) {
                                    throw new Error('Failed to fetch balance');
                                }
                                return response.json();
                            })
                            .then(data => {
                                // Convert USD equivalent to cents
                                if (data.data && data.data.summary && data.data.summary.total_usd_equivalent) {
                                    this.totalBalance = parseFloat(data.data.summary.total_usd_equivalent.replace(/[,$]/g, '')) * 100;
                                }
                            })
                            .catch(error => {
                                console.error('Error loading balance:', error);
                                this.totalBalance = 0;
                            });
                    @else
                        console.warn('No account found for user');
                        this.totalBalance = 0;
                    @endif
                },
                
                updateAllocation(bank) {
                    // Ensure the value is within bounds
                    bank.allocation = Math.max(bank.min_allocation, Math.min(bank.max_allocation, parseInt(bank.allocation)));
                    // Don't auto-adjust other banks - let user manually balance
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
                        // Map bank codes to uppercase to match the API expectations
                        const allocations = {};
                        this.banks.forEach(bank => {
                            allocations[bank.code] = bank.allocation;
                        });
                        
                        // Find the primary bank
                        const primaryBank = this.banks.find(b => b.is_primary);
                        
                        const response = await fetch('/api/bank-allocations', {
                            method: 'PUT',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content'),
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({ 
                                allocations: allocations,
                                primary_bank: primaryBank ? primaryBank.code : 'PAYSERA'
                            })
                        });
                        
                        if (!response.ok) {
                            const errorData = await response.json();
                            throw new Error(errorData.message || 'Failed to save allocations');
                        }
                        
                        const data = await response.json();
                        
                        // Show success message
                        alert('Demo allocation saved (simulated).');
                        
                        // Reload current allocation to confirm changes
                        await this.loadCurrentAllocation();
                        
                    } catch (error) {
                        console.error('Error saving allocation:', error);
                        alert('Failed to save allocation: ' + error.message);
                    } finally {
                        this.saving = false;
                    }
                }
            };
        }
    </script>
</x-app-layout>