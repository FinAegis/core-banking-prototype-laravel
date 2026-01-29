/**
 * Hardware Wallet Service
 *
 * Provides WebUSB communication with Ledger and Trezor hardware wallets.
 * This service handles device connection, address retrieval, and transaction signing.
 *
 * Supported devices:
 * - Ledger Nano S/X (via @ledgerhq/hw-transport-webusb)
 * - Trezor One/Model T (via @trezor/connect-web)
 *
 * Browser Requirements:
 * - Chrome 61+ (WebUSB support)
 * - Edge 79+ (Chromium-based)
 * - Firefox (limited - may need fallback)
 */

// Lazy load dependencies to avoid build issues if packages not installed
let TransportWebUSB = null;
let Eth = null;
let TrezorConnect = null;

/**
 * Load Ledger dependencies
 */
async function loadLedgerDependencies() {
    if (!TransportWebUSB) {
        try {
            const transportModule = await import('@ledgerhq/hw-transport-webusb');
            TransportWebUSB = transportModule.default;
        } catch (e) {
            console.warn('Ledger WebUSB transport not available:', e.message);
            throw new Error('Ledger support requires @ledgerhq/hw-transport-webusb package');
        }
    }
    if (!Eth) {
        try {
            const ethModule = await import('@ledgerhq/hw-app-eth');
            Eth = ethModule.default;
        } catch (e) {
            console.warn('Ledger Ethereum app not available:', e.message);
            throw new Error('Ledger support requires @ledgerhq/hw-app-eth package');
        }
    }
}

/**
 * Load Trezor dependencies
 */
async function loadTrezorDependencies() {
    if (!TrezorConnect) {
        try {
            const trezorModule = await import('@trezor/connect-web');
            TrezorConnect = trezorModule.default;
            // Initialize Trezor Connect
            await TrezorConnect.init({
                lazyLoad: true,
                manifest: {
                    email: 'support@finaegis.com',
                    appUrl: window.location.origin,
                },
            });
        } catch (e) {
            console.warn('Trezor Connect not available:', e.message);
            throw new Error('Trezor support requires @trezor/connect-web package');
        }
    }
}

/**
 * Hardware Wallet Service Class
 */
class HardwareWalletService {
    constructor() {
        this.transport = null;
        this.deviceType = null;
        this.connected = false;
    }

    /**
     * Check if WebUSB is supported in the current browser
     */
    isWebUSBSupported() {
        return typeof navigator !== 'undefined' && navigator.usb !== undefined;
    }

    /**
     * Get supported device types
     */
    getSupportedDevices() {
        return [
            { type: 'ledger_nano_s', name: 'Ledger Nano S' },
            { type: 'ledger_nano_x', name: 'Ledger Nano X' },
            { type: 'trezor_one', name: 'Trezor One' },
            { type: 'trezor_model_t', name: 'Trezor Model T' },
        ];
    }

    /**
     * Connect to a Ledger device via WebUSB
     */
    async connectLedger() {
        if (!this.isWebUSBSupported()) {
            throw new Error('WebUSB is not supported in this browser');
        }

        await loadLedgerDependencies();

        try {
            this.transport = await TransportWebUSB.create();
            this.deviceType = 'ledger';
            this.connected = true;

            // Get device info
            const deviceInfo = this.transport.device;
            return {
                type: deviceInfo.productName?.includes('Nano X') ? 'ledger_nano_x' : 'ledger_nano_s',
                deviceId: deviceInfo.serialNumber || `ledger_${Date.now()}`,
                productName: deviceInfo.productName,
                manufacturerName: deviceInfo.manufacturerName,
            };
        } catch (error) {
            this.connected = false;
            throw new Error(`Failed to connect to Ledger: ${error.message}`);
        }
    }

    /**
     * Get Ethereum address from Ledger
     */
    async getLedgerAddress(derivationPath = "44'/60'/0'/0/0") {
        if (!this.connected || this.deviceType !== 'ledger') {
            throw new Error('Ledger not connected');
        }

        await loadLedgerDependencies();

        const eth = new Eth(this.transport);
        const result = await eth.getAddress(derivationPath, false);

        return {
            address: result.address,
            publicKey: result.publicKey,
            chainCode: result.chainCode,
        };
    }

    /**
     * Sign a transaction with Ledger
     */
    async signWithLedger(derivationPath, rawTxHex) {
        if (!this.connected || this.deviceType !== 'ledger') {
            throw new Error('Ledger not connected');
        }

        await loadLedgerDependencies();

        const eth = new Eth(this.transport);

        // Remove 0x prefix if present
        const txHex = rawTxHex.startsWith('0x') ? rawTxHex.slice(2) : rawTxHex;

        const signature = await eth.signTransaction(derivationPath, txHex);

        return {
            v: signature.v,
            r: '0x' + signature.r,
            s: '0x' + signature.s,
            signature: '0x' + signature.r + signature.s + signature.v,
        };
    }

    /**
     * Disconnect Ledger
     */
    async disconnectLedger() {
        if (this.transport) {
            await this.transport.close();
            this.transport = null;
        }
        this.deviceType = null;
        this.connected = false;
    }

    /**
     * Initialize Trezor Connect
     */
    async initTrezor() {
        await loadTrezorDependencies();
        this.deviceType = 'trezor';
        return true;
    }

