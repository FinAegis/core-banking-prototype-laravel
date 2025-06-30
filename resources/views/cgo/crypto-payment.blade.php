<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Complete Crypto Payment') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-3xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6">
                    <h3 class="text-2xl font-bold text-gray-900 dark:text-gray-100 mb-6">
                        Send {{ $cryptoCurrency }} Payment
                    </h3>
                    
                    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4 mb-6">
                        <p class="text-blue-800 dark:text-blue-200">
                            Please send exactly <strong>${{ number_format($amount, 2) }} USD worth of {{ $cryptoCurrency }}</strong> to the address below.
                        </p>
                    </div>
                    
                    <!-- Crypto Address -->
                    <div class="mb-6">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                            {{ $cryptoCurrency }} Address
                        </label>
                        <div class="flex items-center space-x-2">
                            <input type="text" value="{{ $cryptoAddress }}" readonly
                                class="flex-1 rounded-md border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 bg-gray-50"
                                id="cryptoAddress">
                            <button onclick="copyAddress()" class="bg-indigo-600 text-white px-4 py-2 rounded-md hover:bg-indigo-700">
                                Copy
                            </button>
                        </div>
                    </div>
                    
                    <!-- QR Code -->
                    <div class="mb-6 text-center">
                        <p class="text-sm text-gray-600 dark:text-gray-400 mb-2">Or scan this QR code:</p>
                        <div class="inline-block p-4 bg-white rounded-lg">
                            {!! QrCode::size(200)->generate($cryptoAddress) !!}
                        </div>
                    </div>
                    
                    <!-- Important Notes -->
                    <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4 mb-6">
                        <h4 class="font-semibold text-yellow-800 dark:text-yellow-200 mb-2">Important:</h4>
                        <ul class="text-sm text-yellow-700 dark:text-yellow-300 space-y-1">
                            <li>• Send only {{ $cryptoCurrency }} to this address</li>
                            <li>• Transaction must be confirmed on the blockchain</li>
                            <li>• Allow up to 30 minutes for confirmation</li>
                            <li>• Save your transaction ID for reference</li>
                        </ul>
                    </div>
                    
                    <!-- Reference Number -->
                    <div class="mb-6">
                        <p class="text-sm text-gray-600 dark:text-gray-400">
                            Reference: <code class="bg-gray-100 dark:bg-gray-900 px-2 py-1 rounded">CGO-{{ $investment->uuid }}</code>
                        </p>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="flex justify-between">
                        <a href="{{ route('cgo.invest') }}" class="text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                            ← Back to Investment
                        </a>
                        <a href="{{ route('dashboard') }}" class="bg-gray-600 text-white px-6 py-2 rounded-md hover:bg-gray-700">
                            I've Sent Payment
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function copyAddress() {
            const addressInput = document.getElementById('cryptoAddress');
            addressInput.select();
            addressInput.setSelectionRange(0, 99999);
            document.execCommand('copy');
            
            // Show confirmation
            const button = event.target;
            const originalText = button.textContent;
            button.textContent = 'Copied!';
            button.classList.add('bg-green-600');
            
            setTimeout(() => {
                button.textContent = originalText;
                button.classList.remove('bg-green-600');
            }, 2000);
        }
    </script>
</x-app-layout>