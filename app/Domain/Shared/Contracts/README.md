# Shared Domain Contracts

This directory contains interfaces that enable **domain decoupling** in FinAegis. These contracts allow domains to depend on abstractions rather than concrete implementations, supporting the platform's modularity goals.

## Purpose

Instead of domains directly importing each other:

```php
// BEFORE: Tight coupling
use App\Domain\Account\Services\AccountService;

class BasketService
{
    public function __construct(
        private readonly AccountService $accountService  // Direct dependency
    ) {}
}
```

Domains now depend on interfaces:

```php
// AFTER: Loose coupling
use App\Domain\Shared\Contracts\AccountOperationsInterface;

class BasketService
{
    public function __construct(
        private readonly AccountOperationsInterface $accountOperations  // Interface
    ) {}
}
```

## Available Interfaces

### AccountOperationsInterface

For domains that need to interact with accounts (balance, credit, debit, transfer).

**Used by**: Exchange, Basket, Lending, Treasury, Stablecoin, AI, AgentProtocol, CGO

```php
interface AccountOperationsInterface
{
    public function getBalance(string $accountId, string $assetCode): string;
    public function credit(string $accountId, string $assetCode, string $amount, string $reference, array $metadata = []): string;
    public function debit(string $accountId, string $assetCode, string $amount, string $reference, array $metadata = []): string;
    public function transfer(string $fromAccountId, string $toAccountId, string $assetCode, string $amount, string $reference, array $metadata = []): string;
    public function lockBalance(string $accountId, string $assetCode, string $amount, string $reason): string;
    public function unlockBalance(string $lockId): bool;
}
```

### ComplianceCheckInterface

For domains that need to perform compliance checks (KYC, AML, transaction limits).

**Used by**: Exchange, Lending, AgentProtocol, Stablecoin, Wallet

```php
interface ComplianceCheckInterface
{
    public function getKYCStatus(string $userId): array;
    public function hasMinimumKYCLevel(string $userId, string $requiredLevel): bool;
    public function validateTransaction(array $transaction): array;
    public function checkTransactionLimits(string $userId, string $amount, string $currency): array;
    public function screenAML(string $userId): array;
}
```

### ExchangeRateInterface

For domains that need exchange rate data (currency conversion, NAV calculation).

**Used by**: Basket, Stablecoin, Treasury

```php
interface ExchangeRateInterface
{
    public function getRate(string $fromCurrency, string $toCurrency): string;
    public function convert(string $amount, string $fromCurrency, string $toCurrency): string;
    public function getHistoricalRate(string $fromCurrency, string $toCurrency, \DateTimeInterface $date): ?string;
    public function getQuote(string $fromCurrency, string $toCurrency): array;
}
```

### GovernanceVotingInterface

For domains that integrate with governance (proposals, voting).

**Used by**: Basket, Stablecoin, CGO

```php
interface GovernanceVotingInterface
{
    public function createProposal(array $proposal): array;
    public function castVote(string $proposalId, string $voterId, bool $approve, ?string $reason = null): array;
    public function getProposalStatus(string $proposalId): ?array;
    public function isProposalApproved(string $proposalId): bool;
    public function executeProposal(string $proposalId): array;
}
```

### WalletOperationsInterface (v1.3.0)

For domains that need wallet operations (deposits, withdrawals, fund locking).

**Used by**: Exchange, Stablecoin, Basket, Custodian, AgentProtocol

```php
interface WalletOperationsInterface
{
    public function deposit(string $walletId, string $assetCode, string $amount, string $reference = '', array $metadata = []): string;
    public function withdraw(string $walletId, string $assetCode, string $amount, string $reference = '', array $metadata = []): string;
    public function getBalance(string $walletId, string $assetCode): string;
    public function lockFunds(string $walletId, string $assetCode, string $amount, string $reason, array $metadata = []): string;
    public function unlockFunds(string $lockId): bool;
    public function transfer(string $fromWalletId, string $toWalletId, string $assetCode, string $amount, string $reference = '', array $metadata = []): string;
}
```

### AssetTransferInterface (v1.3.0)

For domains that need asset transfer operations (cross-account, cross-asset conversions).

**Used by**: Exchange, Stablecoin, AI, AgentProtocol, Treasury

```php
interface AssetTransferInterface
{
    public function transfer(string $fromAccountId, string $toAccountId, string $assetCode, string $amount, string $reference = '', array $metadata = []): string;
    public function convertAndTransfer(string $fromAccountId, string $toAccountId, string $fromAssetCode, string $toAssetCode, string $fromAmount, ?string $exchangeRate = null, string $reference = '', array $metadata = []): array;
    public function getAssetDetails(string $assetCode): ?array;
    public function getExchangeRate(string $fromAssetCode, string $toAssetCode): ?string;
    public function isConversionSupported(string $fromAssetCode, string $toAssetCode): bool;
}
```

