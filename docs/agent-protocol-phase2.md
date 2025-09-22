# Agent Protocol Phase 2 (AP2) Implementation

## Overview

The Agent Protocol Phase 2 (AP2) implementation provides a comprehensive payment orchestration system for AI agent-to-agent (A2A) commerce. This system enables secure, automated transactions between AI agents using Decentralized Identifiers (DIDs), with built-in support for escrow, fee management, payment splitting, and dispute resolution.

## Architecture

### Core Components

#### 1. Payment Orchestration Workflow
- **Location**: `app/Domain/AgentProtocol/Workflows/PaymentOrchestrationWorkflow.php`
- **Purpose**: Main workflow orchestrating all payment operations
- **Features**:
  - Payment validation
  - Fee calculation and application
  - Escrow support for conditional payments
  - Split payment distribution
  - Retry logic with exponential backoff
  - Compensation for failed payments
  - Notification system

#### 2. Escrow Workflow
- **Location**: `app/Domain/AgentProtocol/Workflows/EscrowWorkflow.php`
- **Purpose**: Handles conditional payments with dispute resolution
- **Features**:
  - Condition-based fund release
  - Dispute management
  - Timeout handling
  - Automated or manual resolution

#### 3. Workflow Activities

The system uses Laravel Workflow activities for modular business logic:

- **ValidatePaymentActivity**: Validates payment requests and agent DIDs
- **ApplyFeesActivity**: Calculates and applies transaction fees
- **ProcessPaymentActivity**: Executes the actual fund transfer
- **RecordPaymentHistoryActivity**: Maintains immutable payment history
- **ProcessSplitPaymentActivity**: Distributes payments to multiple recipients
- **NotifyAgentActivity**: Sends notifications to agents
- **ReversePaymentActivity**: Handles payment reversals
- **CalculateExchangeRateActivity**: Multi-currency support
- **AuditPaymentActivity**: Compliance and audit logging
- **RaiseDisputeActivity**: Initiates dispute resolution

### Data Objects

#### AgentPaymentRequest
```php
public function __construct(
    public readonly string $fromAgentDid,
    public readonly string $toAgentDid,
    public readonly float $amount,
    public readonly string $currency,
    public readonly string $purpose,
    public readonly ?array $metadata = null,
    public readonly ?array $escrowConditions = null,
    public readonly ?array $splits = null,
    public readonly ?int $timeoutSeconds = 300,
    ?string $transactionId = null,
    ?Carbon $createdAt = null
)
```

#### PaymentResult
```php
class PaymentResult
{
    public string $transactionId;
    public string $status; // completed, failed, pending, disputed
    public float $amount;
    public float $fees;
    public float $totalAmount;
    public ?string $errorMessage = null;
    public ?array $metadata = null;
}
```

#### EscrowResult
```php
class EscrowResult
{
    public string $escrowId;
    public string $status; // created, funded, released, refunded, disputed, expired
    public float $amount;
    public ?array $conditions = null;
    public ?string $resolutionReason = null;
    public ?Carbon $releasedAt = null;
}
```

### Event Sourcing Aggregates

#### AgentWalletAggregate
- Manages agent wallet balances
- Records all transactions as events
- Supports snapshots for performance
- Methods:
  - `initiatePayment()`: Debit funds from wallet
  - `receivePayment()`: Credit funds to wallet
  - `freezeWallet()`: Prevent transactions
  - `unfreezeWallet()`: Resume transactions
  - `getBalance()`: Current wallet balance

#### PaymentHistoryAggregate
- Immutable payment record
- Audit trail for compliance
- Query capabilities for reporting

#### EscrowAggregate
- Manages escrow lifecycle
- Tracks condition states
- Handles dispute resolution

## Configuration

### Configuration File
**Location**: `config/agent_protocol.php`

