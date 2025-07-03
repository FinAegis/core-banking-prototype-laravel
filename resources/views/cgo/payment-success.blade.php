<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Payment Successful') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <div class="text-center mb-8">
                        <div class="inline-flex items-center justify-center w-16 h-16 bg-green-100 dark:bg-green-900 rounded-full mb-4">
                            <svg class="w-8 h-8 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-2">
                            {{ $message }}
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            Thank you for investing in FinAegis Continuous Growth Offering
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
                                <dt class="text-gray-600 dark:text-gray-400">Shares Purchased:</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ number_format($investment->shares_purchased, 4) }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">Ownership:</dt>
                                <dd class="text-gray-900 dark:text-gray-100">{{ number_format($investment->ownership_percentage, 4) }}%</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-gray-600 dark:text-gray-400">Investment Tier:</dt>
                                <dd>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-{{ $investment->tierColor }}-100 text-{{ $investment->tierColor }}-800">
                                        {{ ucfirst($investment->tier) }}
                                    </span>
                                </dd>
                            </div>
                        </dl>
                    </div>
                    
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6">
                        <h4 class="font-semibold text-blue-800 dark:text-blue-200 mb-2">What happens next?</h4>
                        <ul class="text-sm text-blue-700 dark:text-blue-300 space-y-1">
                            <li>• You will receive a confirmation email with your investment details</li>
                            <li>• Your investment certificate will be issued within 24-48 hours</li>
                            <li>• You can track your investment status from your dashboard</li>
                            <li>• Quarterly reports will be sent to your registered email</li>
                        </ul>
                    </div>
                    
                    @if($investment->certificate_number)
                    <div class="flex justify-center mb-6">
                        <a href="{{ route('cgo.certificate', $investment->uuid) }}" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring ring-indigo-300 disabled:opacity-25 transition ease-in-out duration-150">
                            Download Certificate
                        </a>
                    </div>
                    @endif
                    
                    <div class="flex justify-between">
                        <a href="{{ route('cgo.invest') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                            ← View All Investments
                        </a>
                        <a href="{{ route('dashboard') }}" class="bg-gray-600 text-white px-6 py-2 rounded-md hover:bg-gray-700">
                            Go to Dashboard
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>