### PaymentProcessingInterface (v1.3.0)

For domains that need payment processing (deposits, withdrawals, refunds).

**Used by**: AgentProtocol, Exchange, Stablecoin, Banking

```php
interface PaymentProcessingInterface
{
    public function processDeposit(string $accountId, int $amount, string $currency, string $paymentMethod, string $reference = '', array $metadata = []): array;
    public function processWithdrawal(string $accountId, int $amount, string $currency, string $paymentMethod, array $destination, string $reference = '', array $metadata = []): array;
    public function getPaymentStatus(string $paymentId): ?array;
    public function refundPayment(string $paymentId, ?int $amount = null, string $reason = '', array $metadata = []): array;
    public function validatePaymentRequest(string $type, int $amount, string $currency, string $paymentMethod, array $context = []): array;
}
```

### AccountQueryInterface (v1.3.0)

For read-only account queries (balances, transaction history). Separates reads from writes for better caching and CQRS support.

**Used by**: Exchange, Lending, Treasury, Basket, AI, AgentProtocol

```php
interface AccountQueryInterface
{
    public function getAccountDetails(string $accountId): ?array;
    public function getBalance(string $accountId, string $assetCode): string;
    public function getAllBalances(string $accountId): array;
    public function hasSufficientBalance(string $accountId, string $assetCode, string $amount): bool;
    public function accountExists(string $accountId): bool;
    public function getTransactionHistory(string $accountId, array $filters = [], int $limit = 50, int $offset = 0): array;
}
```

## Binding Interfaces

Interfaces are bound to implementations in service providers:

```php
// app/Providers/DomainServiceProvider.php

public function register(): void
{
    // Core domain bindings
    $this->app->bind(
        AccountOperationsInterface::class,
        \App\Domain\Account\Services\AccountService::class
    );

    $this->app->bind(
        ComplianceCheckInterface::class,
        \App\Domain\Compliance\Services\ComplianceService::class
    );

    $this->app->bind(
        ExchangeRateInterface::class,
        \App\Domain\Exchange\Services\ExchangeRateService::class
    );

    $this->app->bind(
        GovernanceVotingInterface::class,
        \App\Domain\Governance\Services\GovernanceService::class
    );
}
```

## Benefits

### 1. Testability

Mock interfaces easily in tests:

```php
it('composes basket correctly', function () {
    $mockAccount = Mockery::mock(AccountOperationsInterface::class);
    $mockAccount->shouldReceive('getBalance')->andReturn('1000.00');
    $mockAccount->shouldReceive('debit')->andReturn('tx-123');

    $service = new BasketService($mockAccount);
    $result = $service->composeBasket(...);

    expect($result)->toBeSuccessful();
});
```

### 2. Modularity

Domains can be replaced or disabled:

```php
// For demo mode
$this->app->bind(
    AccountOperationsInterface::class,
    DemoAccountService::class
);

// For testing
$this->app->bind(
    ComplianceCheckInterface::class,
    AlwaysApproveComplianceService::class
);
```

### 3. Feature Flags

Conditional implementations based on configuration:

```php
$this->app->bind(ExchangeRateInterface::class, function ($app) {
    return match (config('exchange.provider')) {
        'binance' => new BinanceExchangeRateService(),
        'kraken' => new KrakenExchangeRateService(),
        'demo' => new DemoExchangeRateService(),
        default => new InternalExchangeRateService(),
    };
});
```

## Design Guidelines

### When to Create an Interface

Create a shared interface when:
- Multiple domains need the same capability
- The capability is stable and well-defined
- You want to enable domain substitution

### Naming Conventions

- `*Interface` suffix for interfaces
- `*Service` suffix for implementations
- Use verb-based method names (`getBalance`, `validateTransaction`)

### Documentation

- Document all interface methods with PHPDoc
- Include return type arrays with structure documentation
- Reference the implementing class with `@see`

## Related Documentation

- [DOMAIN_DEPENDENCIES.md](../../../../docs/02-ARCHITECTURE/DOMAIN_DEPENDENCIES.md)
- [ADR-0008: CQRS Pattern](../../../../docs/adr/0008-cqrs-pattern.md)
- [ARCHITECTURAL_ROADMAP.md](../../../../docs/ARCHITECTURAL_ROADMAP.md)