```php
return [
    'enabled' => env('AGENT_PROTOCOL_ENABLED', true),

    // DID Settings
    'did' => [
        'verification_enabled' => env('DID_VERIFICATION_ENABLED', false),
        'signature_algorithm' => env('DID_SIGNATURE_ALGO', 'RS256'),
        'cache_ttl' => env('DID_CACHE_TTL', 3600),
    ],

    // Fee Structure
    'fees' => [
        'standard_rate' => env('FEE_STANDARD_RATE', 0.025), // 2.5%
        'minimum_fee' => env('FEE_MINIMUM', 0.50),
        'maximum_fee' => env('FEE_MAXIMUM', 100.00),
        'fee_collector_did' => env('FEE_COLLECTOR_DID', 'did:agent:finaegis:fee-collector'),
        'exemption_threshold' => env('FEE_EXEMPTION_THRESHOLD', 1.00),
    ],

    // Escrow Settings
    'escrow' => [
        'minimum_amount' => env('ESCROW_MIN_AMOUNT', 10.00),
        'maximum_amount' => env('ESCROW_MAX_AMOUNT', 1000000.00),
        'default_timeout' => env('ESCROW_DEFAULT_TIMEOUT', 86400),
        'dispute_timeout' => env('ESCROW_DISPUTE_TIMEOUT', 3600),
        'auto_release_enabled' => env('ESCROW_AUTO_RELEASE', true),
    ],

    // System Agents
    'system_agents' => [
        'admin_dids' => explode(',', env('SYSTEM_ADMIN_DIDS', '')),
        'system_did' => env('SYSTEM_AGENT_DID', 'did:agent:finaegis:system'),
        'treasury_did' => env('TREASURY_AGENT_DID', 'did:agent:finaegis:treasury'),
        'reserve_did' => env('RESERVE_AGENT_DID', 'did:agent:finaegis:reserve'),
    ],
];
```

## Usage Examples

### Simple Payment

```php
use App\Domain\AgentProtocol\DataObjects\AgentPaymentRequest;
use App\Domain\AgentProtocol\Workflows\PaymentOrchestrationWorkflow;
use Workflow\WorkflowStub;

// Create payment request
$request = new AgentPaymentRequest(
    fromAgentDid: 'did:agent:buyer:123',
    toAgentDid: 'did:agent:seller:456',
    amount: 100.00,
    currency: 'USD',
    purpose: 'purchase'
);

// Execute workflow
$workflow = WorkflowStub::make(PaymentOrchestrationWorkflow::class);
$result = $workflow->execute($request);

if ($result->status === 'completed') {
    echo "Payment successful: {$result->transactionId}";
} else {
    echo "Payment failed: {$result->errorMessage}";
}
```

### Escrow Payment

```php
$request = new AgentPaymentRequest(
    fromAgentDid: 'did:agent:buyer:123',
    toAgentDid: 'did:agent:seller:456',
    amount: 1000.00,
    currency: 'USD',
    purpose: 'escrow_purchase',
    escrowConditions: [
        'delivery_confirmed' => false,
        'inspection_passed' => false,
    ],
    metadata: [
        'release_conditions' => ['delivery_confirmed', 'inspection_passed'],
        'dispute_resolver' => 'did:agent:arbitrator:789',
    ]
);

$workflow = WorkflowStub::make(PaymentOrchestrationWorkflow::class);
$result = $workflow->execute($request);

// Later, update escrow conditions
$escrow = EscrowAggregate::retrieve($result->escrowId);
$escrow->updateCondition('delivery_confirmed', true);
$escrow->updateCondition('inspection_passed', true);
$escrow->release(); // Funds released to seller
$escrow->persist();
```

### Split Payment

```php
$request = new AgentPaymentRequest(
    fromAgentDid: 'did:agent:buyer:123',
    toAgentDid: 'did:agent:seller:456',
    amount: 100.00,
    currency: 'USD',
    purpose: 'marketplace_purchase',
    splits: [
        ['agentDid' => 'did:agent:platform:001', 'amount' => 5.00, 'type' => 'commission'],
        ['agentDid' => 'did:agent:affiliate:002', 'amount' => 2.00, 'type' => 'referral'],
    ]
);

$workflow = WorkflowStub::make(PaymentOrchestrationWorkflow::class);
$result = $workflow->execute($request);

// Platform receives 5.00, affiliate receives 2.00, seller receives 93.00 (minus fees)
```

### Payment with Retry

```php
$workflow = WorkflowStub::make(PaymentOrchestrationWorkflow::class);
$result = $workflow->executeWithRetry(
    $request,
    maxAttempts: 3,
    backoffMultiplier: 2
);
```

