@extends('layouts.app')

@section('header')
    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
        {{ __('Manual Bank Transfer') }}
    </h2>
@endsection

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold mb-4">Bank Transfer Instructions</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                            To deposit funds via manual bank transfer, please use the following bank details:
                        </p>
                    </div>

                    <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6 mb-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Bank Name</p>
                                <p class="text-lg font-semibold">FinAegis Banking Ltd.</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Account Name</p>
                                <p class="text-lg font-semibold">FinAegis Core Banking</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Account Number</p>
                                <p class="text-lg font-semibold">1234567890</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Routing Number</p>
                                <p class="text-lg font-semibold">021000021</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">SWIFT/BIC</p>
                                <p class="text-lg font-semibold">FINAEGISXXX</p>
                            </div>
                            <div>
                                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Reference Code</p>
                                <p class="text-lg font-semibold text-blue-600">{{ $account->uuid ?? 'N/A' }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-6">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Important</h3>
                                <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                                    <ul class="list-disc list-inside">
                                        <li>Always include your reference code in the transfer description</li>
                                        <li>Deposits typically take 1-3 business days to process</li>
                                        <li>Minimum deposit amount: $100</li>
                                        <li>Contact support if your deposit doesn't appear within 5 business days</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-between">
                        <a href="{{ route('wallet.deposit') }}" class="inline-flex items-center px-4 py-2 bg-gray-300 dark:bg-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-400 dark:hover:bg-gray-500 transition ease-in-out duration-150">
                            Back to Deposit Options
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection