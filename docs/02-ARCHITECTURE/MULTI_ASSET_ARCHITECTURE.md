# Multi-Asset Platform Architecture

**Version:** 1.0  
**Last Updated:** 2025-06-15

## Overview

This document outlines the technical architecture for evolving FinAegis from a single-currency banking platform to a comprehensive multi-asset management system with decentralized custody and democratic governance.

## Core Architectural Changes

### 1. Asset-Centric Domain Model

#### Current State (Currency-Centric)
```php
// Current: Account has single balance in base currency
class Account {
    public int $balance; // Amount in cents (USD)
}
```

#### Future State (Asset-Centric)
```php
// Future: Account has multiple balances per asset
class Account {
    // Balance is moved to AccountBalance entity
}

class AccountBalance {
    public string $account_uuid;
    public string $asset_code; // e.g., "USD", "EUR", "BTC", "XAU"
    public int $balance;       // Amount in smallest unit
    public int $precision;     // Decimal places for display
}

class Asset {
    public string $code;       // Unique identifier (ISO 4217 for fiat)
    public string $name;       // Human-readable name
    public string $type;       // 'fiat', 'crypto', 'commodity', 'custom'
    public int $precision;     // Number of decimal places
    public bool $is_active;    // Whether asset is enabled
}
```

### 2. Event Sourcing Adaptations

All financial events must be updated to include asset information:

#### Updated Event Structure
```php
// Before
class MoneyAdded extends ShouldBeStored {
    public function __construct(
        public readonly Money $money,
        public readonly Hash $hash
    ) {}
}

// After
class MoneyAdded extends ShouldBeStored {
    public function __construct(
        public readonly string $asset_code,
        public readonly int $amount,
        public readonly Hash $hash,
        public readonly ?array $metadata = []
    ) {}
}
```

#### New Events
```php
class AssetBalanceCreated extends ShouldBeStored {
    public function __construct(
        public readonly string $account_uuid,
        public readonly string $asset_code,
        public readonly int $initial_balance = 0
    ) {}
}

class AssetExchanged extends ShouldBeStored {
    public function __construct(
        public readonly string $from_asset,
        public readonly int $from_amount,
        public readonly string $to_asset,
        public readonly int $to_amount,
        public readonly float $exchange_rate,
        public readonly Hash $hash
    ) {}
}
```

### 3. Custodian Abstraction Layer

#### Interface Definition
```php
interface ICustodianConnector {
    /**
     * Get current balance for a specific asset
     */
    public function getBalance(string $asset_code): Money;
    
    /**
     * Initiate a transfer between accounts
     */
    public function initiateTransfer(
        string $from_account,
        string $to_account,
        string $asset_code,
        int $amount,
        array $metadata = []
    ): TransactionReceipt;
    
    /**
     * Check the status of a pending transaction
     */
    public function getTransactionStatus(string $transaction_id): TransactionStatus;
    
    /**
     * Retrieve transaction history for reconciliation
     */
    public function getTransactions(
        DateTimeInterface $from,
        DateTimeInterface $to,
        ?string $asset_code = null
    ): array;
    
    /**
     * Get supported assets by this custodian
     */
    public function getSupportedAssets(): array;
    
    /**
     * Verify webhook signatures (if supported)
     */
    public function verifyWebhookSignature(
        string $payload,
        string $signature
    ): bool;
}
```

#### Custodian Registry
```php
class CustodianRegistry {
    private array $connectors = [];
    
    public function register(string $name, ICustodianConnector $connector): void {
        $this->connectors[$name] = $connector;
    }
    
    public function get(string $name): ICustodianConnector {
        if (!isset($this->connectors[$name])) {
            throw new CustodianNotFoundException($name);
        }
        return $this->connectors[$name];
    }
    
    public function getForAsset(string $asset_code): array {
        return array_filter($this->connectors, function($connector) use ($asset_code) {
            return in_array($asset_code, $connector->getSupportedAssets());
        });
    }
}
```

### 4. Exchange Rate Service Architecture

```php
interface IExchangeRateProvider {
    public function getRate(string $from, string $to): float;
    public function getSupportedPairs(): array;
    public function getLastUpdateTime(): DateTimeInterface;
}

class ExchangeRateService {
    private array $providers = [];
    private CacheInterface $cache;
    
    public function getRate(string $from, string $to): ExchangeRate {
        $cacheKey = "rate:{$from}:{$to}";
        
        return $this->cache->remember($cacheKey, 300, function() use ($from, $to) {
            // Try providers in priority order
            foreach ($this->providers as $provider) {
                try {
                    $rate = $provider->getRate($from, $to);
                    return new ExchangeRate($from, $to, $rate, now());
                } catch (RateUnavailableException $e) {
                    continue;
                }
            }
            
            throw new NoExchangeRateAvailable($from, $to);
        });
    }
    
    public function convert(Money $money, string $to_asset): Money {
        if ($money->asset_code === $to_asset) {
            return $money;
        }
        
        $rate = $this->getRate($money->asset_code, $to_asset);
        $converted_amount = (int) round($money->amount * $rate->rate);
        
        return new Money($converted_amount, $to_asset);
    }
}
```

### 5. Multi-Asset Transfer Workflow

```php
class MultiAssetTransferWorkflow extends Workflow {
    public function execute(
        AccountUuid $from,
        AccountUuid $to,
        string $asset_code,
        int $amount,
        ?string $custodian = null
    ): \Generator {
        try {
            // 1. Validate accounts have the asset
            yield ActivityStub::make(ValidateAssetBalanceActivity::class, $from, $asset_code);
            
            // 2. Check sufficient balance
            yield ActivityStub::make(CheckBalanceActivity::class, $from, $asset_code, $amount);
            
            // 3. Perform internal ledger transfer
            yield ChildWorkflowStub::make(
                InternalAssetTransferWorkflow::class,
                $from,
                $to,
                $asset_code,
                $amount
            );
            
            // 4. If custodian specified, initiate external transfer
            if ($custodian) {
                $this->addCompensation(fn() => ChildWorkflowStub::make(
                    InternalAssetTransferWorkflow::class,
                    $to,
                    $from,
                    $asset_code,
                    $amount
                ));
                
                yield ActivityStub::make(
                    CustodianTransferActivity::class,
                    $custodian,
                    $from,
                    $to,
                    $asset_code,
                    $amount
                );
            }
            
            // 5. Record transfer event
            yield ActivityStub::make(
                RecordTransferEventActivity::class,
                $from,
                $to,
                $asset_code,
                $amount,
                $custodian
            );
            
        } catch (\Throwable $e) {
            yield from $this->compensate();
            throw $e;
        }
    }
}
```

### 6. Governance System Architecture

```php
// Poll Management
class Poll extends Model {
    protected $fillable = [
        'title',
        'description',
        'type', // 'single_choice', 'multiple_choice', 'weighted'
        'options', // JSON array of options
        'start_date',
        'end_date',
        'status', // 'draft', 'active', 'closed', 'executed'
        'required_participation', // Minimum % of eligible voters
        'execution_workflow', // Class name of workflow to execute
    ];
    
    public function votes(): HasMany {
        return $this->hasMany(Vote::class);
    }
    
    public function results(): ?PollResult {
        if ($this->status !== 'closed') {
            return null;
        }
        
        return app(PollResultService::class)->calculate($this);
    }
}

// Voting Power Calculation
interface IVotingPowerStrategy {
    public function calculatePower(User $user, Poll $poll): int;
}

class OneUserOneVoteStrategy implements IVotingPowerStrategy {
    public function calculatePower(User $user, Poll $poll): int {
        return 1;
    }
}

class AssetWeightedVoteStrategy implements IVotingPowerStrategy {
    public function __construct(
        private string $asset_code,
        private bool $sqrt_weighted = false
    ) {}
    
    public function calculatePower(User $user, Poll $poll): int {
        $balance = $user->accounts()
            ->join('account_balances', 'accounts.uuid', '=', 'account_balances.account_uuid')
            ->where('asset_code', $this->asset_code)
            ->sum('balance');
        
        if ($this->sqrt_weighted) {
            return (int) sqrt($balance);
        }
        
        return $balance;
    }
}
```

## Database Schema Changes

### New Tables

```sql
-- Assets table
CREATE TABLE assets (
    code VARCHAR(10) PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    type ENUM('fiat', 'crypto', 'commodity', 'custom') NOT NULL,
    precision TINYINT NOT NULL DEFAULT 2,
    is_active BOOLEAN DEFAULT TRUE,
    metadata JSON,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX idx_type (type),
    INDEX idx_active (is_active)
);

-- Account balances (replaces single balance field)
CREATE TABLE account_balances (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    account_uuid CHAR(36) NOT NULL,
    asset_code VARCHAR(10) NOT NULL,
    balance BIGINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    UNIQUE KEY unique_account_asset (account_uuid, asset_code),
    FOREIGN KEY (account_uuid) REFERENCES accounts(uuid),
    FOREIGN KEY (asset_code) REFERENCES assets(code),
    INDEX idx_asset (asset_code)
);

-- Exchange rates
CREATE TABLE exchange_rates (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    from_asset VARCHAR(10) NOT NULL,
    to_asset VARCHAR(10) NOT NULL,
    rate DECIMAL(20, 8) NOT NULL,
    provider VARCHAR(50),
    fetched_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP,
    INDEX idx_pair (from_asset, to_asset),
    INDEX idx_fetched (fetched_at)
);

-- Custodians
CREATE TABLE custodians (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(50) NOT NULL,
    config JSON NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP,
    updated_at TIMESTAMP
);

-- Custodian balances (for reconciliation)
CREATE TABLE custodian_balances (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    custodian_id BIGINT NOT NULL,
    asset_code VARCHAR(10) NOT NULL,
    balance BIGINT NOT NULL,
    last_reconciled_at TIMESTAMP,
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    FOREIGN KEY (custodian_id) REFERENCES custodians(id),
    UNIQUE KEY unique_custodian_asset (custodian_id, asset_code)
);

-- Polls
CREATE TABLE polls (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    uuid CHAR(36) UNIQUE NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    type VARCHAR(50) NOT NULL,
    options JSON NOT NULL,
    start_date TIMESTAMP NOT NULL,
    end_date TIMESTAMP NOT NULL,
    status VARCHAR(20) NOT NULL,
    required_participation INT,
    voting_power_strategy VARCHAR(100),
    execution_workflow VARCHAR(255),
    created_by CHAR(36),
    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_dates (start_date, end_date)
);

-- Votes
CREATE TABLE votes (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    poll_id BIGINT NOT NULL,
    user_uuid CHAR(36) NOT NULL,
    selected_options JSON NOT NULL,
    voting_power INT NOT NULL DEFAULT 1,
    voted_at TIMESTAMP NOT NULL,
    signature VARCHAR(255),
    FOREIGN KEY (poll_id) REFERENCES polls(id),
    UNIQUE KEY unique_user_poll (user_uuid, poll_id),
    INDEX idx_poll (poll_id)
);
```

### Migration Strategy

1. **Phase 1**: Add new tables without breaking existing functionality
2. **Phase 2**: Migrate balance data to account_balances table
3. **Phase 3**: Update all queries to use new structure
4. **Phase 4**: Remove deprecated columns

## API Changes

### New Endpoints

```yaml
# Asset Management
GET    /api/v2/assets
GET    /api/v2/assets/{code}
GET    /api/v2/assets/{code}/exchange-rates

# Multi-Asset Accounts
GET    /api/v2/accounts/{uuid}/balances
GET    /api/v2/accounts/{uuid}/balances/{asset_code}
POST   /api/v2/accounts/{uuid}/balances/{asset_code}/convert

# Multi-Asset Transfers
POST   /api/v2/transfers
{
  "from_account": "uuid",
  "to_account": "uuid",
  "asset_code": "USD",
  "amount": 10000,
  "custodian": "paysera" // optional
}

# Governance
GET    /api/v2/governance/polls
POST   /api/v2/governance/polls
GET    /api/v2/governance/polls/{uuid}
POST   /api/v2/governance/polls/{uuid}/vote
GET    /api/v2/governance/polls/{uuid}/results

# Custodians
GET    /api/v2/custodians
GET    /api/v2/custodians/{code}/balances
POST   /api/v2/custodians/{code}/reconcile
```

### Backward Compatibility

All v1 endpoints will continue to work by:
1. Assuming USD as the default asset
2. Returning single balance from account_balances where asset_code = 'USD'
3. Converting multi-asset responses to single-currency format

## Performance Considerations

### Caching Strategy
```php
// Asset data (rarely changes)
Cache::remember("asset:{$code}", 86400, fn() => Asset::find($code));

// Exchange rates (5-minute cache)
Cache::remember("rate:{$from}:{$to}", 300, fn() => $this->fetchRate($from, $to));

// Account balances (use cache tags for invalidation)
Cache::tags(["account:{$uuid}"])->remember(
    "balance:{$uuid}:{$asset}",
    3600,
    fn() => AccountBalance::where('account_uuid', $uuid)
        ->where('asset_code', $asset)
        ->first()
);
```

### Query Optimization
```sql
-- Efficient multi-asset balance query
SELECT 
    ab.asset_code,
    ab.balance,
    a.name as asset_name,
    a.precision
FROM account_balances ab
JOIN assets a ON ab.asset_code = a.code
WHERE ab.account_uuid = ?
AND ab.balance > 0
ORDER BY ab.asset_code;

-- Optimized exchange rate lookup
SELECT rate 
FROM exchange_rates 
WHERE from_asset = ? AND to_asset = ?
AND fetched_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)
ORDER BY fetched_at DESC 
LIMIT 1;
```

## Security Considerations

### Asset Validation
- Validate asset codes against whitelist
- Implement rate limiting on exchange rate API calls
- Require additional authentication for high-value transfers

### Custodian Security
- Encrypt custodian API credentials at rest
- Implement IP whitelisting for custodian webhooks
- Use mutual TLS for custodian API connections
- Implement circuit breakers for custodian failures

### Governance Security
- Implement anti-sybil measures for voting
- Use time-locks for executing poll results
- Require multi-signature for critical governance actions
- Audit trail for all governance activities

## Testing Strategy

### Unit Tests
```php
// Asset domain tests
test('account can hold multiple asset balances', function () {
    $account = Account::factory()->create();
    
    $account->addBalance('USD', 10000);
    $account->addBalance('EUR', 5000);
    $account->addBalance('BTC', 100000000); // 1 BTC in satoshis
    
    expect($account->balances)->toHaveCount(3);
    expect($account->getBalance('USD'))->toBe(10000);
    expect($account->getBalance('BTC'))->toBe(100000000);
});

// Exchange rate tests
test('can convert between assets', function () {
    $service = app(ExchangeRateService::class);
    $service->setRate('USD', 'EUR', 0.85);
    
    $usd = new Money(10000, 'USD'); // $100.00
    $eur = $service->convert($usd, 'EUR');
    
    expect($eur->amount)->toBe(8500); // €85.00
    expect($eur->asset_code)->toBe('EUR');
});
```

### Integration Tests
```php
// Multi-asset transfer test
test('can transfer assets between accounts with custodian', function () {
    $custodian = MockBankConnector::make();
    app(CustodianRegistry::class)->register('mock_bank', $custodian);
    
    $from = Account::factory()->create();
    $to = Account::factory()->create();
    
    $from->addBalance('USD', 50000); // $500.00
    
    $workflow = WorkflowStub::make(MultiAssetTransferWorkflow::class);
    $workflow->start($from->uuid, $to->uuid, 'USD', 10000, 'mock_bank');
    
    expect($from->getBalance('USD'))->toBe(40000);
    expect($to->getBalance('USD'))->toBe(10000);
    expect($custodian->getLastTransfer())->toMatchArray([
        'from' => $from->uuid,
        'to' => $to->uuid,
        'amount' => 10000,
        'asset_code' => 'USD'
    ]);
});
```

## Monitoring and Observability

### Key Metrics
- Asset balance distribution
- Exchange rate fetch success rate
- Custodian API response times
- Governance participation rates
- Multi-asset transfer success rates

### Alerts
- Exchange rate staleness (> 10 minutes)
- Custodian balance mismatch
- Failed governance workflow execution
- Unusual voting patterns

## Migration Timeline

### Phase 1: Foundation (Weeks 1-4)
- Create asset and account_balances tables
- Implement Asset domain and services
- Build exchange rate service
- Create backward-compatible APIs

### Phase 2: Integration (Weeks 5-8)
- Implement custodian abstraction
- Build mock custodian connector
- Update transfer workflows
- Create reconciliation services

### Phase 3: Governance (Weeks 9-12)
- Implement poll and vote entities
- Build voting power strategies
- Create governance workflows
- Develop admin UI

### Phase 4: Production (Weeks 13-16)
- Implement real custodian connector
- Perform data migration
- Extensive testing
- Gradual rollout

---

This architecture provides a solid foundation for building complex financial products while maintaining the platform's core strengths in security, auditability, and performance.