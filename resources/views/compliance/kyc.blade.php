<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('KYC Verification') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 lg:p-8">
                    <div class="flex items-center mb-6">
                        <div class="flex-shrink-0">
                            <div class="w-12 h-12 bg-yellow-500 rounded-full flex items-center justify-center">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path>
                                </svg>
                            </div>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">
                                Verify Your Identity
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                This helps us keep your account secure and comply with regulations
                            </p>
                        </div>
                    </div>

                    @if(auth()->user()->kyc_status === 'approved')
                        <div class="bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg p-4 mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-green-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-green-800 dark:text-green-200">
                                        Your identity has been verified. You have full access to all features.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @elseif(auth()->user()->kyc_status === 'pending' || auth()->user()->kyc_status === 'in_review')
                        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-6">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <p class="text-sm text-yellow-800 dark:text-yellow-200">
                                        Your documents are being reviewed. This usually takes 1-2 business days.
                                    </p>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="space-y-6">
                            <!-- KYC Level Selection -->
                            <div>
                                <h4 class="text-base font-medium text-gray-900 dark:text-gray-100 mb-4">
                                    Choose Your Verification Level
                                </h4>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:border-indigo-500 cursor-pointer">
                                        <h5 class="font-medium text-gray-900 dark:text-gray-100">Basic</h5>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Up to $10,000 daily limit</p>
                                        <ul class="text-sm text-gray-600 dark:text-gray-400 mt-2 space-y-1">
                                            <li>• National ID</li>
                                            <li>• Selfie verification</li>
                                        </ul>
                                    </div>
                                    
                                    <div class="border-2 border-indigo-500 rounded-lg p-4 cursor-pointer">
                                        <h5 class="font-medium text-gray-900 dark:text-gray-100">Enhanced</h5>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Up to $50,000 daily limit</p>
                                        <ul class="text-sm text-gray-600 dark:text-gray-400 mt-2 space-y-1">
                                            <li>• Passport</li>
                                            <li>• Proof of address</li>
                                            <li>• Selfie verification</li>
                                        </ul>
                                    </div>
                                    
                                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:border-indigo-500 cursor-pointer">
                                        <h5 class="font-medium text-gray-900 dark:text-gray-100">Full</h5>
                                        <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">No limits</p>
                                        <ul class="text-sm text-gray-600 dark:text-gray-400 mt-2 space-y-1">
                                            <li>• All Enhanced docs</li>
                                            <li>• Bank statement</li>
                                            <li>• Income proof</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <!-- Document Upload -->
                            <div>
                                <h4 class="text-base font-medium text-gray-900 dark:text-gray-100 mb-4">
                                    Upload Your Documents
                                </h4>
                                <div class="space-y-4">
                                    <div class="border-2 border-dashed border-gray-300 dark:border-gray-700 rounded-lg p-6 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" stroke="currentColor" fill="none" viewBox="0 0 48 48">
                                            <path d="M28 8H12a4 4 0 00-4 4v20m32-12v8m0 0v8a4 4 0 01-4 4H12a4 4 0 01-4-4v-4m32-4l-3.172-3.172a4 4 0 00-5.656 0L28 28M8 32l9.172-9.172a4 4 0 015.656 0L28 28m0 0l4 4m4-24h8m-4-4v8m-12 4h.02" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                        </svg>
                                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                            <button type="button" class="font-medium text-indigo-600 hover:text-indigo-500">
                                                Upload a file
                                            </button>
                                            or drag and drop
                                        </p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">
                                            PNG, JPG, PDF up to 10MB
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="flex justify-end">
                                <button type="button" class="inline-flex items-center px-4 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400 active:bg-gray-500 focus:outline-none focus:border-gray-500 focus:ring focus:ring-gray-300 disabled:opacity-25 transition mr-3">
                                    Skip for Now
                                </button>
                                <button type="button" class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring focus:ring-indigo-300 disabled:opacity-25 transition">
                                    Submit for Verification
                                </button>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>