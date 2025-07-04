# Wallet Management System Architecture

## Overview

The Finaegis Wallet Management System provides a unified interface for managing both traditional banking wallets and blockchain-based crypto wallets. This system bridges the gap between traditional finance and decentralized finance (DeFi) by offering seamless integration with multiple blockchain networks while maintaining the security and compliance requirements of a banking platform.

## System Architecture

### Core Components

```
┌─────────────────────────────────────────────────────────────────┐
│                     Wallet Management System                      │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌───────────────┐  ┌────────────────┐  ┌──────────────────┐  │
│  │ Wallet Factory│  │ Key Management │  │ Blockchain       │  │
│  │   Service     │  │    Service     │  │  Connectors      │  │
│  └───────────────┘  └────────────────┘  └──────────────────┘  │
│                                                                  │
│  ┌───────────────┐  ┌────────────────┐  ┌──────────────────┐  │
│  │  HD Wallet    │  │  Transaction   │  │    Security      │  │
│  │  Generator    │  │    Builder     │  │   Middleware     │  │
│  └───────────────┘  └────────────────┘  └──────────────────┘  │
│                                                                  │
│  ┌───────────────┐  ┌────────────────┐  ┌──────────────────┐  │
│  │   Balance     │  │    Gas Fee     │  │     Event        │  │
│  │   Tracker     │  │   Estimator    │  │    Monitor       │  │
│  └───────────────┘  └────────────────┘  └──────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

### Wallet Types

1. **Traditional Wallets** (Existing)
   - Account-based ledger system
   - Multi-currency support (USD, EUR, GBP, GCU)
   - Instant internal transfers
   - Regulatory compliant

2. **Blockchain Wallets** (New)
   - **Non-Custodial Wallets**: User controls private keys
   - **Custodial Wallets**: Platform manages keys with HSM
   - **Smart Contract Wallets**: Multi-sig and programmable
   - **Hardware Wallet Integration**: Ledger, Trezor support

### Supported Blockchains

1. **EVM-Compatible Chains**
   - Ethereum Mainnet
   - Polygon
   - Binance Smart Chain
   - Arbitrum
   - Optimism

2. **Bitcoin Network**
   - Native SegWit addresses
   - Lightning Network integration

3. **Other Chains** (Future)
   - Solana
   - Cosmos/IBC
   - Polkadot

## Key Management Architecture

### Hierarchical Deterministic (HD) Wallet Structure

```
Master Seed (BIP39)
    │
    ├── m/44'/60'/0'/0/x    (Ethereum addresses)
    ├── m/44'/0'/0'/0/x     (Bitcoin addresses)
    ├── m/44'/966'/0'/0/x   (Polygon addresses)
    └── m/44'/xxx'/0'/0/x   (Other chains)
```

### Security Layers

1. **Key Storage**
   - Hardware Security Module (HSM) for master keys
   - Encrypted database storage for derived keys
   - Key rotation policies
   - Backup and recovery procedures

2. **Access Control**
   - Multi-factor authentication for wallet access
   - Transaction limits and velocity checks
   - IP whitelisting for API access
   - Role-based permissions

3. **Transaction Security**
   - Multi-signature requirements for high-value transfers
   - Time-locked transactions
   - Withdrawal address whitelisting
   - Real-time fraud detection

## Implementation Components

### 1. Wallet Aggregate (Event Sourced)

```php
namespace App\Domain\Wallet\Aggregates;

class BlockchainWallet extends AggregateRoot
{
    protected string $walletId;
    protected string $userId;
    protected string $type; // 'custodial', 'non-custodial', 'smart-contract'
    protected array $addresses = []; // chain => address mapping
    protected array $publicKeys = [];
    protected string $status = 'active';
    protected array $settings = [];
    
    // Core wallet operations
    public function createWallet(...);
    public function generateAddress($chain);
    public function updateSettings($settings);
    public function freeze($reason);
    public function unfreeze();
}
```

### 2. Blockchain Connectors

```php
namespace App\Domain\Wallet\Connectors;

