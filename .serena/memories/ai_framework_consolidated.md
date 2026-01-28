# AI Agent Framework - Consolidated Reference

## Overview
The FinAegis AI Agent Framework provides a comprehensive Model Context Protocol (MCP) implementation for banking operations, enabling AI agents to interact with core banking services through a standardized interface.

## Architecture Components

### Core Structure
```
app/Domain/AI/
├── MCP/                 # Model Context Protocol implementation
│   ├── MCPServer.php    # Central MCP server
│   ├── ToolRegistry.php # Tool registration
│   └── ResourceManager.php
├── Aggregates/          # Event sourcing
│   └── AIInteractionAggregate.php
├── Events/              # Domain events
├── Activities/          # 12 atomic business logic units
│   ├── Trading/        # RSI, MACD, patterns
│   └── Risk/           # Credit, fraud detection
├── Workflows/           # Multi-step processes
│   ├── CustomerServiceWorkflow.php
│   ├── ComplianceWorkflow.php
│   └── TradingAgentWorkflow.php
├── ChildWorkflows/      # Modular sub-workflows
│   ├── Trading/
│   └── Risk/
├── Sagas/               # Transactional operations
├── Tools/               # 20+ MCP tools by domain
└── ValueObjects/

app/Infrastructure/AI/
├── LLM/                 # OpenAI, Claude providers
├── Storage/             # Redis conversation store
└── VectorDB/            # Pinecone integration
```

### MCP Tools (20+)
- **Account**: CreateAccount, CheckBalance, GetTransactionHistory, FreezeAccount
- **Payment**: InitiatePayment, PaymentStatus, CancelPayment
- **Exchange**: GetExchangeRates, PlaceOrder, ExecuteTrade
- **Lending**: LoanApplication, CheckLoanStatus, AssessCreditRisk
- **Compliance**: KYCVerification, AMLScreening, TransactionMonitoring
- **Stablecoin**: TransferTokens, CheckTokenBalance, MintTokens, BurnTokens

## Key Patterns

### Event Sourcing
All AI interactions recorded via AIInteractionAggregate:
- ConversationStartedEvent, AIDecisionMadeEvent, ToolExecutedEvent
- Complete audit trail for compliance

### Clean Architecture (65% code reduction)
- Activities: Pure business logic (RSI, MACD, credit scoring)
- Child Workflows: Domain orchestration
- Sagas: Transactional with compensation

## Configuration

```env
AI_LLM_PROVIDER=openai
OPENAI_API_KEY=your-key
ANTHROPIC_API_KEY=your-key
PINECONE_API_KEY=your-key
AI_CONFIDENCE_THRESHOLD=0.8
AI_HUMAN_APPROVAL_THRESHOLD=10000
```

## Testing
- `tests/Feature/AI/` - Feature tests
- `tests/Unit/AI/` - Unit tests
- PHPStan Level 5, PSR-12 compliant

## Documentation
`docs/13-AI-FRAMEWORK/` contains complete documentation.
