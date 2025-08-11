# AI Agent Framework Documentation

## Overview

The FinAegis AI Agent Framework is a comprehensive, event-sourced artificial intelligence system designed specifically for banking and financial operations. Built on Domain-Driven Design (DDD) principles, it provides intelligent automation, decision support, and customer service capabilities while maintaining complete auditability and regulatory compliance.

## Architecture

### Core Components

1. **MCP Server (Model Context Protocol)**
   - Tool registry and discovery
   - Resource management
   - Protocol handling
   - Performance monitoring

2. **AI Agents**
   - Customer Service Agent
   - Compliance Agent
   - Risk Assessment Agent
   - Trading Agent

3. **Domain Layer** (`app/Domain/AI/`)
   - Event sourcing with AIInteractionAggregate
   - Domain events for complete audit trail
   - Workflow orchestration
   - Saga pattern implementation

4. **Infrastructure Layer** (`app/Infrastructure/AI/`)
   - OpenAI/Claude API integration
   - Redis conversation store
   - Pinecone vector database
   - External service connectors

## Key Features

### Event Sourcing
Every AI decision and interaction is captured as an immutable event:
- `AIDecisionMadeEvent`
- `ToolExecutedEvent`
- `ConversationStartedEvent`
- `HumanOverrideEvent`
- `ConfidenceThresholdExceededEvent`

### Workflow Orchestration
Complex multi-step operations using Laravel Workflow:
- `CustomerServiceWorkflow`
- `ComplianceWorkflow`
- `RiskAssessmentSaga`
- `TradingAgentWorkflow`

### Human-in-the-Loop
Configurable confidence thresholds and approval workflows:
- High-value transaction approval
- Risk assessment verification
- Compliance decision review
- Trading strategy confirmation

### Multi-Agent Coordination
Agents can collaborate and delegate tasks:
- Consensus building
- Task delegation
- Conflict resolution
- Load balancing

## Banking Tools

The framework includes 20+ specialized banking tools:

### Account Management
- `CreateAccountTool`
- `CheckBalanceTool`
- `GetTransactionHistoryTool`
- `FreezeAccountTool`

### Payments
- `InitiatePaymentTool`
- `CheckPaymentStatusTool`
- `CancelPaymentTool`
- `SchedulePaymentTool`

### Trading & Exchange
- `GetExchangeRatesTool`
- `PlaceOrderTool`
- `GetMarketDataTool`
- `ExecuteTradeTool`

### Lending
- `LoanApplicationTool`
- `CheckLoanStatusTool`
- `CalculateInterestTool`
- `AssessCreditRiskTool`

### Compliance
- `KYCVerificationTool`
- `AMLScreeningTool`
- `TransactionMonitoringTool`
- `RegulatoryReportingTool`

## Getting Started

### Installation

1. Ensure Redis and database are configured
2. Set up vector database (optional, for semantic search)
3. Configure AI provider credentials:

```bash
# .env configuration
AI_PROVIDER=openai  # or claude
OPENAI_API_KEY=your-key-here
CLAUDE_API_KEY=your-key-here

# Vector database (optional)
PINECONE_API_KEY=your-key-here
PINECONE_ENVIRONMENT=your-environment

# Redis for conversation store
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### Basic Usage

```php
// Get AI Agent Service
$aiAgent = app(AIAgentService::class);

// Send a chat message
$response = $aiAgent->chat(
    message: "What is my account balance?",
    conversationId: $conversationId,
    userId: $userId,
    context: ['account_type' => 'checking']
);

// Response includes:
// - AI response text
// - Tools used (e.g., CheckBalanceTool)
// - Confidence score
// - Context for follow-up
```

### MCP Tool Registration

```php
// Register a custom tool
$toolRegistry = app(ToolRegistry::class);

$toolRegistry->register(
    name: 'CustomBankingTool',
    tool: new CustomBankingTool(),
    metadata: [
        'category' => 'banking',
        'requires_auth' => true,
        'cache_ttl' => 300,
    ]
);
```

## API Endpoints

### AI Chat
- `POST /api/ai/chat` - Send message to AI agent
- `GET /api/ai/conversations` - List user conversations
- `GET /api/ai/conversations/{id}` - Get conversation details
- `DELETE /api/ai/conversations/{id}` - Delete conversation

### MCP Tools
- `GET /api/ai/mcp/tools` - List available tools
- `GET /api/ai/mcp/tools/{tool}` - Get tool details
- `POST /api/ai/mcp/tools/{tool}/execute` - Execute tool
- `POST /api/ai/mcp/tools/register` - Register new tool

## Testing

The framework includes comprehensive test coverage:

```bash
# Run AI Framework tests
./vendor/bin/pest tests/Feature/AI/
./vendor/bin/pest tests/Unit/Domain/AI/

# Test specific components
./vendor/bin/pest tests/Feature/AI/AgentWorkflowTest.php
./vendor/bin/pest tests/Unit/Domain/AI/MCPServerTest.php
```

## Security Considerations

1. **Authentication**: All AI operations require user authentication
2. **Authorization**: Tool execution respects user permissions
3. **Audit Trail**: Complete event log of all AI decisions
4. **Data Privacy**: Conversation isolation and encryption
5. **Rate Limiting**: API throttling to prevent abuse
6. **Confidence Thresholds**: Configurable limits for automated actions

## Performance

- **Caching**: Intelligent caching of tool results
- **Async Processing**: Long-running operations via queues
- **Vector Search**: Semantic search for relevant context
- **Connection Pooling**: Efficient external API usage
- **Event Streaming**: Real-time updates via WebSockets

## Monitoring

Built-in monitoring capabilities:
- Tool execution metrics
- Response time tracking
- Error rate monitoring
- Token usage tracking
- Conversation analytics

## Next Steps

- [MCP Integration Guide](01-MCP-Integration.md)
- [Creating Custom Agents](02-Agent-Creation.md)
- [Workflow Development](03-Workflows.md)
- [Event Sourcing Patterns](04-Event-Sourcing.md)
- [API Reference](05-API-Reference.md)