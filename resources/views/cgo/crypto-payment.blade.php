<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Complete Your CGO Investment') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-8">
                    <div class="text-center mb-8">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-4">
                            Send {{ $cryptoCurrency }} to Complete Investment
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            Send exactly <span class="font-bold text-indigo-600">${{ number_format($amount, 2) }} USD</span> worth of {{ $cryptoCurrency }}
                        </p>
                    </div>
                    
                    <!-- QR Code -->
                    <div class="flex justify-center mb-8">
                        <div class="bg-white p-4 rounded-lg border-2 border-gray-300">
                            {!! QrCode::size(250)->generate($cryptoAddress) !!}
                        </div>
                    </div>
                    
                    <!-- Crypto Address -->
                    <div class="mb-8">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ $cryptoCurrency }} Address
                        </label>
                        <div class="flex">
                            <input type="text" value="{{ $cryptoAddress }}" id="cryptoAddress" readonly
                                class="flex-1 block w-full rounded-l-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 bg-gray-50">
                            <button onclick="copyToClipboard('cryptoAddress')"
                                class="inline-flex items-center px-4 py-2 border border-l-0 border-gray-300 rounded-r-md bg-gray-50 text-gray-700 hover:bg-gray-100">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Important Notes -->
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-6">
                        <h4 class="font-semibold text-yellow-800 dark:text-yellow-200 mb-2">Important Notes:</h4>
                        <ul class="list-disc list-inside text-sm text-yellow-700 dark:text-yellow-300 space-y-1">
                            <li>Send only {{ $cryptoCurrency }} to this address</li>
                            <li>Minimum 3 confirmations required</li>
                            <li>Transaction will be verified automatically</li>
                            <li>Do not close this page until payment is confirmed</li>
                        </ul>
                    </div>
                    
                    <!-- Status -->
                    <div class="text-center">
                        <div id="paymentStatus" class="mb-4">
                            <div class="inline-flex items-center text-gray-600 dark:text-gray-400">
                                <svg class="animate-spin h-5 w-5 mr-3" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                Waiting for payment...
                            </div>
                        </div>
                        
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                            Investment ID: <span class="font-mono">{{ $investment->uuid }}</span>
                        </p>
                        
                        <a href="{{ route('cgo.invest') }}" class="text-indigo-600 hover:text-indigo-500">
                            Cancel and return
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function copyToClipboard(elementId) {
            const element = document.getElementById(elementId);
            element.select();
            element.setSelectionRange(0, 99999);
            document.execCommand("copy");
            
            // Show copied feedback
            const button = element.nextElementSibling;
            const originalContent = button.innerHTML;
            button.innerHTML = '<svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
            
            setTimeout(() => {
                button.innerHTML = originalContent;
            }, 2000);
        }
        
        // Poll for payment status
        setInterval(() => {
            fetch(`/api/cgo/payment-status/{{ $investment->uuid }}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'confirmed') {
                        document.getElementById('paymentStatus').innerHTML = `
                            <div class="inline-flex items-center text-green-600">
                                <svg class="h-5 w-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Payment confirmed! Redirecting...
                            </div>
                        `;
                        setTimeout(() => {
                            window.location.href = '{{ route("cgo.invest") }}';
                        }, 2000);
                    }
                });
        }, 5000); // Check every 5 seconds
    </script>
</x-app-layout>