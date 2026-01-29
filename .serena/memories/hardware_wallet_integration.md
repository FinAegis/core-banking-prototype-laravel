# Hardware Wallet Integration (v2.1.0)

## Overview
Hardware wallet support for Ledger and Trezor devices using WebUSB for browser-based device communication.

## Architecture

### Key Files
- `app/Domain/Wallet/Contracts/ExternalSignerInterface.php` - Abstraction for hardware wallets
- `app/Domain/Wallet/Services/HardwareWallet/LedgerSignerService.php` - Ledger implementation
- `app/Domain/Wallet/Services/HardwareWallet/TrezorSignerService.php` - Trezor implementation
- `app/Domain/Wallet/Services/HardwareWallet/HardwareWalletManager.php` - Coordination service
- `app/Http/Controllers/Api/HardwareWalletController.php` - REST API endpoints

### Database Tables
- `hardware_wallet_associations` - Links hardware wallets to users/addresses
- `pending_signing_requests` - Tracks async signing request lifecycle

### Events
- `HardwareWalletConnected` - Device registration
- `HardwareWalletSigningRequested` - Signing request created
- `HardwareWalletSigningCompleted` - Signing finished (success or failure)

## API Endpoints
```
POST   /api/hardware-wallet/register                    - Register device
POST   /api/hardware-wallet/signing-request             - Create signing request
POST   /api/hardware-wallet/signing-request/{id}/submit - Submit signature
GET    /api/hardware-wallet/signing-request/{id}        - Get request status
GET    /api/hardware-wallet/associations                - List user's devices
DELETE /api/hardware-wallet/associations/{uuid}         - Remove device
```

## Supported Devices
- Ledger Nano S, Ledger Nano X
- Trezor One, Trezor Model T

## Supported Chains
- Ethereum (chain_id: 1)
- Polygon (chain_id: 137)
- BSC (chain_id: 56)
- Bitcoin

## Security Features
1. **Input Validation**: Regex patterns for addresses, signatures, public keys
2. **Public Key Verification**: Validates submitted key matches registered device
3. **Address Verification**: Ensures transaction 'from' matches device address
4. **Race Condition Prevention**: Database transactions with row locking
5. **Request TTL**: Signing requests expire (configurable, default 5 minutes)
6. **Device Limits**: Max associations per user (default: 10)
7. **Pending Request Limits**: Max concurrent requests (default: 5)

## Configuration
```php
// config/blockchain.php
'hardware_wallets' => [
    'enabled' => env('HARDWARE_WALLETS_ENABLED', true),
    'ledger' => ['supported_models' => ['nano_s', 'nano_x']],
    'trezor' => ['supported_models' => ['one', 'model_t']],
    'signing_request' => ['ttl_seconds' => 300],
    'security' => [
        'max_associations_per_user' => 10,
        'max_pending_requests' => 5,
    ],
],
```

## Testing
```bash
# Unit tests
./vendor/bin/pest tests/Domain/Wallet/Services/HardwareWallet/

# Feature tests (requires Redis)
./vendor/bin/pest tests/Feature/HardwareWallet/
```

## Production Notes
⚠️ This implementation is designed for educational/prototype use. For production:
- Implement proper ECDSA signature verification using cryptography library
- Add HSM integration for key operations
- Conduct formal security audit
- Implement rate limiting at multiple layers

## BIP44 Derivation Paths
- Ethereum/Polygon/BSC: `m/44'/60'/0'/0/{index}`
- Bitcoin: `m/44'/0'/0'/0/{index}`
