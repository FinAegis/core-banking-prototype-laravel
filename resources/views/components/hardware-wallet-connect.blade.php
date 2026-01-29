@props([
    'chain' => 'ethereum',
    'onConnect' => null,
    'onError' => null,
])

<div x-data="hardwareWalletConnect()" class="hardware-wallet-connect">
    {{-- Device Selection --}}
    <div x-show="!connected" class="space-y-4">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Connect Hardware Wallet</h3>

        {{-- Browser Check --}}
        <div x-show="!isSupported" class="p-4 bg-yellow-50 dark:bg-yellow-900/20 rounded-lg">
            <div class="flex items-center">
                <svg class="w-5 h-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                </svg>
                <span class="ml-2 text-sm text-yellow-800 dark:text-yellow-200">
                    WebUSB is not supported in this browser. Please use Chrome or Edge.
                </span>
            </div>
        </div>

        {{-- Device Type Selection --}}
        <div x-show="isSupported" class="grid grid-cols-2 gap-4">
            {{-- Ledger --}}
            <button
                @click="connectLedger()"
                :disabled="loading"
                class="flex flex-col items-center p-6 border-2 border-gray-200 dark:border-gray-700 rounded-lg hover:border-blue-500 dark:hover:border-blue-400 transition-colors disabled:opacity-50"
            >
                <div class="w-16 h-16 flex items-center justify-center bg-gray-100 dark:bg-gray-800 rounded-full mb-3">
                    <svg class="w-8 h-8 text-gray-600 dark:text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M14.5 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V7.5L14.5 2z"/>
                    </svg>
                </div>
                <span class="font-medium text-gray-900 dark:text-white">Ledger</span>
                <span class="text-sm text-gray-500 dark:text-gray-400">Nano S / Nano X</span>
            </button>

            {{-- Trezor --}}
            <button
                @click="connectTrezor()"
                :disabled="loading"
                class="flex flex-col items-center p-6 border-2 border-gray-200 dark:border-gray-700 rounded-lg hover:border-blue-500 dark:hover:border-blue-400 transition-colors disabled:opacity-50"
            >
                <div class="w-16 h-16 flex items-center justify-center bg-gray-100 dark:bg-gray-800 rounded-full mb-3">
                    <svg class="w-8 h-8 text-gray-600 dark:text-gray-400" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2L2 7l10 5 10-5-10-5zM2 17l10 5 10-5M2 12l10 5 10-5"/>
                    </svg>
                </div>
                <span class="font-medium text-gray-900 dark:text-white">Trezor</span>
                <span class="text-sm text-gray-500 dark:text-gray-400">One / Model T</span>
            </button>
        </div>

        {{-- Loading State --}}
        <div x-show="loading" class="flex items-center justify-center py-4">
            <svg class="animate-spin h-6 w-6 text-blue-500" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
            </svg>
            <span class="ml-3 text-sm text-gray-600 dark:text-gray-400" x-text="loadingMessage"></span>
        </div>

        {{-- Error Message --}}
        <div x-show="error" class="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
            <div class="flex items-start">
                <svg class="w-5 h-5 text-red-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <div class="ml-2">
                    <span class="text-sm text-red-800 dark:text-red-200" x-text="error"></span>
                    <button @click="error = null" class="block mt-1 text-xs text-red-600 dark:text-red-400 hover:underline">
                        Dismiss
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Connected State --}}
    <div x-show="connected" class="space-y-4">
        <div class="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
            <div class="flex items-center justify-between">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800 dark:text-green-200">
                            Connected to <span x-text="deviceName"></span>
                        </p>
                        <p class="text-xs text-green-600 dark:text-green-400 font-mono truncate max-w-xs" x-text="address"></p>
                    </div>
                </div>
                <button
                    @click="disconnect()"
                    class="text-sm text-green-600 dark:text-green-400 hover:underline"
                >
                    Disconnect
                </button>
            </div>
        </div>

        {{-- Registration Status --}}
        <div x-show="registered" class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
            <p class="text-sm text-blue-800 dark:text-blue-200">
                <svg class="inline w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                Device registered with FinAegis
            </p>
        </div>

        {{-- Register Button --}}
        <button
            x-show="!registered"
            @click="registerDevice()"
            :disabled="registering"
            class="w-full flex items-center justify-center px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg disabled:opacity-50 transition-colors"
        >
            <span x-show="!registering">Register Device</span>
            <span x-show="registering" class="flex items-center">
                <svg class="animate-spin h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                </svg>
                Registering...
            </span>
        </button>
    </div>
