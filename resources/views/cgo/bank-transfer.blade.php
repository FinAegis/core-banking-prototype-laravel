<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Bank Transfer Instructions') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">
                        Complete Bank Transfer
                    </h3>
                    
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6">
                        <p class="text-blue-800 dark:text-blue-200">
                            Please transfer <strong>${{ number_format($investment->amount, 2) }} USD</strong> to the following bank account.
                        </p>
                    </div>
                    
                    <!-- Bank Details -->
                    <div class="space-y-4 mb-6">
                        <div class="border-b border-gray-200 dark:border-gray-700 pb-3">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Bank Name</p>
                            <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $bankDetails['bank_name'] }}</p>
                        </div>
                        
                        <div class="border-b border-gray-200 dark:border-gray-700 pb-3">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Account Name</p>
                            <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $bankDetails['account_name'] }}</p>
                        </div>
                        
                        <div class="border-b border-gray-200 dark:border-gray-700 pb-3">
                            <p class="text-sm text-gray-600 dark:text-gray-400">Account Number</p>
                            <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $bankDetails['account_number'] }}</p>
                        </div>
                        
                        <div class="border-b border-gray-200 dark:border-gray-700 pb-3">
                            <p class="text-sm text-gray-600 dark:text-gray-400">SWIFT/BIC Code</p>
                            <p class="font-semibold text-gray-900 dark:text-gray-100">{{ $bankDetails['swift_code'] }}</p>
                        </div>
                        
                        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                            <p class="text-sm font-semibold text-yellow-800 dark:text-yellow-200 mb-1">Important Reference</p>
                            <p class="font-mono text-lg text-yellow-900 dark:text-yellow-100">{{ $bankDetails['reference'] }}</p>
                            <p class="text-xs text-yellow-700 dark:text-yellow-300 mt-1">
                                Please include this reference in your transfer to ensure proper processing
                            </p>
                        </div>
                    </div>
                    
                    <!-- Instructions -->
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 mb-6">
                        <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-2">Transfer Instructions:</h4>
                        <ol class="text-sm text-gray-600 dark:text-gray-400 space-y-1 list-decimal list-inside">
                            <li>Log in to your online banking</li>
                            <li>Add the above account as a beneficiary</li>
                            <li>Initiate a wire transfer for the exact amount</li>
                            <li>Include the reference number in the transfer details</li>
                            <li>Save your transfer confirmation for your records</li>
                        </ol>
                    </div>
                    
                    <!-- Processing Time -->
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6">
                        <p class="text-sm text-blue-800 dark:text-blue-200">
                            <strong>Processing Time:</strong> Bank transfers typically take 1-3 business days to process. 
                            You'll receive an email confirmation once your investment is confirmed.
                        </p>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex justify-between">
                        <a href="{{ route('cgo.invest') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                            ← Back to Investment
                        </a>
                        <a href="{{ route('dashboard') }}" class="bg-gray-600 text-white px-6 py-2 rounded-md hover:bg-gray-700">
                            I've Initiated Transfer
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>