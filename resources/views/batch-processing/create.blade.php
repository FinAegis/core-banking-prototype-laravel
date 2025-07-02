<x-app-layout>
    <x-slot name="header">
        <div class="flex justify-between items-center">
            <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                {{ __('Create Batch Job') }}
            </h2>
            <a href="{{ route('batch-processing.index') }}" 
               class="text-gray-600 hover:text-gray-900">
                ← Back to Batch Processing
            </a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('batch-processing.store') }}" id="batch-form">
                @csrf
                
                <!-- Job Details -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Job Details</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Job Name <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="name" value="{{ old('name', $template['name'] ?? '') }}"
                                       class="w-full rounded-md border-gray-300 shadow-sm"
                                       placeholder="e.g., Monthly Salary Payment" required>
                                @error('name')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Job Type <span class="text-red-500">*</span>
                                </label>
                                <select name="type" id="job-type" class="w-full rounded-md border-gray-300 shadow-sm" required>
                                    <option value="">Select Type</option>
                                    <option value="transfer" {{ old('type', $template['type'] ?? '') === 'transfer' ? 'selected' : '' }}>Transfer</option>
                                    <option value="payment" {{ old('type', $template['type'] ?? '') === 'payment' ? 'selected' : '' }}>Payment</option>
                                    <option value="conversion" {{ old('type', $template['type'] ?? '') === 'conversion' ? 'selected' : '' }}>Currency Conversion</option>
                                </select>
                                @error('type')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                    Schedule At (Optional)
                                </label>
                                <input type="datetime-local" name="schedule_at" value="{{ old('schedule_at') }}"
                                       class="w-full rounded-md border-gray-300 shadow-sm"
                                       min="{{ now()->addMinutes(5)->format('Y-m-d\TH:i') }}">
                                <p class="text-sm text-gray-500 mt-1">Leave empty to process immediately</p>
                                @error('schedule_at')
                                    <p class="text-red-500 text-sm mt-1">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Batch Items -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                    <div class="p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold">Batch Items</h3>
                            <div class="flex space-x-2">
                                <button type="button" onclick="addItem()" 
                                        class="px-3 py-1 bg-indigo-600 text-white rounded hover:bg-indigo-700">
                                    Add Item
                                </button>
                                <button type="button" onclick="importCSV()" 
                                        class="px-3 py-1 bg-gray-600 text-white rounded hover:bg-gray-700">
                                    Import CSV
                                </button>
                            </div>
                        </div>
                        
                        <div id="batch-items">
                            <!-- Items will be added here dynamically -->
                        </div>
                        
                        @error('items')
                            <p class="text-red-500 text-sm mt-2">{{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <!-- Summary -->
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm rounded-lg mb-6">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold mb-4">Summary</h3>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Total Items</p>
                                <p class="text-2xl font-bold" id="total-items">0</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Total Amount</p>
                                <p class="text-2xl font-bold" id="total-amount">0.00</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 dark:text-gray-400">Currencies</p>
                                <p class="text-sm" id="currencies">-</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="flex justify-end space-x-4">
                    <a href="{{ route('batch-processing.index') }}" 
                       class="px-6 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                        Create Batch Job
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Hidden CSV input -->
    <input type="file" id="csv-input" accept=".csv" style="display: none;" onchange="handleCSVFile(event)">

    <!-- Item template -->
    <template id="item-template">
        <div class="batch-item border border-gray-200 dark:border-gray-700 rounded-lg p-4 mb-4">
            <div class="flex justify-between items-start mb-3">
                <h4 class="font-medium">Item #<span class="item-number"></span></h4>
                <button type="button" onclick="removeItem(this)" 
                        class="text-red-600 hover:text-red-800">
                    Remove
                </button>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Transfer/Payment fields -->
                <div class="transfer-fields payment-fields">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        From Account
                    </label>
                    <select name="items[INDEX][from_account]" class="w-full rounded-md border-gray-300 shadow-sm from-account-select">
                        <option value="">Select Account</option>
                        @foreach($accounts as $account)
                            <option value="{{ $account->uuid }}">
                                {{ $account->name }} - {{ $account->type }}
                                @foreach($account->balances as $balance)
                                    ({{ $balance->asset->symbol }}: {{ number_format($balance->amount / 100, 2) }})
                                @endforeach
                            </option>
                        @endforeach
                    </select>
                </div>
                
                <div class="transfer-fields payment-fields">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        To Account
                    </label>
                    <input type="text" name="items[INDEX][to_account]" 
                           class="w-full rounded-md border-gray-300 shadow-sm"
                           placeholder="Recipient account UUID">
                </div>
                
                <!-- Conversion fields -->
                <div class="conversion-fields" style="display: none;">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        From Currency
                    </label>
                    <select name="items[INDEX][from_currency]" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Select Currency</option>
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                        <option value="GBP">GBP</option>
                        <option value="PHP">PHP</option>
                    </select>
                </div>
                
                <div class="conversion-fields" style="display: none;">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        To Currency
                    </label>
                    <select name="items[INDEX][to_currency]" class="w-full rounded-md border-gray-300 shadow-sm">
                        <option value="">Select Currency</option>
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                        <option value="GBP">GBP</option>
                        <option value="PHP">PHP</option>
                    </select>
                </div>
                
                <!-- Common fields -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Amount
                    </label>
                    <input type="number" name="items[INDEX][amount]" 
                           class="w-full rounded-md border-gray-300 shadow-sm amount-input"
                           placeholder="0.00" step="0.01" min="0.01">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Currency
                    </label>
                    <select name="items[INDEX][currency]" class="w-full rounded-md border-gray-300 shadow-sm currency-select">
                        <option value="">Select Currency</option>
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                        <option value="GBP">GBP</option>
                        <option value="PHP">PHP</option>
                    </select>
                </div>
                
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                        Description (Optional)
                    </label>
                    <input type="text" name="items[INDEX][description]" 
                           class="w-full rounded-md border-gray-300 shadow-sm"
                           placeholder="e.g., Invoice #12345">
                </div>
            </div>
        </div>
    </template>

    <script>
        let itemCount = 0;
        
        // Initialize with one item
        document.addEventListener('DOMContentLoaded', function() {
            addItem();
            updateFieldVisibility();
        });
        
        // Update field visibility based on job type
        document.getElementById('job-type').addEventListener('change', updateFieldVisibility);
        
        function updateFieldVisibility() {
            const jobType = document.getElementById('job-type').value;
            const items = document.querySelectorAll('.batch-item');
            
            items.forEach(item => {
                // Hide all type-specific fields
                item.querySelectorAll('.transfer-fields, .payment-fields, .conversion-fields').forEach(field => {
                    field.style.display = 'none';
                });
                
                // Show relevant fields
                if (jobType === 'transfer' || jobType === 'payment') {
                    item.querySelectorAll('.transfer-fields, .payment-fields').forEach(field => {
                        field.style.display = 'block';
                    });
                } else if (jobType === 'conversion') {
                    item.querySelectorAll('.conversion-fields').forEach(field => {
                        field.style.display = 'block';
                    });
                }
            });
        }
        
        function addItem() {
            const template = document.getElementById('item-template');
            const clone = template.content.cloneNode(true);
            
            // Update indexes
            clone.querySelector('.item-number').textContent = itemCount + 1;
            clone.querySelectorAll('[name*="[INDEX]"]').forEach(input => {
                input.name = input.name.replace('[INDEX]', '[' + itemCount + ']');
            });
            
            document.getElementById('batch-items').appendChild(clone);
            itemCount++;
            
            updateFieldVisibility();
            updateSummary();
        }
        
        function removeItem(button) {
            if (itemCount > 1) {
                button.closest('.batch-item').remove();
                itemCount--;
                
                // Renumber items
                document.querySelectorAll('.batch-item').forEach((item, index) => {
                    item.querySelector('.item-number').textContent = index + 1;
                });
                
                updateSummary();
            } else {
                alert('At least one item is required');
            }
        }
        
        function updateSummary() {
            const items = document.querySelectorAll('.batch-item');
            let totalAmount = 0;
            const currencies = new Set();
            
            items.forEach(item => {
                const amount = parseFloat(item.querySelector('.amount-input').value) || 0;
                const currency = item.querySelector('.currency-select').value;
                
                if (amount > 0 && currency) {
                    totalAmount += amount;
                    currencies.add(currency);
                }
            });
            
            document.getElementById('total-items').textContent = items.length;
            document.getElementById('total-amount').textContent = totalAmount.toFixed(2);
            document.getElementById('currencies').textContent = currencies.size > 0 
                ? Array.from(currencies).join(', ') 
                : '-';
        }
        
        // Update summary on input change
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('amount-input') || e.target.classList.contains('currency-select')) {
                updateSummary();
            }
        });
        
        function importCSV() {
            document.getElementById('csv-input').click();
        }
        
        function handleCSVFile(event) {
            const file = event.target.files[0];
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const csv = e.target.result;
                const lines = csv.split('\n');
                const headers = lines[0].split(',');
                
                // Clear existing items
                document.getElementById('batch-items').innerHTML = '';
                itemCount = 0;
                
                // Add items from CSV
                for (let i = 1; i < lines.length; i++) {
                    if (lines[i].trim() === '') continue;
                    
                    const values = lines[i].split(',');
                    addItem();
                    
                    const lastItem = document.querySelector('.batch-item:last-child');
                    
                    // Map CSV columns to form fields
                    headers.forEach((header, index) => {
                        const value = values[index]?.trim();
                        if (!value) return;
                        
                        switch(header.toLowerCase().trim()) {
                            case 'from_account':
                                const fromSelect = lastItem.querySelector('[name*="[from_account]"]');
                                if (fromSelect) fromSelect.value = value;
                                break;
                            case 'to_account':
                                const toInput = lastItem.querySelector('[name*="[to_account]"]');
                                if (toInput) toInput.value = value;
                                break;
                            case 'amount':
                                const amountInput = lastItem.querySelector('[name*="[amount]"]');
                                if (amountInput) amountInput.value = value;
                                break;
                            case 'currency':
                                const currencySelect = lastItem.querySelector('[name*="[currency]"]');
                                if (currencySelect) currencySelect.value = value;
                                break;
                            case 'description':
                                const descInput = lastItem.querySelector('[name*="[description]"]');
                                if (descInput) descInput.value = value;
                                break;
                        }
                    });
                }
                
                updateSummary();
                alert('CSV imported successfully!');
            };
            
            reader.readAsText(file);
            event.target.value = ''; // Reset input
        }
    </script>
</x-app-layout>