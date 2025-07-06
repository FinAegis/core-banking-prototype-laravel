<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Cryptocurrency Deposit') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold mb-4">Deposit Cryptocurrency</h3>
                        <p class="text-gray-600 dark:text-gray-400 mb-4">
                            Select a cryptocurrency and generate a deposit address:
                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <button onclick="selectCrypto('BTC')" class="crypto-option p-4 border-2 border-gray-200 dark:border-gray-600 rounded-lg hover:border-blue-500 transition-colors cursor-pointer text-center">
                            <div class="text-3xl mb-2">₿</div>
                            <h4 class="font-semibold">Bitcoin (BTC)</h4>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Network: Bitcoin</p>
                        </button>
                        
                        <button onclick="selectCrypto('ETH')" class="crypto-option p-4 border-2 border-gray-200 dark:border-gray-600 rounded-lg hover:border-blue-500 transition-colors cursor-pointer text-center">
                            <div class="text-3xl mb-2">Ξ</div>
                            <h4 class="font-semibold">Ethereum (ETH)</h4>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Network: ERC-20</p>
                        </button>
                        
                        <button onclick="selectCrypto('USDT')" class="crypto-option p-4 border-2 border-gray-200 dark:border-gray-600 rounded-lg hover:border-blue-500 transition-colors cursor-pointer text-center">
                            <div class="text-3xl mb-2">₮</div>
                            <h4 class="font-semibold">Tether (USDT)</h4>
                            <p class="text-sm text-gray-500 dark:text-gray-400">Network: TRC-20</p>
                        </button>
                    </div>

                    <div id="depositDetails" class="hidden">
                        <div class="bg-gray-50 dark:bg-gray-700 rounded-lg p-6 mb-6">
                            <h4 class="font-semibold mb-4">Deposit Address</h4>
                            <div class="flex items-center space-x-2">
                                <input type="text" id="cryptoAddress" readonly class="flex-1 px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-md bg-white dark:bg-gray-800" value="">
                                <button onclick="copyAddress()" class="px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors">
                                    Copy
                                </button>
                            </div>
                            
                            <div class="mt-6 flex justify-center">
                                <div id="qrcode" class="p-4 bg-white rounded-lg"></div>
                            </div>
                        </div>

                        <div class="bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <svg class="h-5 w-5 text-yellow-400" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                                <div class="ml-3">
                                    <h3 class="text-sm font-medium text-yellow-800 dark:text-yellow-200">Important Notice</h3>
                                    <div class="mt-2 text-sm text-yellow-700 dark:text-yellow-300">
                                        <ul class="list-disc list-inside">
                                            <li>Send only <span id="selectedCrypto"></span> to this address</li>
                                            <li>Minimum deposit: <span id="minDeposit"></span></li>
                                            <li>Deposits require <span id="confirmations"></span> network confirmations</li>
                                            <li>Processing time: 10-60 minutes depending on network congestion</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mt-6">
                        <a href="{{ route('wallet.deposit') }}" class="inline-flex items-center px-4 py-2 bg-gray-300 dark:bg-gray-600 rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-400 dark:hover:bg-gray-500 transition ease-in-out duration-150">
                            Back to Deposit Options
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <!-- QR Code Library -->
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js"></script>
    
    <script>
        function selectCrypto(crypto) {
            // Remove active state from all options
            document.querySelectorAll('.crypto-option').forEach(el => {
                el.classList.remove('border-blue-500');
            });
            
            // Add active state to selected option
            event.target.closest('.crypto-option').classList.add('border-blue-500');
            
            // Show deposit details
            document.getElementById('depositDetails').classList.remove('hidden');
            
            // Update crypto-specific details
            const addresses = {
                'BTC': '1A1zP1eP5QGefi2DMPTfTL5SLmv7DivfNa',
                'ETH': '0x742d35Cc6634C0532925a3b844Bc9e7595f06789',
                'USDT': 'TN3W4H6rK2UM6GnKms9iFGQfVY73Gmwm7T'
            };
            
            const minDeposits = {
                'BTC': '0.001 BTC',
                'ETH': '0.01 ETH',
                'USDT': '10 USDT'
            };
            
            const confirmations = {
                'BTC': '3',
                'ETH': '12',
                'USDT': '20'
            };
            
            const address = addresses[crypto];
            document.getElementById('cryptoAddress').value = address;
            document.getElementById('selectedCrypto').textContent = crypto;
            document.getElementById('minDeposit').textContent = minDeposits[crypto];
            document.getElementById('confirmations').textContent = confirmations[crypto];
            
            // Generate QR code
            generateQRCode(address, crypto);
        }
        
        function generateQRCode(address, crypto) {
            const qrcodeContainer = document.getElementById('qrcode');
            qrcodeContainer.innerHTML = ''; // Clear existing content
            
            // Create canvas element
            const canvas = document.createElement('canvas');
            canvas.style.display = 'block';
            qrcodeContainer.appendChild(canvas);
            
            // Generate QR code
            QRCode.toCanvas(canvas, address, {
                width: 200,
                height: 200,
                margin: 2,
                color: {
                    dark: '#000000',
                    light: '#FFFFFF'
                },
                errorCorrectionLevel: 'H'
            }, function (error) {
                if (error) {
                    console.error('QR Code generation error:', error);
                    qrcodeContainer.innerHTML = '<div class="text-red-500 text-sm">Error generating QR code</div>';
                }
            });
            
            // Add address text below QR code
            const addressText = document.createElement('div');
            addressText.className = 'mt-2 text-xs text-gray-600 dark:text-gray-400 break-all text-center max-w-[200px]';
            addressText.textContent = address;
            qrcodeContainer.appendChild(addressText);
        }
        
        function copyAddress() {
            const addressInput = document.getElementById('cryptoAddress');
            
            // Use modern clipboard API if available
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(addressInput.value).then(() => {
                    showCopyFeedback();
                }).catch(() => {
                    fallbackCopy();
                });
            } else {
                fallbackCopy();
            }
        }
        
        function fallbackCopy() {
            const addressInput = document.getElementById('cryptoAddress');
            addressInput.select();
            addressInput.setSelectionRange(0, 99999); // For mobile devices
            
            try {
                document.execCommand('copy');
                showCopyFeedback();
            } catch (err) {
                console.error('Failed to copy:', err);
            }
        }
        
        function showCopyFeedback() {
            const button = event.target;
            const originalText = button.textContent;
            button.textContent = 'Copied!';
            button.classList.add('bg-green-600', 'hover:bg-green-700');
            button.classList.remove('bg-blue-600', 'hover:bg-blue-700');
            
            setTimeout(() => {
                button.textContent = originalText;
                button.classList.remove('bg-green-600', 'hover:bg-green-700');
                button.classList.add('bg-blue-600', 'hover:bg-blue-700');
            }, 2000);
        }
    </script>
    @endpush
</x-app-layout>