interface BlockchainConnector
{
    public function generateAddress(): AddressData;
    public function getBalance(string $address): BalanceData;
    public function estimateGas(TransactionData $tx): GasEstimate;
    public function broadcastTransaction(SignedTransaction $tx): TransactionResult;
    public function getTransaction(string $hash): TransactionData;
    public function subscribeToEvents(string $address, callable $callback): void;
}
```

### 3. Transaction Builder

```php
namespace App\Domain\Wallet\Services;

class TransactionBuilder
{
    public function buildTransfer($from, $to, $amount, $asset): Transaction;
    public function buildContractCall($contract, $method, $params): Transaction;
    public function buildMultiSend($from, array $recipients): Transaction;
    public function addGasSettings($tx, $gasPrice, $gasLimit): Transaction;
    public function signTransaction($tx, $privateKey): SignedTransaction;
}
```

### 4. Balance Aggregator

```php
namespace App\Domain\Wallet\Services;

class BalanceAggregator
{
    public function getUnifiedBalance(string $userId): UnifiedBalance;
    public function getChainBalance(string $userId, string $chain): ChainBalance;
    public function getTokenBalances(string $address, string $chain): array;
    public function subscribeToBalanceUpdates(string $userId, callable $callback): void;
}
```

## Workflow Integration

### Deposit Workflow
```
1. User initiates deposit
2. Generate unique deposit address
3. Monitor blockchain for incoming transactions
4. Confirm transaction (wait for confirmations)
5. Credit user's internal balance
6. Emit deposit completed event
```

### Withdrawal Workflow
```
1. User requests withdrawal
2. Validate withdrawal limits and KYC
3. Build blockchain transaction
4. Sign transaction (HSM or user key)
5. Broadcast to network
6. Monitor for confirmation
7. Update internal balance
8. Emit withdrawal completed event
```

### Cross-Chain Transfer Workflow
```
1. Lock assets on source chain
2. Generate proof of lock
3. Submit proof to destination chain
4. Mint/unlock assets on destination
5. Update balances on both chains
```

## Security Considerations

### 1. Private Key Security
- Never store raw private keys
- Use HSM for custodial wallets
- Implement key sharding for recovery
- Regular security audits

### 2. Transaction Validation
- Implement spending limits
- Require multi-sig for large amounts
- Validate destination addresses
- Check against blacklisted addresses

### 3. Smart Contract Security
- Use audited contract templates
- Implement upgrade mechanisms
- Emergency pause functionality
- Time-locked admin functions

## Integration Points

### 1. With Exchange System
- Direct trading from wallet balances
- Automatic deposit to exchange accounts
- Withdrawal to personal wallets

### 2. With Stablecoin System
- Mint stablecoins using crypto collateral
- Direct stablecoin transfers
- Yield farming integration

### 3. With Traditional Banking
- Fiat on/off ramps
- Unified balance view
- Regulatory reporting

## API Design

### REST API Endpoints

```
POST   /api/wallets                    # Create new wallet
GET    /api/wallets/{userId}           # List user wallets
GET    /api/wallets/{walletId}         # Get wallet details
POST   /api/wallets/{walletId}/addresses # Generate new address
GET    /api/wallets/{walletId}/balance  # Get wallet balance
POST   /api/wallets/{walletId}/transfer # Initiate transfer
GET    /api/wallets/{walletId}/transactions # Transaction history
POST   /api/wallets/{walletId}/estimate-gas # Estimate transaction gas
```

### WebSocket Events

```
wallet.balance.updated
wallet.transaction.pending
wallet.transaction.confirmed
wallet.transaction.failed
wallet.address.generated
```

## Database Schema

### wallets table
```sql
- id
- wallet_id (uuid)
- user_id
- type (custodial/non-custodial/smart-contract)
- status
- metadata (json)
- created_at
- updated_at
```

### wallet_addresses table
```sql
- id
- wallet_id
- chain
- address
- public_key
- derivation_path
- label
- is_active
- created_at
```

### blockchain_transactions table
```sql
- id
- wallet_id
- chain
- transaction_hash
- from_address
- to_address
- amount
- asset
- gas_used
- gas_price
- status
- confirmations
- block_number
- metadata (json)
- created_at
- confirmed_at
```

## Implementation Phases

### Phase 1: Core Infrastructure (Week 1-2)
- Wallet aggregate and events
- Key management service
- Basic Ethereum connector
- HD wallet generation

### Phase 2: Blockchain Integration (Week 3-4)
- Multi-chain connectors
- Transaction builder
- Gas estimation
- Event monitoring

### Phase 3: Security & UI (Week 5-6)
- HSM integration
- Multi-sig implementation
- Web UI for wallet management
- Mobile app support

### Phase 4: Advanced Features (Week 7-8)
- DeFi integrations
- Cross-chain bridges
- Hardware wallet support
- Advanced analytics

## Monitoring & Maintenance

### Key Metrics
- Wallet creation rate
- Transaction success rate
- Gas optimization efficiency
- Balance sync accuracy
- Security incident rate

### Alerts
- Failed transactions
- Unusual withdrawal patterns
- Low gas wallet balances
- Network congestion
- Security breaches

## Compliance & Regulations

### KYC/AML Integration
- Identity verification for wallet creation
- Transaction monitoring
- Suspicious activity reporting
- Sanctions screening

### Reporting
- Daily transaction reports
- Monthly balance reconciliation
- Regulatory submissions
- Audit trails

## Future Enhancements

1. **Layer 2 Integration**
   - Lightning Network for Bitcoin
   - State channels
   - Rollup solutions

2. **DeFi Features**
   - Yield aggregation
   - Liquidity provision
   - Automated strategies

3. **NFT Support**
   - NFT wallet view
   - Marketplace integration
   - NFT-based collateral

4. **Social Recovery**
   - Guardian-based recovery
   - Social key sharding
   - Time-locked recovery

## Implementation Status

### Phase 1: Core Infrastructure (✅ Completed)
- [x] Create base wallet aggregate and events
- [x] Implement key management service  
- [x] Build blockchain connectors (Ethereum, Polygon, BSC, Bitcoin)
- [x] Set up database schema
- [x] Create wallet projector
- [x] Implement comprehensive test suite

### Phase 2: API & Workflows (✅ Completed)
- [x] REST API controllers
- [x] Deposit workflow with saga pattern
- [x] Withdrawal workflow with saga pattern
- [x] Transaction monitoring
- [x] API resources and validation

### Phase 3: Security & UI (🔄 Pending)
- [ ] Hardware wallet integration
- [ ] Multi-signature support
- [ ] Advanced 2FA mechanisms
- [ ] Web UI components
- [ ] Mobile app support

### Phase 4: DeFi Integration (🔄 Pending)
- [ ] Smart contract wallets
- [ ] DeFi protocol connectors
- [ ] Yield optimization
- [ ] Cross-chain bridges

## Implemented Components

### Core Domain
- `BlockchainWallet` aggregate with event sourcing
- `BlockchainWalletCreated`, `WalletAddressGenerated`, `WalletFrozen` events
- `KeyManagementService` for HD wallet and encryption
- `BlockchainWalletService` orchestration layer

### Blockchain Connectors
- `EthereumConnector` - Ethereum mainnet support
- `PolygonConnector` - Polygon/Matic support
- `BitcoinConnector` - Bitcoin network support
- BSC support via Ethereum connector

### Workflows
- `BlockchainDepositWorkflow` - Handles incoming deposits
- `BlockchainWithdrawalWorkflow` - Handles outgoing withdrawals
- Transaction monitoring and confirmation tracking

### API Endpoints
- `POST /api/blockchain-wallets` - Create wallet
- `GET /api/blockchain-wallets` - List wallets
- `GET /api/blockchain-wallets/{id}` - Get wallet details
- `POST /api/blockchain-wallets/{id}/addresses` - Generate address
- `GET /api/blockchain-wallets/{id}/transactions` - Transaction history
- `POST /api/blockchain-wallets/generate-mnemonic` - Generate mnemonic

### Database Tables
- `blockchain_wallets` - Wallet projections
- `wallet_addresses` - Generated addresses
- `blockchain_transactions` - Transaction records
- `wallet_seeds` - Encrypted seed storage
- `token_balances` - ERC20/BEP20 balances
- `wallet_backups` - Backup records
- `blockchain_withdrawals` - Withdrawal tracking