</div>

<script>
function hardwareWalletConnect() {
    return {
        isSupported: typeof navigator !== 'undefined' && navigator.usb !== undefined,
        loading: false,
        loadingMessage: '',
        error: null,
        connected: false,
        deviceType: null,
        deviceName: null,
        address: null,
        publicKey: null,
        deviceInfo: null,
        registered: false,
        registering: false,
        chain: '{{ $chain }}',

        async connectLedger() {
            this.loading = true;
            this.loadingMessage = 'Connecting to Ledger...';
            this.error = null;

            try {
                // Connect to device
                this.deviceInfo = await window.hardwareWalletService.connectLedger();
                this.deviceType = this.deviceInfo.type;
                this.deviceName = this.deviceInfo.productName || 'Ledger';

                // Get address
                this.loadingMessage = 'Getting address from device...';
                const derivationPath = this.getDerivationPath('ledger');
                const result = await window.hardwareWalletService.getLedgerAddress(derivationPath);

                this.address = result.address;
                this.publicKey = result.publicKey;
                this.connected = true;

                // Dispatch event
                this.$dispatch('hardware-wallet-connected', {
                    type: this.deviceType,
                    address: this.address,
                    publicKey: this.publicKey,
                });
            } catch (err) {
                this.error = err.message;
                this.$dispatch('hardware-wallet-error', { error: err.message });
            } finally {
                this.loading = false;
                this.loadingMessage = '';
            }
        },

        async connectTrezor() {
            this.loading = true;
            this.loadingMessage = 'Connecting to Trezor...';
            this.error = null;

            try {
                await window.hardwareWalletService.initTrezor();

                // Get address
                this.loadingMessage = 'Getting address from Trezor...';
                const derivationPath = this.getDerivationPath('trezor');
                const result = await window.hardwareWalletService.getTrezorAddress(derivationPath);

                this.address = result.address;
                this.deviceType = 'trezor_model_t';
                this.deviceName = 'Trezor';
                this.deviceInfo = {
                    type: 'trezor_model_t',
                    deviceId: 'trezor_' + Date.now(),
                };
                this.connected = true;

                this.$dispatch('hardware-wallet-connected', {
                    type: this.deviceType,
                    address: this.address,
                });
            } catch (err) {
                this.error = err.message;
                this.$dispatch('hardware-wallet-error', { error: err.message });
            } finally {
                this.loading = false;
                this.loadingMessage = '';
            }
        },

        async disconnect() {
            try {
                await window.hardwareWalletService.disconnect();
            } catch (e) {
                console.warn('Disconnect error:', e);
            }

            this.connected = false;
            this.deviceType = null;
            this.deviceName = null;
            this.address = null;
            this.publicKey = null;
            this.registered = false;

            this.$dispatch('hardware-wallet-disconnected');
        },

        async registerDevice() {
            this.registering = true;
            this.error = null;

            try {
                const derivationPath = this.getDerivationPath(this.deviceType);
                const result = await window.registerHardwareWallet(
                    this.deviceInfo,
                    this.address,
                    this.publicKey || this.address,
                    this.chain,
                    derivationPath
                );

                this.registered = true;
                this.$dispatch('hardware-wallet-registered', {
                    associationId: result.data.association_id,
                    address: this.address,
                });
            } catch (err) {
                this.error = err.message;
            } finally {
                this.registering = false;
            }
        },

        getDerivationPath(deviceType) {
            const coinType = this.chain === 'bitcoin' ? 0 : 60;
            const prefix = deviceType === 'trezor' || deviceType?.startsWith('trezor') ? 'm/' : '';
            return `${prefix}44'/${coinType}'/0'/0/0`;
        },
    };
}
</script>