## Fee Structure

### Standard Fees
- **Rate**: 2.5% of transaction amount
- **Minimum**: $0.50 per transaction
- **Maximum**: $100.00 per transaction

### Fee Exemptions
- System accounts (treasury, reserve)
- Internal transfers (prefix: `internal:`)
- Micropayments below threshold ($1.00)
- Custom exemption flag in metadata

### Custom Fee Rates
```php
$request = new AgentPaymentRequest(
    // ... other parameters ...
    metadata: [
        'custom_fee_rate' => 0.01, // 1% custom rate
        'fee_exempt' => false,
    ]
);
```

## Notification System

The system includes a comprehensive notification service for agent communication:

### Notification Types
- Payment received
- Payment sent
- Escrow created
- Escrow funded
- Escrow released
- Dispute raised
- Dispute resolved

### Offline Notifications
When agents are offline, notifications are stored and retried:
- Exponential backoff retry strategy
- Maximum retry attempts: 3
- Retry delays: 100ms, 200ms, 400ms

### Database Table
```sql
CREATE TABLE agent_offline_notifications (
    id BIGINT PRIMARY KEY,
    agent_did VARCHAR(255),
    type VARCHAR(50),
    data JSON,
    retry_count INT DEFAULT 0,
    next_retry_at TIMESTAMP NULL,
    delivered_at TIMESTAMP NULL,
    delivery_status VARCHAR(20) DEFAULT 'pending',
    last_error TEXT NULL,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);
```

## Security Features

### DID Verification
- Cryptographic signature verification
- DID document resolution
- Public key validation
- Rate limiting per DID

### Transaction Limits
- Daily transaction limit per agent
- Single transaction maximum
- Rate limiting (requests per minute)
- Concurrent payment restrictions

### Audit Trail
- Complete event sourcing
- Immutable payment history
- Compliance reporting
- Fraud detection integration

## Testing

### Unit Tests
```bash
# Run activity tests
./vendor/bin/pest tests/Unit/AgentProtocol/Activities/

# Test specific activity
./vendor/bin/pest tests/Unit/AgentProtocol/Activities/ValidatePaymentActivityTest.php
```

### Feature Tests
```bash
# Run workflow tests
./vendor/bin/pest tests/Feature/AgentProtocol/

# Test payment orchestration
./vendor/bin/pest tests/Feature/AgentProtocol/PaymentOrchestrationWorkflowTest.php

# Test escrow workflow
./vendor/bin/pest tests/Feature/AgentProtocol/EscrowWorkflowTest.php
```

### Integration Tests
```bash
# Run full integration tests
./vendor/bin/pest tests/Integration/AgentProtocol/
```

## API Endpoints

### Payment Endpoints
- `POST /api/v1/agent/payments` - Initiate payment
- `GET /api/v1/agent/payments/{transactionId}` - Get payment status
- `POST /api/v1/agent/payments/{transactionId}/reverse` - Reverse payment

### Escrow Endpoints
- `POST /api/v1/agent/escrow` - Create escrow
- `PUT /api/v1/agent/escrow/{escrowId}/fund` - Fund escrow
- `PUT /api/v1/agent/escrow/{escrowId}/conditions` - Update conditions
- `POST /api/v1/agent/escrow/{escrowId}/release` - Release funds
- `POST /api/v1/agent/escrow/{escrowId}/dispute` - Raise dispute
- `POST /api/v1/agent/escrow/{escrowId}/resolve` - Resolve dispute

### Wallet Endpoints
- `GET /api/v1/agent/wallets/{agentDid}/balance` - Get wallet balance
- `GET /api/v1/agent/wallets/{agentDid}/transactions` - Transaction history
- `POST /api/v1/agent/wallets/{agentDid}/freeze` - Freeze wallet
- `POST /api/v1/agent/wallets/{agentDid}/unfreeze` - Unfreeze wallet

## Monitoring & Metrics

### Key Metrics
- Transaction volume (TPV)
- Success rate
- Average transaction time
- Fee revenue
- Dispute rate
- Escrow completion rate

