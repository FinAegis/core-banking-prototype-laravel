<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Burn Stablecoin') }}
            </h2>
            <a href="{{ route('stablecoin-operations.index') }}" 
               class="text-gray-600 hover:text-gray-900">
                ← Back to Operations
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @if($stablecoinInfo)
                <!-- Stablecoin Info -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">{{ $stablecoinInfo['name'] }}</h3>
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Current Supply</p>
                                <p class="font-medium">${{ number_format($stablecoinInfo['total_supply'] / 100, 0) }}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Collateral Ratio</p>
                                <p class="font-medium">{{ $stablecoinInfo['collateral_ratio'] }}%</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Status</p>
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full
                                    {{ $stablecoinInfo['active'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' }}">
                                    {{ $stablecoinInfo['active'] ? 'Active' : 'Inactive' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Burn Form -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('stablecoin-operations.burn.process') }}">
                        @csrf
                        <input type="hidden" name="stablecoin" value="{{ $stablecoin }}">
                        
                        <!-- Source Account -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Source Account
                            </label>
                            <select name="source_account" 
                                    id="source-account"
                                    class="w-full rounded-md border-gray-300 shadow-sm"
                                    required>
                                <option value="">Select source account</option>
                                @foreach($operatorAccounts as $account)
                                    <option value="{{ $account->uuid }}" 
                                            data-balance="{{ $account->balances->first()->balance ?? 0 }}"
                                            {{ old('source_account') == $account->uuid ? 'selected' : '' }}>
                                        {{ $account->name }} - {{ $account->type }}
                                        @if($account->balances->first())
                                            (Balance: ${{ number_format($account->balances->first()->balance / 100, 2) }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('source_account')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Amount to Burn -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Amount to Burn
                            </label>
                            <div class="relative">
                                <input type="number" 
                                       name="amount" 
                                       id="burn-amount"
                                       value="{{ old('amount') }}"
                                       class="w-full rounded-md border-gray-300 shadow-sm pl-8"
                                       placeholder="0.00"
                                       step="0.01"
                                       min="100"
                                       max="1000000"
                                       required>
                                <span class="absolute left-3 top-3 text-gray-500">$</span>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">
                                Available balance: <span id="available-balance">0.00</span> {{ $stablecoin }}
                            </p>
                            @error('amount')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Return Collateral -->
                        <div class="mb-6">
                            <label class="flex items-center">
                                <input type="checkbox" 
                                       name="return_collateral" 
                                       id="return-collateral"
                                       value="1"
                                       class="rounded border-gray-300 text-indigo-600"
                                       {{ old('return_collateral') ? 'checked' : '' }}>
                                <span class="ml-2 text-sm text-gray-700 dark:text-gray-300">
                                    Return collateral to account
                                </span>
                            </label>
                            <p class="mt-1 text-sm text-gray-500">
                                If checked, collateral will be released back to your account (minus fees)
                            </p>
                        </div>

                        <!-- Collateral Asset (shown when return_collateral is checked) -->
                        <div class="mb-6" id="collateral-asset-container" style="display: none;">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Collateral Asset to Return
                            </label>
                            <select name="collateral_asset" 
                                    id="collateral-asset"
                                    class="w-full rounded-md border-gray-300 shadow-sm">
                                <option value="">Select collateral asset</option>
                                <option value="USD" {{ old('collateral_asset') == 'USD' ? 'selected' : '' }}>US Dollar (USD)</option>
                                <option value="EUR" {{ old('collateral_asset') == 'EUR' ? 'selected' : '' }}>Euro (EUR)</option>
                                <option value="GBP" {{ old('collateral_asset') == 'GBP' ? 'selected' : '' }}>British Pound (GBP)</option>
                            </select>
                            <p class="mt-1 text-sm text-gray-500">
                                Estimated return: <span id="collateral-return">0.00</span> <span id="collateral-currency"></span>
                            </p>
                            @error('collateral_asset')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Reason -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Reason for Burning
                            </label>
                            <input type="text" 
                                   name="reason" 
                                   value="{{ old('reason') }}"
                                   class="w-full rounded-md border-gray-300 shadow-sm"
                                   placeholder="e.g., Excess supply reduction"
                                   maxlength="255"
                                   required>
                            @error('reason')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Authorization Code -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Authorization Code
                            </label>
                            <input type="password" 
                                   name="authorization_code" 
                                   class="w-full rounded-md border-gray-300 shadow-sm"
                                   placeholder="Enter authorization code"
                                   required>
                            @error('authorization_code')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Warning -->
                        <div class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4 mb-6">
                            <div class="flex">
                                <svg class="h-5 w-5 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-red-800 dark:text-red-200">
                                        Important Notice
                                    </h3>
                                    <p class="mt-1 text-sm text-red-700 dark:text-red-300">
                                        This action will permanently burn {{ $stablecoin }} tokens, reducing the total supply. 
                                        This operation cannot be undone. Ensure all parameters are correct before proceeding.
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex justify-end space-x-3">
                            <a href="{{ route('stablecoin-operations.index') }}" 
                               class="px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                                Cancel
                            </a>
                            <button type="submit" 
                                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">
                                Burn Stablecoin
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const sourceAccountSelect = document.getElementById('source-account');
            const burnAmountInput = document.getElementById('burn-amount');
            const availableBalanceSpan = document.getElementById('available-balance');
            const returnCollateralCheckbox = document.getElementById('return-collateral');
            const collateralAssetContainer = document.getElementById('collateral-asset-container');
            const collateralAssetSelect = document.getElementById('collateral-asset');
            const collateralReturnSpan = document.getElementById('collateral-return');
            const collateralCurrencySpan = document.getElementById('collateral-currency');
            
            const collateralRatio = {{ $stablecoinInfo['collateral_ratio'] }} / 100;
            const rates = {
                'USD': 1,
                'EUR': 0.92,
                'GBP': 0.79
            };
            
            // Update available balance
            sourceAccountSelect.addEventListener('change', function() {
                const selectedOption = this.options[this.selectedIndex];
                const balance = parseFloat(selectedOption.dataset.balance) || 0;
                availableBalanceSpan.textContent = (balance / 100).toFixed(2);
                
                // Clear amount if it exceeds balance
                const currentAmount = parseFloat(burnAmountInput.value) || 0;
                if (currentAmount > balance / 100) {
                    burnAmountInput.value = '';
                }
            });
            
            // Toggle collateral asset selection
            returnCollateralCheckbox.addEventListener('change', function() {
                collateralAssetContainer.style.display = this.checked ? 'block' : 'none';
                if (!this.checked) {
                    collateralAssetSelect.value = '';
                }
            });
            
            // Calculate collateral return
            function calculateCollateralReturn() {
                const burnAmount = parseFloat(burnAmountInput.value) || 0;
                const collateralAsset = collateralAssetSelect.value;
                
                if (burnAmount > 0 && collateralAsset && rates[collateralAsset]) {
                    // Calculate base collateral amount
                    const baseAmount = burnAmount * collateralRatio;
                    // Convert to selected currency
                    const collateralAmount = baseAmount / rates[collateralAsset];
                    // Apply 95% return rate (5% fee)
                    const returnAmount = collateralAmount * 0.95;
                    
                    collateralReturnSpan.textContent = returnAmount.toFixed(2);
                    collateralCurrencySpan.textContent = collateralAsset;
                }
            }
            
            burnAmountInput.addEventListener('input', calculateCollateralReturn);
            collateralAssetSelect.addEventListener('change', calculateCollateralReturn);
            
            // Initialize
            sourceAccountSelect.dispatchEvent(new Event('change'));
            if (returnCollateralCheckbox.checked) {
                collateralAssetContainer.style.display = 'block';
            }
        });
    </script>
</x-app-layout>