<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Apply for a Loan') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <!-- Credit Score Summary -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                <div class="p-6">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-lg font-semibold mb-2">Your Credit Score</h3>
                            <div class="flex items-baseline space-x-2">
                                <span class="text-3xl font-bold">{{ $creditScore['score'] }}</span>
                                <span class="text-lg text-gray-600 dark:text-gray-400">{{ $creditScore['rating'] }}</span>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Based on your score, you qualify for:</p>
                            <p class="text-lg font-semibold text-green-600">Premium Rates</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Application Form -->
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg">
                <div class="p-6">
                    <form method="POST" action="{{ route('lending.apply.submit') }}" id="loanApplicationForm">
                        @csrf

                        <!-- Step 1: Loan Details -->
                        <div class="step" id="step1">
                            <h3 class="text-lg font-semibold mb-4">Loan Details</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <x-label for="loan_product" value="{{ __('Loan Product') }}" />
                                    <select id="loan_product" name="loan_product" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600" required>
                                        <option value="">Select a loan product</option>
                                        @foreach($loanProducts as $product)
                                            <option value="{{ $product['id'] }}" 
                                                    data-min="{{ $product['min_amount'] }}"
                                                    data-max="{{ $product['max_amount'] }}"
                                                    data-min-term="{{ $product['min_term'] }}"
                                                    data-max-term="{{ $product['max_term'] }}"
                                                    data-rate="{{ $product['interest_rate'] }}"
                                                    data-collateral="{{ $product['collateral_required'] ? 'true' : 'false' }}"
                                                    {{ request('product') === $product['id'] ? 'selected' : '' }}>
                                                {{ $product['name'] }} ({{ $product['interest_rate'] }}% APR)
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('loan_product')" class="mt-2" />
                                </div>

                                <div>
                                    <x-label for="account_id" value="{{ __('Disbursement Account') }}" />
                                    <select id="account_id" name="account_id" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600" required>
                                        <option value="">Select an account</option>
                                        @foreach($accounts as $account)
                                            <option value="{{ $account->uuid }}">
                                                {{ $account->name }} ({{ $account->account_number }})
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('account_id')" class="mt-2" />
                                </div>

                                <div>
                                    <x-label for="amount" value="{{ __('Loan Amount') }}" />
                                    <div class="mt-1 relative rounded-md shadow-sm">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 sm:text-sm">$</span>
                                        </div>
                                        <input type="number" 
                                               name="amount" 
                                               id="amount" 
                                               min="100"
                                               max="1000000"
                                               step="100"
                                               class="block w-full pl-7 pr-12 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                                               placeholder="0.00"
                                               required>
                                    </div>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        <span id="amount-range">Select a product to see amount range</span>
                                    </p>
                                    <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                                </div>

                                <div>
                                    <x-label for="term_months" value="{{ __('Loan Term (Months)') }}" />
                                    <input type="number" 
                                           name="term_months" 
                                           id="term_months" 
                                           min="1"
                                           max="360"
                                           class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                                           required>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        <span id="term-range">Select a product to see term range</span>
                                    </p>
                                    <x-input-error :messages="$errors->get('term_months')" class="mt-2" />
                                </div>

                                <div class="md:col-span-2">
                                    <x-label for="purpose" value="{{ __('Loan Purpose') }}" />
                                    <textarea name="purpose" 
                                              id="purpose" 
                                              rows="3"
                                              class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                                              placeholder="Please describe how you plan to use this loan"
                                              required>{{ old('purpose') }}</textarea>
                                    <x-input-error :messages="$errors->get('purpose')" class="mt-2" />
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Collateral (if required) -->
                        <div class="step hidden" id="step2">
                            <h3 class="text-lg font-semibold mb-4">Collateral Information</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <x-label for="collateral_type" value="{{ __('Collateral Type') }}" />
                                    <select id="collateral_type" name="collateral_type" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600">
                                        <option value="none">No Collateral</option>
                                        <option value="crypto">Cryptocurrency</option>
                                        <option value="asset">Other Asset</option>
                                    </select>
                                    <x-input-error :messages="$errors->get('collateral_type')" class="mt-2" />
                                </div>

                                <div id="collateral-details" class="hidden">
                                    <x-label for="collateral_asset" value="{{ __('Collateral Asset') }}" />
                                    <select id="collateral_asset" name="collateral_asset" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600">
                                        <option value="">Select an asset</option>
                                        @foreach($collateralAssets as $code => $asset)
                                            <option value="{{ $code }}" data-ltv="{{ $asset['ltv'] }}">
                                                {{ $asset['name'] }} ({{ $code }}) - LTV: {{ $asset['ltv'] }}%
                                            </option>
                                        @endforeach
                                    </select>
                                    <x-input-error :messages="$errors->get('collateral_asset')" class="mt-2" />
                                </div>

                                <div id="collateral-amount-div" class="hidden md:col-span-2">
                                    <x-label for="collateral_amount" value="{{ __('Collateral Amount') }}" />
                                    <input type="number" 
                                           name="collateral_amount" 
                                           id="collateral_amount" 
                                           step="0.00000001"
                                           min="0"
                                           class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600">
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        Required collateral: <span id="required-collateral">-</span>
                                    </p>
                                    <x-input-error :messages="$errors->get('collateral_amount')" class="mt-2" />
                                </div>
                            </div>
                        </div>

                        <!-- Step 3: Personal Information -->
                        <div class="step hidden" id="step3">
                            <h3 class="text-lg font-semibold mb-4">Personal Information</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <x-label for="employment_status" value="{{ __('Employment Status') }}" />
                                    <select id="employment_status" name="employment_status" class="mt-1 block w-full rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600" required>
                                        <option value="">Select status</option>
                                        <option value="employed">Employed</option>
                                        <option value="self-employed">Self-Employed</option>
                                        <option value="retired">Retired</option>
                                        <option value="student">Student</option>
                                        <option value="unemployed">Unemployed</option>
                                    </select>
                                    <x-input-error :messages="$errors->get('employment_status')" class="mt-2" />
                                </div>

                                <div>
                                    <x-label for="annual_income" value="{{ __('Annual Income') }}" />
                                    <div class="mt-1 relative rounded-md shadow-sm">
                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                            <span class="text-gray-500 sm:text-sm">$</span>
                                        </div>
                                        <input type="number" 
                                               name="annual_income" 
                                               id="annual_income" 
                                               min="0"
                                               step="1000"
                                               class="block w-full pl-7 pr-12 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600"
                                               placeholder="0.00"
                                               required>
                                    </div>
                                    <x-input-error :messages="$errors->get('annual_income')" class="mt-2" />
                                </div>
                            </div>
                        </div>

                        <!-- Loan Summary -->
                        <div class="mt-6 p-4 bg-gray-50 dark:bg-gray-700 rounded-lg">
                            <h3 class="font-semibold mb-3">Loan Summary</h3>
                            <div class="space-y-2 text-sm">
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Loan Amount</span>
                                    <span id="summary-amount">$0</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Interest Rate</span>
                                    <span id="summary-rate">0%</span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600 dark:text-gray-400">Term</span>
                                    <span id="summary-term">0 months</span>
                                </div>
                                <div class="flex justify-between font-semibold">
                                    <span>Estimated Monthly Payment</span>
                                    <span id="summary-payment">$0</span>
                                </div>
                            </div>
                        </div>

                        @if ($errors->has('error'))
                            <div class="mt-4 p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                                <p class="text-sm text-red-600 dark:text-red-400">{{ $errors->first('error') }}</p>
                            </div>
                        @endif

                        <!-- Navigation Buttons -->
                        <div class="mt-6 flex items-center justify-between">
                            <button type="button" 
                                    id="prevBtn"
                                    class="px-4 py-2 bg-gray-300 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-600 transition hidden">
                                Previous
                            </button>
                            
                            <div class="ml-auto space-x-3">
                                <a href="{{ route('lending.index') }}" 
                                   class="px-4 py-2 bg-gray-300 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-400 dark:hover:bg-gray-600 transition">
                                    Cancel
                                </a>
                                <button type="button" 
                                        id="nextBtn"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition">
                                    Next
                                </button>
                                <button type="submit" 
                                        id="submitBtn"
                                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition hidden">
                                    Submit Application
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        let currentStep = 1;
        const totalSteps = 3;
        
        // Product change handler
        document.getElementById('loan_product').addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            const minAmount = selected.dataset.min;
            const maxAmount = selected.dataset.max;
            const minTerm = selected.dataset.minTerm;
            const maxTerm = selected.dataset.maxTerm;
            const rate = selected.dataset.rate;
            const requiresCollateral = selected.dataset.collateral === 'true';
            
            if (minAmount && maxAmount) {
                document.getElementById('amount').min = minAmount;
                document.getElementById('amount').max = maxAmount;
                document.getElementById('amount-range').textContent = 
                    `$${parseInt(minAmount).toLocaleString()} - $${parseInt(maxAmount).toLocaleString()}`;
            }
            
            if (minTerm && maxTerm) {
                document.getElementById('term_months').min = minTerm;
                document.getElementById('term_months').max = maxTerm;
                document.getElementById('term-range').textContent = 
                    `${minTerm} - ${maxTerm} months`;
            }
            
            // Show/hide collateral step
            if (requiresCollateral) {
                document.getElementById('step2').dataset.required = 'true';
            } else {
                delete document.getElementById('step2').dataset.required;
                document.getElementById('collateral_type').value = 'none';
            }
            
            updateSummary();
        });
        
        // Collateral type change handler
        document.getElementById('collateral_type').addEventListener('change', function() {
            const showDetails = this.value !== 'none';
            document.getElementById('collateral-details').classList.toggle('hidden', !showDetails);
            document.getElementById('collateral-amount-div').classList.toggle('hidden', !showDetails);
            
            if (!showDetails) {
                document.getElementById('collateral_asset').value = '';
                document.getElementById('collateral_amount').value = '';
            }
        });
        
        // Update loan summary
        function updateSummary() {
            const amount = parseFloat(document.getElementById('amount').value) || 0;
            const rate = parseFloat(document.getElementById('loan_product').selectedOptions[0]?.dataset.rate) || 0;
            const term = parseInt(document.getElementById('term_months').value) || 0;
            
            document.getElementById('summary-amount').textContent = `$${amount.toLocaleString()}`;
            document.getElementById('summary-rate').textContent = `${rate}%`;
            document.getElementById('summary-term').textContent = `${term} months`;
            
            if (amount > 0 && rate > 0 && term > 0) {
                const monthlyRate = rate / 100 / 12;
                const payment = amount * (monthlyRate * Math.pow(1 + monthlyRate, term)) / (Math.pow(1 + monthlyRate, term) - 1);
                document.getElementById('summary-payment').textContent = `$${payment.toFixed(2)}`;
            }
        }
        
        // Input change handlers
        document.getElementById('amount').addEventListener('input', updateSummary);
        document.getElementById('term_months').addEventListener('input', updateSummary);
        
        // Collateral calculation
        document.getElementById('collateral_asset').addEventListener('change', function() {
            calculateRequiredCollateral();
        });
        
        document.getElementById('amount').addEventListener('input', function() {
            calculateRequiredCollateral();
        });
        
        function calculateRequiredCollateral() {
            const loanAmount = parseFloat(document.getElementById('amount').value) || 0;
            const selectedAsset = document.getElementById('collateral_asset').selectedOptions[0];
            
            if (loanAmount > 0 && selectedAsset && selectedAsset.value) {
                const ltv = parseFloat(selectedAsset.dataset.ltv) || 50;
                const requiredCollateral = loanAmount / (ltv / 100);
                document.getElementById('required-collateral').textContent = 
                    `${requiredCollateral.toFixed(2)} ${selectedAsset.value}`;
            }
        }
        
        // Step navigation
        function showStep(step) {
            document.querySelectorAll('.step').forEach(s => s.classList.add('hidden'));
            document.getElementById(`step${step}`).classList.remove('hidden');
            
            document.getElementById('prevBtn').classList.toggle('hidden', step === 1);
            document.getElementById('nextBtn').classList.toggle('hidden', step === totalSteps);
            document.getElementById('submitBtn').classList.toggle('hidden', step !== totalSteps);
        }
        
        document.getElementById('nextBtn').addEventListener('click', function() {
            if (validateStep(currentStep)) {
                currentStep++;
                if (currentStep === 2 && document.getElementById('step2').dataset.required !== 'true') {
                    currentStep++; // Skip collateral step if not required
                }
                showStep(currentStep);
            }
        });
        
        document.getElementById('prevBtn').addEventListener('click', function() {
            currentStep--;
            if (currentStep === 2 && document.getElementById('step2').dataset.required !== 'true') {
                currentStep--; // Skip collateral step if not required
            }
            showStep(currentStep);
        });
        
        function validateStep(step) {
            const stepElement = document.getElementById(`step${step}`);
            const requiredFields = stepElement.querySelectorAll('[required]');
            
            for (let field of requiredFields) {
                if (!field.value) {
                    field.focus();
                    return false;
                }
            }
            
            return true;
        }
        
        // Initialize
        showStep(1);
        updateSummary();
    </script>
    @endpush
</x-app-layout>