    /**
     * Get Ethereum address from Trezor
     */
    async getTrezorAddress(derivationPath = "m/44'/60'/0'/0/0") {
        await loadTrezorDependencies();

        const result = await TrezorConnect.ethereumGetAddress({
            path: derivationPath,
            showOnTrezor: true,
        });

        if (!result.success) {
            throw new Error(`Trezor error: ${result.payload.error}`);
        }

        this.connected = true;
        this.deviceType = 'trezor';

        return {
            address: result.payload.address,
            path: result.payload.serializedPath,
        };
    }

    /**
     * Sign a transaction with Trezor
     */
    async signWithTrezor(derivationPath, transaction) {
        await loadTrezorDependencies();

        const result = await TrezorConnect.ethereumSignTransaction({
            path: derivationPath,
            transaction: {
                to: transaction.to,
                value: transaction.value,
                gasPrice: transaction.gasPrice,
                gasLimit: transaction.gasLimit,
                nonce: transaction.nonce,
                chainId: transaction.chainId,
                data: transaction.data || '',
            },
        });

        if (!result.success) {
            throw new Error(`Trezor signing error: ${result.payload.error}`);
        }

        return {
            v: result.payload.v,
            r: result.payload.r,
            s: result.payload.s,
            signature: result.payload.r + result.payload.s.slice(2) + result.payload.v.slice(2),
        };
    }

    /**
     * Get Bitcoin address from Trezor
     */
    async getTrezorBitcoinAddress(derivationPath = "m/44'/0'/0'/0/0") {
        await loadTrezorDependencies();

        const result = await TrezorConnect.getAddress({
            path: derivationPath,
            coin: 'btc',
            showOnTrezor: true,
        });

        if (!result.success) {
            throw new Error(`Trezor error: ${result.payload.error}`);
        }

        return {
            address: result.payload.address,
            path: result.payload.serializedPath,
        };
    }

    /**
     * Disconnect from current device
     */
    async disconnect() {
        if (this.deviceType === 'ledger') {
            await this.disconnectLedger();
        }
        this.connected = false;
        this.deviceType = null;
    }

    /**
     * Check if currently connected
     */
    isConnected() {
        return this.connected;
    }

    /**
     * Get current device type
     */
    getDeviceType() {
        return this.deviceType;
    }
}

/**
 * Create a singleton instance
 */
const hardwareWalletService = new HardwareWalletService();

/**
 * Helper function to register a hardware wallet with the backend
 */
async function registerHardwareWallet(deviceInfo, address, publicKey, chain, derivationPath) {
    const response = await fetch('/api/hardware-wallet/register', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
        },
        credentials: 'include',
        body: JSON.stringify({
            device_type: deviceInfo.type,
            device_id: deviceInfo.deviceId,
            device_label: deviceInfo.productName || deviceInfo.type,
            public_key: publicKey,
            address: address,
            chain: chain,
            derivation_path: derivationPath,
            supported_chains: ['ethereum', 'polygon', 'bsc'],
        }),
    });

    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.error || error.message || 'Registration failed');
    }

    return response.json();
}

/**
 * Helper function to create a signing request
 */
async function createSigningRequest(associationId, transaction) {
    const response = await fetch('/api/hardware-wallet/signing-request', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
        },
        credentials: 'include',
        body: JSON.stringify({
            association_id: associationId,
            transaction: transaction,
        }),
    });

    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.error || error.message || 'Failed to create signing request');
    }

    return response.json();
}

/**
 * Helper function to submit a signature
 */
async function submitSignature(requestId, signature, publicKey) {
    const response = await fetch(`/api/hardware-wallet/signing-request/${requestId}/submit`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
        },
        credentials: 'include',
        body: JSON.stringify({
            signature: signature,
            public_key: publicKey,
        }),
    });

    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.error || error.message || 'Failed to submit signature');
    }

    return response.json();
}

/**
 * Helper function to get signing request status
 */
async function getSigningRequestStatus(requestId) {
    const response = await fetch(`/api/hardware-wallet/signing-request/${requestId}`, {
        method: 'GET',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content,
        },
        credentials: 'include',
    });

    if (!response.ok) {
        const error = await response.json();
        throw new Error(error.error || error.message || 'Failed to get status');
    }

    return response.json();
}

/**
 * Complete hardware wallet signing flow
 */
async function signTransactionWithHardwareWallet(associationId, transaction, deviceType, derivationPath) {
    // Step 1: Create signing request on backend
    const signingRequest = await createSigningRequest(associationId, transaction);
    const requestId = signingRequest.data.request_id;
    const rawDataToSign = signingRequest.data.raw_data_to_sign;

    // Step 2: Sign with hardware wallet
    let signature;
    if (deviceType.startsWith('ledger')) {
        signature = await hardwareWalletService.signWithLedger(derivationPath, rawDataToSign);
    } else if (deviceType.startsWith('trezor')) {
        signature = await hardwareWalletService.signWithTrezor(derivationPath, transaction);
    } else {
        throw new Error(`Unsupported device type: ${deviceType}`);
    }

    // Step 3: Submit signature to backend
    const result = await submitSignature(requestId, signature.signature, transaction.from);

    return result;
}

// Export for module use
export {
    hardwareWalletService,
    HardwareWalletService,
    registerHardwareWallet,
    createSigningRequest,
    submitSignature,
    getSigningRequestStatus,
    signTransactionWithHardwareWallet,
};

// Also make available globally for non-module scripts
if (typeof window !== 'undefined') {
    window.HardwareWalletService = HardwareWalletService;
    window.hardwareWalletService = hardwareWalletService;
    window.registerHardwareWallet = registerHardwareWallet;
    window.createSigningRequest = createSigningRequest;
    window.submitSignature = submitSignature;
    window.signTransactionWithHardwareWallet = signTransactionWithHardwareWallet;
}
