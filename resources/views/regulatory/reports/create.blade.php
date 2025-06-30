<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Generate Regulatory Report') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <form action="{{ route('regulatory.reports.store') }}" method="POST" class="p-6 lg:p-8">
                    @csrf
                    
                    <!-- Back link -->
                    <div class="mb-6">
                        <a href="{{ route('regulatory.reports.index') }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-300 font-medium">
                            ← Back to reports
                        </a>
                    </div>

                    <div class="space-y-6">
                        <!-- Report Type -->
                        <div>
                            <label for="report_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Report Type
                            </label>
                            <select id="report_type" 
                                    name="report_type" 
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                                    required>
                                <option value="">Select a report type</option>
                                @foreach($reportTypes as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('report_type')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Report Type Description -->
                        <div id="report-description" class="hidden bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
                            <h4 class="text-sm font-medium text-blue-900 dark:text-blue-100 mb-2">Report Description</h4>
                            <div id="description-content" class="text-sm text-blue-700 dark:text-blue-300"></div>
                        </div>

                        <!-- Date Range -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="start_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    Start Date
                                </label>
                                <input type="date" 
                                       name="start_date" 
                                       id="start_date" 
                                       class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                                       value="{{ old('start_date', now()->startOfMonth()->format('Y-m-d')) }}"
                                       required>
                                @error('start_date')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                            
                            <div>
                                <label for="end_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                    End Date
                                </label>
                                <input type="date" 
                                       name="end_date" 
                                       id="end_date" 
                                       class="mt-1 focus:ring-indigo-500 focus:border-indigo-500 block w-full shadow-sm sm:text-sm border-gray-300 rounded-md dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                                       value="{{ old('end_date', now()->format('Y-m-d')) }}"
                                       required>
                                @error('end_date')
                                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                                @enderror
                            </div>
                        </div>

                        <!-- Jurisdiction -->
                        <div>
                            <label for="jurisdiction" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Jurisdiction
                            </label>
                            <select id="jurisdiction" 
                                    name="jurisdiction" 
                                    class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md dark:bg-gray-900 dark:border-gray-700 dark:text-white"
                                    required>
                                <option value="">Select jurisdiction</option>
                                <option value="US">United States</option>
                                <option value="EU">European Union</option>
                                <option value="UK">United Kingdom</option>
                                <option value="LT">Lithuania</option>
                            </select>
                            @error('jurisdiction')
                                <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Include Details -->
                        <div>
                            <div class="relative flex items-start">
                                <div class="flex items-center h-5">
                                    <input id="include_details" 
                                           name="include_details" 
                                           type="checkbox" 
                                           class="focus:ring-indigo-500 h-4 w-4 text-indigo-600 border-gray-300 rounded dark:bg-gray-900 dark:border-gray-700"
                                           value="1"
                                           {{ old('include_details') ? 'checked' : '' }}>
                                </div>
                                <div class="ml-3 text-sm">
                                    <label for="include_details" class="font-medium text-gray-700 dark:text-gray-300">
                                        Include detailed transaction information
                                    </label>
                                    <p class="text-gray-500 dark:text-gray-400">
                                        This will include individual transaction details in the report (may increase file size)
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Warning for SAR -->
                        <div id="sar-warning" class="hidden bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">
                                        Suspicious Activity Report (SAR) Notice
                                    </h3>
                                    <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                                        <ul class="list-disc pl-5 space-y-1">
                                            <li>SARs are confidential and should not be disclosed to the subject</li>
                                            <li>Must be filed within 30 days of detection</li>
                                            <li>Do not notify customers about SAR filing</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="flex items-center justify-end space-x-4">
                            <a href="{{ route('regulatory.reports.index') }}" class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400 active:bg-gray-500 focus:outline-none focus:border-gray-500 focus:ring focus:ring-gray-300 disabled:opacity-25 transition">
                                Cancel
                            </a>
                            <button type="submit" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition">
                                Generate Report
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Help Section -->
            <div class="mt-8 bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-4">Report Types Guide</h3>
                    <div class="space-y-4">
                        <div>
                            <h4 class="font-medium text-gray-900 dark:text-white">Currency Transaction Report (CTR)</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Required for cash transactions exceeding $10,000 in a single business day. Must be filed within 15 days.
                            </p>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900 dark:text-white">Suspicious Activity Report (SAR)</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Required for transactions that appear suspicious or potentially related to money laundering. Must be filed within 30 days of detection.
                            </p>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900 dark:text-white">Monthly Compliance Report</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Summary of all compliance activities, violations, and corrective actions taken during the month.
                            </p>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900 dark:text-white">Quarterly Risk Assessment</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Comprehensive risk analysis including customer risk ratings, geographic risks, and product risks.
                            </p>
                        </div>
                        <div>
                            <h4 class="font-medium text-gray-900 dark:text-white">Annual AML Report</h4>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Annual anti-money laundering program assessment including training, testing, and system effectiveness.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // Show/hide report descriptions and warnings based on selection
        document.getElementById('report_type').addEventListener('change', function() {
            const descriptionDiv = document.getElementById('report-description');
            const descriptionContent = document.getElementById('description-content');
            const sarWarning = document.getElementById('sar-warning');
            
            const descriptions = {
                'ctr': 'This report identifies all cash transactions over $10,000 within the specified period. The report will include customer information, transaction details, and aggregated amounts.',
                'sar': 'This report compiles all suspicious activities detected during the specified period. It includes transaction patterns, customer behavior analysis, and risk indicators.',
                'monthly_compliance': 'Comprehensive compliance overview including KYC completions, AML alerts, regulatory violations, and training completions.',
                'quarterly_risk': 'Detailed risk assessment covering customer risk distribution, high-risk jurisdictions, product risks, and emerging threats.',
                'annual_aml': 'Annual review of the AML program effectiveness, including policy updates, system enhancements, and regulatory changes.'
            };
            
            if (this.value) {
                descriptionDiv.classList.remove('hidden');
                descriptionContent.textContent = descriptions[this.value] || '';
                
                // Show SAR warning if SAR is selected
                if (this.value === 'sar') {
                    sarWarning.classList.remove('hidden');
                } else {
                    sarWarning.classList.add('hidden');
                }
            } else {
                descriptionDiv.classList.add('hidden');
                sarWarning.classList.add('hidden');
            }
        });

        // Set appropriate date ranges based on report type
        document.getElementById('report_type').addEventListener('change', function() {
            const startDate = document.getElementById('start_date');
            const endDate = document.getElementById('end_date');
            const today = new Date();
            
            switch(this.value) {
                case 'monthly_compliance':
                    // Set to previous month
                    const firstDayLastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
                    const lastDayLastMonth = new Date(today.getFullYear(), today.getMonth(), 0);
                    startDate.value = firstDayLastMonth.toISOString().split('T')[0];
                    endDate.value = lastDayLastMonth.toISOString().split('T')[0];
                    break;
                case 'quarterly_risk':
                    // Set to previous quarter
                    const quarter = Math.floor((today.getMonth() - 3) / 3);
                    const firstDayQuarter = new Date(today.getFullYear(), quarter * 3, 1);
                    const lastDayQuarter = new Date(today.getFullYear(), quarter * 3 + 3, 0);
                    startDate.value = firstDayQuarter.toISOString().split('T')[0];
                    endDate.value = lastDayQuarter.toISOString().split('T')[0];
                    break;
                case 'annual_aml':
                    // Set to previous year
                    const firstDayLastYear = new Date(today.getFullYear() - 1, 0, 1);
                    const lastDayLastYear = new Date(today.getFullYear() - 1, 11, 31);
                    startDate.value = firstDayLastYear.toISOString().split('T')[0];
                    endDate.value = lastDayLastYear.toISOString().split('T')[0];
                    break;
            }
        });
    </script>
    @endpush
</x-app-layout>