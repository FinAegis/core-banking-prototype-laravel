<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Payment Processing') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <div class="text-center mb-8">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-yellow-100 dark:bg-yellow-900 rounded-full mb-4">
                            <svg class="w-8 h-8 text-yellow-600 dark:text-yellow-400 animate-spin" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">
                            {{ $message }}
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            Please wait while we verify your payment
                        </p>
                    </div>
                    
                    <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-6 mb-6">
                        <h4 class="font-semibold text-gray-900 dark:text-gray-100 mb-4">Investment Details</h4>
                        <dl class="space-y-2">
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">Investment ID:</dt>
                                <dd class="text-gray-900 dark:text-gray-100 font-mono">{{ $investment->uuid }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">Amount:</dt>
                                <dd class="text-gray-900 dark:text-gray-100 font-semibold">${{ number_format($investment->amount, 2) }} USD</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">Payment Method:</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ ucfirst(str_replace('_', ' ', $investment->payment_method)) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">Status:</dt>
                                <dd>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                        Processing
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                    
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-6">
                        <h4 class="font-semibold text-yellow-800 dark:text-yellow-200 mb-2">Important Information</h4>
                        <ul class="text-sm text-yellow-700 dark:text-yellow-300 space-y-1">
                            <li>• Payment verification typically takes 1-5 minutes</li>
                            <li>• For bank transfers, processing may take 1-3 business days</li>
                            <li>• You will receive an email confirmation once payment is verified</li>
                            <li>• If you experience any issues, please contact support</li>
                        </ul>
                    </div>
                    
                    <div class="text-center">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            This page will automatically refresh every 30 seconds
                        </p>
                        <a href="{{ route('dashboard') }}" class="inline-flex items-center px-4 py-2 bg-gray-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-gray-700 active:bg-gray-900 focus:outline-none focus:border-gray-900 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150">
                            Go to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-refresh page every 30 seconds to check payment status
        setTimeout(function() {
            window.location.reload();
        }, 30000);
    </script>
</x-app-layout>