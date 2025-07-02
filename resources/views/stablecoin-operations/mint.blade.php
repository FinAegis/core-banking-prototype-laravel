<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Mint Stablecoin') }}
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

            <!-- Mint Form -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('stablecoin-operations.mint.process') }}">
                        @csrf
                        <input type="hidden" name="stablecoin" value="{{ $stablecoin }}">
                        
                        <!-- Amount to Mint -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Amount to Mint
                            </label>
                            <div class="relative">
                                <input type="number" 
                                       name="amount" 
                                       id="mint-amount"
                                       value="{{ old('amount') }}"
                                       class="w-full rounded-md border-gray-300 shadow-sm pl-8"
                                       placeholder="0.00"
                                       step="0.01"
                                       min="100"
                                       max="1000000"
                                       required>
                                <span class="absolute left-3 top-3 text-gray-500">$</span>
                            </div>
                            @error('amount')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Collateral Asset -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Collateral Asset
                            </label>
                            <select name="collateral_asset" 
                                    id="collateral-asset"
                                    class="w-full rounded-md border-gray-300 shadow-sm"
                                    required>
                                <option value="">Select collateral asset</option>
                                @foreach($collateralAssets as $code => $asset)
                                    <option value="{{ $code }}" {{ old('collateral_asset') == $code ? 'selected' : '' }}>
                                        {{ $asset['name'] }} ({{ $code }})
                                    </option>
                                @endforeach
                            </select>
                            @error('collateral_asset')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Collateral Amount -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Collateral Amount
                            </label>
                            <div class="relative">
                                <input type="number" 
                                       name="collateral_amount" 
                                       id="collateral-amount"
                                       value="{{ old('collateral_amount') }}"
                                       class="w-full rounded-md border-gray-300 shadow-sm"
                                       placeholder="0.00"
                                       step="0.01"
                                       min="0"
                                       required>
                            </div>
                            <p class="mt-1 text-sm text-gray-500">
                                Required: <span id="required-collateral">0.00</span> <span id="collateral-currency"></span>
                            </p>
                            @error('collateral_amount')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Recipient Account -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Recipient Account
                            </label>
                            <select name="recipient_account" 
                                    class="w-full rounded-md border-gray-300 shadow-sm"
                                    required>
                                <option value="">Select recipient account</option>
                                @foreach($operatorAccounts as $account)
                                    <option value="{{ $account->uuid }}" {{ old('recipient_account') == $account->uuid ? 'selected' : '' }}>
                                        {{ $account->name }} - {{ $account->type }}
                                    </option>
                                @endforeach
                            </select>
                            @error('recipient_account')
                                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Reason -->
                        <div class="mb-6">
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                                Reason for Minting
                            </label>
                            <input type="text" 
                                   name="reason" 
                                   value="{{ old('reason') }}"
                                   class="w-full rounded-md border-gray-300 shadow-sm"
                                   placeholder="e.g., Market maker liquidity provision"
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
                        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-6">
                            <div class="flex">
                                <svg class="h-5 w-5 text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                                </svg>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                        Important Notice
                                    </h3>
                                    <p class="mt-1 text-sm text-yellow-700 dark:text-yellow-300">
                                        This action will mint new {{ $stablecoin }} tokens and lock collateral. 
                                        Ensure all parameters are correct before proceeding.
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
                                    class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                                Mint Stablecoin
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Calculate required collateral
        document.addEventListener('DOMContentLoaded', function() {
            const mintAmountInput = document.getElementById('mint-amount');
            const collateralAssetSelect = document.getElementById('collateral-asset');
            const collateralAmountInput = document.getElementById('collateral-amount');
            const requiredCollateralSpan = document.getElementById('required-collateral');
            const collateralCurrencySpan = document.getElementById('collateral-currency');
            
            const collateralRatio = {{ $stablecoinInfo['collateral_ratio'] }} / 100;
            const rates = {!! json_encode(array_map(function($asset) { return $asset['rate']; }, $collateralAssets)) !!};
            
            function calculateRequiredCollateral() {
                const mintAmount = parseFloat(mintAmountInput.value) || 0;
                const collateralAsset = collateralAssetSelect.value;
                
                if (mintAmount > 0 && collateralAsset && rates[collateralAsset]) {
                    const baseAmount = mintAmount * collateralRatio;
                    const requiredAmount = baseAmount / rates[collateralAsset];
                    
                    requiredCollateralSpan.textContent = requiredAmount.toFixed(2);
                    collateralCurrencySpan.textContent = collateralAsset;
                    
                    // Auto-fill if empty
                    if (!collateralAmountInput.value) {
                        collateralAmountInput.value = requiredAmount.toFixed(2);
                    }
                }
            }
            
            mintAmountInput.addEventListener('input', calculateRequiredCollateral);
            collateralAssetSelect.addEventListener('change', calculateRequiredCollateral);
            
            calculateRequiredCollateral();
        });
    </script>
</x-app-layout>