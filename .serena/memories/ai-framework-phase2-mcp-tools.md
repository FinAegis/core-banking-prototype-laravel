# AI Framework Phase 2 - MCP Tools Implementation

## Completed MCP Tools (January 2025)

### Account Domain Tools
1. **AccountBalanceTool** - Query account balances (read-only)
2. **DepositTool** - Handle deposits with AccountService integration
3. **WithdrawTool** - Handle withdrawals with balance validation

### Payment Domain Tools
1. **TransferTool** - Account-to-account transfers with TransferService

## Integration Patterns

### Service Integration
All tools properly integrate with domain services:
- DepositTool uses AccountService::deposit()
- WithdrawTool uses AccountService::withdraw()  
- TransferTool uses TransferService::transfer()

### Event Sourcing Support
Tools trigger workflows that handle:
- Domain event creation
- Event sourcing aggregates
- Saga pattern for multi-step operations

## Code Quality Standards
- PHPStan Level 5 compliance required
- PHPCS PSR-12 standard enforced
- PHP CS Fixer for consistent formatting
- No @phpstan-ignore annotations - fix issues properly

## Next Tools to Implement
- Exchange tools: quote, trade, liquidity pools
- Compliance tools: KYC, AML, risk assessment
- Lending tools: applications, credit scoring
- Stablecoin tools: mint, burn, collateral management

## Testing Approach
- Unit tests for each tool
- Integration tests with mocked services
- Event sourcing verification tests