### Logging
```php
Log::channel('agent_protocol')->info('Payment processed', [
    'transaction_id' => $transactionId,
    'amount' => $amount,
    'from_agent' => $fromAgentDid,
    'to_agent' => $toAgentDid,
    'status' => $status,
]);
```

### Health Checks
- Workflow engine status
- Database connectivity
- Redis cache availability
- Notification service health

## Migration Guide

### From Phase 1 to Phase 2

1. **Update Configuration**
   ```bash
   php artisan config:publish agent_protocol
   php artisan migrate
   ```

2. **Update Agent DIDs**
   - Migrate from old format to new DID format
   - Update wallet associations

3. **Enable New Features**
   ```env
   AGENT_PROTOCOL_ENABLED=true
   DID_VERIFICATION_ENABLED=true
   ESCROW_AUTO_RELEASE=true
   ```

4. **Test Integration**
   ```bash
   php artisan agent:test-payment
   php artisan agent:test-escrow
   ```

## Troubleshooting

### Common Issues

#### Payment Fails with "Invalid DID"
- Verify DID format: `did:agent:namespace:identifier`
- Check DID verification is enabled
- Ensure agent is registered

#### Escrow Not Releasing
- Verify all conditions are met
- Check timeout hasn't expired
- Ensure no active disputes

#### High Fee Calculations
- Review custom fee rate settings
- Check for fee exemption eligibility
- Verify configuration values

#### Notification Delivery Failures
- Check webhook URLs are accessible
- Verify network connectivity
- Review retry logs in database

### Debug Commands

```bash
# Check payment status
php artisan agent:payment:status {transactionId}

# Verify agent wallet
php artisan agent:wallet:balance {agentDid}

# Process offline notifications
php artisan agent:notifications:process

# Rebuild payment history
php artisan event-sourcing:replay "App\\Domain\\AgentProtocol\\Projectors\\PaymentHistoryProjector"
```

## Performance Optimization

### Caching Strategy
- Agent wallet balances (5 min TTL)
- DID documents (1 hour TTL)
- Fee calculations (10 min TTL)
- Exchange rates (5 min TTL)

### Database Indexes
```sql
-- Optimize payment queries
CREATE INDEX idx_payments_agent ON agent_payments(from_agent_did, to_agent_did);
CREATE INDEX idx_payments_status ON agent_payments(status, created_at);

-- Optimize escrow queries
CREATE INDEX idx_escrow_status ON agent_escrows(status, timeout_at);
CREATE INDEX idx_escrow_agents ON agent_escrows(buyer_did, seller_did);

-- Optimize notification queries
CREATE INDEX idx_notifications_delivery ON agent_offline_notifications(delivery_status, next_retry_at);
```

### Queue Configuration
```php
// config/queue.php
'connections' => [
    'agent_payments' => [
        'driver' => 'redis',
        'queue' => 'agent_payments',
        'retry_after' => 90,
        'block_for' => 5,
    ],
    'agent_notifications' => [
        'driver' => 'redis',
        'queue' => 'agent_notifications',
        'retry_after' => 30,
    ],
],
```

## Future Enhancements

### Phase 3 Features (Planned)
- Multi-signature escrow
- Recurring payments
- Subscription management
- Cross-chain payments
- Machine learning fraud detection
- Advanced dispute resolution AI
- Payment scheduling
- Bulk payment processing
- Currency hedging
- Loyalty programs

### Integration Roadmap
- Ethereum blockchain integration
- Bitcoin Lightning Network
- Stripe Connect for fiat rails
- Open Banking APIs
- SWIFT integration
- Central Bank Digital Currencies (CBDCs)

## Support & Resources

### Documentation
- [Agent Protocol Specification](https://github.com/finaegis/agent-protocol)
- [DID Specification](https://www.w3.org/TR/did-core/)
- [Laravel Workflow Documentation](https://github.com/laravel-workflow/laravel-workflow)

### Community
- Discord: [FinAegis Community](https://discord.gg/finaegis)
- GitHub Issues: [Report Issues](https://github.com/finaegis/core-banking/issues)

### Contact
- Technical Support: support@finaegis.com
- Security Issues: security@finaegis.com
- Partnership: partners@finaegis.com