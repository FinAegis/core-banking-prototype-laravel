# MCP (Model Context Protocol) Integration Guide

## Overview

The Model Context Protocol (MCP) v1.0 enables standardized communication between AI systems and application backends. FinAegis implements a production-ready MCP server that exposes banking operations as tools and resources that AI agents can interact with, complete with event sourcing, caching, and comprehensive testing.

## Architecture

### Core Components

```
app/Domain/AI/
├── MCP/
│   ├── MCPServer.php              # Main MCP server implementation
│   ├── ToolRegistry.php           # Dynamic tool registration
│   ├── ResourceManager.php        # Resource exposure system
│   └── Tools/                     # Banking tool implementations
│       ├── Account/               # 4 account management tools
│       ├── Payment/               # 2 payment operation tools
│       ├── Exchange/              # 3 trading/liquidity tools
│       └── Compliance/            # 2 KYC/AML tools
├── Aggregates/
│   └── AIInteractionAggregate.php # Event sourcing for AI interactions
├── Events/                        # Domain events for AI operations
├── ValueObjects/                  # Request/Response objects
└── Workflows/
    └── CustomerServiceWorkflow.php # AI agent orchestration
```

### MCP Server Implementation

```php
namespace App\Domain\AI\MCP;

class MCPServer implements MCPServerInterface
{
    public function __construct(
        private ToolRegistry $toolRegistry,
        private ResourceManager $resourceManager,
        private ?CommandBus $commandBus = null,
        private ?DomainEventBus $eventBus = null
    ) {}
    
    public function handle(MCPRequest $request): MCPResponse
    {
        return match ($request->getMethod()) {
            'initialize' => $this->handleInitialize($request),
            'tools/list' => $this->handleToolsList($request),
            'tools/call' => $this->handleToolCall($request),
            'resources/list' => $this->handleResourcesList($request),
            'resources/read' => $this->handleResourceRead($request),
            'prompts/list' => $this->handlePromptsList($request),
            default => MCPResponse::error('Method not found'),
        };
    }
}
```

## Available Tools (11 Banking Tools)

### Account Domain (4 tools)

#### account.balance
Get current account balance with multi-asset support.

```json
{
  "name": "account.balance",
  "description": "Get current account balance",
  "inputSchema": {
    "type": "object",
    "properties": {
      "account_uuid": {
        "type": "string",
        "pattern": "^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$"
      }
    },
    "required": ["account_uuid"]
  }
}
```

#### account.create
Create a new bank account with workflow integration.

```json
{
  "name": "account.create",
  "description": "Create a new bank account",
  "inputSchema": {
    "type": "object",
    "properties": {
      "name": { "type": "string" },
      "type": { "enum": ["checking", "savings", "investment"] },
      "currency": { 
        "type": "string",
        "pattern": "^[A-Z]{3}$"
      },
      "initial_balance": { "type": "number", "minimum": 0 }
    },
    "required": ["name", "type"]
  }
}
```

#### account.deposit
Process deposits with balance validation.

```json
{
  "name": "account.deposit",
  "description": "Deposit funds to account",
  "inputSchema": {
    "type": "object",
    "properties": {
      "account_uuid": { "type": "string" },
      "amount": { "type": "number", "minimum": 0.01 },
      "currency": { "type": "string" },
      "description": { "type": "string" }
    },
    "required": ["account_uuid", "amount"]
  }
}
```

#### account.withdraw
Process withdrawals with overdraft protection.

```json
{
  "name": "account.withdraw",
  "description": "Withdraw funds from account",
  "inputSchema": {
    "type": "object",
    "properties": {
      "account_uuid": { "type": "string" },
      "amount": { "type": "number", "minimum": 0.01 },
      "description": { "type": "string" }
    },
    "required": ["account_uuid", "amount"]
  }
}
```

### Payment Domain (2 tools)

#### payment.transfer
Execute account-to-account transfers with saga support.

```json
{
  "name": "payment.transfer",
  "description": "Transfer funds between accounts",
  "inputSchema": {
    "type": "object",
    "properties": {
      "from_account": { "type": "string" },
      "to_account": { "type": "string" },
      "amount": { "type": "number", "minimum": 0.01 },
      "currency": { "type": "string" },
      "reference": { "type": "string" }
    },
    "required": ["from_account", "to_account", "amount"]
  }
}
```

#### payment.status
Track payment and transfer status.

```json
{
  "name": "payment.status",
  "description": "Get payment or transfer status",
  "inputSchema": {
    "type": "object",
    "properties": {
      "transaction_id": { 
        "type": "string",
        "description": "UUID, reference, or external reference"
      }
    },
    "required": ["transaction_id"]
  }
}
```

### Exchange Domain (3 tools)

#### exchange.quote
Get real-time exchange rate quotes.

```json
{
  "name": "exchange.quote",
  "description": "Get exchange rate quote",
  "inputSchema": {
    "type": "object",
    "properties": {
      "from_currency": { "type": "string" },
      "to_currency": { "type": "string" },
      "amount": { "type": "number", "minimum": 0 }
    },
    "required": ["from_currency", "to_currency", "amount"]
  }
}
```

#### exchange.trade
Execute trades with order matching.

```json
{
  "name": "exchange.trade",
  "description": "Execute currency exchange",
  "inputSchema": {
    "type": "object",
    "properties": {
      "account_uuid": { "type": "string" },
      "from_currency": { "type": "string" },
      "to_currency": { "type": "string" },
      "amount": { "type": "number" },
      "order_type": { "enum": ["market", "limit"] },
      "limit_price": { "type": "number" }
    },
    "required": ["account_uuid", "from_currency", "to_currency", "amount"]
  }
}
```

#### exchange.liquidity_pool
Manage AMM liquidity pools.

```json
{
  "name": "exchange.liquidity_pool",
  "description": "Manage liquidity pool operations",
  "inputSchema": {
    "type": "object",
    "properties": {
      "action": { 
        "enum": ["create", "add_liquidity", "remove_liquidity", "get_info", "get_metrics", "list_pools", "my_positions"]
      },
      "pool_id": { "type": "string" },
      "base_currency": { "type": "string" },
      "quote_currency": { "type": "string" },
      "base_amount": { "type": "number" },
      "quote_amount": { "type": "number" }
    },
    "required": ["action"]
  }
}
```

### Compliance Domain (2 tools)

#### compliance.kyc
Perform KYC verification.

```json
{
  "name": "compliance.kyc",
  "description": "Perform KYC verification",
  "inputSchema": {
    "type": "object",
    "properties": {
      "user_uuid": { "type": "string" },
      "verification_level": { 
        "enum": ["basic", "enhanced", "full"]
      }
    },
    "required": ["user_uuid"]
  }
}
```

#### compliance.aml_screening
Perform AML screening.

```json
{
  "name": "compliance.aml_screening",
  "description": "Perform AML screening",
  "inputSchema": {
    "type": "object",
    "properties": {
      "entity_type": { "enum": ["user", "transaction"] },
      "entity_id": { "type": "string" },
      "screening_type": { 
        "enum": ["sanctions", "pep", "adverse_media", "all"]
      }
    },
    "required": ["entity_type", "entity_id"]
  }
}
```

## Event Sourcing Integration

### AIInteractionAggregate

All AI interactions are tracked through event sourcing:

```php
namespace App\Domain\AI\Aggregates;

class AIInteractionAggregate extends AggregateRoot
{
    public function startConversation(string $userId, array $context): self
    {
        $this->recordThat(new ConversationStartedEvent(
            conversationId: $this->uuid(),
            userId: $userId,
            context: $context
        ));
        return $this;
    }
    
    public function executeTool(
        string $toolName,
        array $input,
        array $result,
        int $durationMs
    ): self {
        $this->recordThat(new ToolExecutedEvent(
            conversationId: $this->uuid(),
            toolName: $toolName,
            input: $input,
            result: $result,
            durationMs: $durationMs
        ));
        return $this;
    }
}
```

### Domain Events

- `ConversationStartedEvent` - New AI conversation initiated
- `AgentCreatedEvent` - AI agent instantiated
- `IntentClassifiedEvent` - User intent identified
- `AIDecisionMadeEvent` - AI made a decision
- `ToolExecutedEvent` - Tool was executed
- `ConversationEndedEvent` - Conversation completed

## Security & Authorization

### Authentication

All MCP requests require authentication:

```php
class MCPServer
{
    private function handleToolCall(MCPRequest $request): MCPResponse
    {
        $userId = $request->getUserId();
        
        if (!$userId && !Auth::check()) {
            return MCPResponse::error('Authentication required');
        }
        
        $user = $userId ? User::find($userId) : Auth::user();
        
        // Tool execution with user context
        $result = $tool->execute($params, $user);
    }
}
```

### Permission Validation

Tools validate permissions before execution:

```php
class CreateAccountTool implements MCPToolInterface
{
    public function authorize(?string $userId): bool
    {
        if (!$userId) {
            return false;
        }
        
        $user = User::find($userId);
        return $user && $user->can('create', Account::class);
    }
}
```

## Caching Strategy

### Tool Result Caching

Read operations are cached for performance:

```php
class AccountBalanceTool implements MCPToolInterface
{
    public function getCacheTTL(): ?int
    {
        return 60; // Cache for 1 minute
    }
    
    public function getCacheKey(array $parameters): string
    {
        return sprintf(
            'mcp.account.balance.%s',
            $parameters['account_uuid']
        );
    }
}
```

## Testing Coverage

### Unit Tests

```php
class AIInteractionAggregateTest extends TestCase
{
    #[Test]
    public function it_starts_conversation_and_records_event(): void
    {
        $aggregate = AIInteractionAggregate::retrieve($conversationId);
        $aggregate->startConversation($userId, ['channel' => 'api']);
        
        $events = $aggregate->getRecordedEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ConversationStartedEvent::class, $events[0]);
    }
    
    #[Test]
    public function it_tracks_tool_executions(): void
    {
        $aggregate->executeTool('account.balance', [], ['balance' => 1000], 150);
        
        $executedTools = $aggregate->getExecutedTools();
        $this->assertCount(1, $executedTools);
        $this->assertEquals('account.balance', $executedTools[0]['tool']);
    }
}
```

### Feature Tests

```php
class CreateAccountToolTest extends TestCase
{
    #[Test]
    public function it_creates_account_successfully_with_valid_input(): void
    {
        $response = $this->server->handle(MCPRequest::create('tools/call', [
            'name' => 'account.create',
            'arguments' => [
                'name' => 'Test Account',
                'type' => 'savings',
                'currency' => 'USD',
            ],
        ]));
        
        $this->assertTrue($response->isSuccess());
        $this->assertDatabaseHas('accounts', ['name' => 'Test Account']);
        $this->assertDatabaseHas('stored_events', ['event_class' => 'ai_tool_executed']);
    }
}
```

### Integration Tests

```php
class MCPServerTest extends TestCase
{
    #[Test]
    public function it_measures_tool_execution_performance(): void
    {
        $response = $this->server->handle($request);
        
        $metadata = $response->getData()['metadata'];
        $this->assertLessThan(100, $metadata['duration_ms']);
    }
    
    #[Test]
    public function it_caches_tool_results_when_cacheable(): void
    {
        $response1 = $this->server->handle($request);
        $this->assertFalse($response1->getData()['metadata']['cache_hit']);
        
        $response2 = $this->server->handle($request);
        $this->assertTrue($response2->getData()['metadata']['cache_hit']);
    }
}
```

## Performance Metrics

- **Response Time**: <100ms for cached operations
- **Tool Execution**: <1 second for complex operations
- **Event Recording**: Asynchronous via queue
- **Cache Hit Rate**: >80% for read operations

## Client Integration Examples

### JavaScript/TypeScript

```typescript
import { MCPClient } from '@modelcontextprotocol/sdk';

const client = new MCPClient({
  url: 'https://api.finaegis.org/mcp',
  apiKey: process.env.MCP_API_KEY
});

// Create account
const account = await client.executeTool('account.create', {
  name: 'Business Account',
  type: 'checking',
  currency: 'USD'
});

// Check balance
const balance = await client.executeTool('account.balance', {
  account_uuid: account.account_uuid
});

// Execute transfer
const transfer = await client.executeTool('payment.transfer', {
  from_account: account.account_uuid,
  to_account: 'recipient-uuid',
  amount: 100.00,
  reference: 'INV-2025-001'
});
```

### Python

```python
from mcp import MCPClient

client = MCPClient(
    url="https://api.finaegis.org/mcp",
    api_key=os.environ["MCP_API_KEY"]
)

# Perform KYC check
kyc_result = client.execute_tool(
    "compliance.kyc",
    user_uuid="user-123",
    verification_level="enhanced"
)

# Get payment status
status = client.execute_tool(
    "payment.status",
    transaction_id="TRF-2025-00123"
)
```

## Best Practices

1. **Event Sourcing**: All AI interactions are recorded for audit
2. **Saga Pattern**: Complex operations use compensation flows
3. **Caching**: Read operations cached with appropriate TTL
4. **Authorization**: User context validated on every tool call
5. **Testing**: Comprehensive unit and feature test coverage
6. **Performance**: Sub-100ms response times for cached operations
7. **Error Handling**: Graceful degradation with clear error messages
8. **Documentation**: Clear schemas and descriptions for all tools

## Troubleshooting

### Common Issues

#### Tool Not Found
```json
{
  "error": "Tool not found: account.invalid",
  "code": "TOOL_NOT_FOUND"
}
```
**Solution**: Verify tool name in registry. Available tools: `account.balance`, `account.create`, etc.

#### Authentication Required
```json
{
  "error": "Authentication required",
  "code": "UNAUTHENTICATED"
}
```
**Solution**: Provide valid user ID or authentication token.

#### Invalid Schema
```json
{
  "error": "Schema validation failed",
  "details": {
    "missing": ["account_uuid"],
    "invalid": ["amount: must be positive"]
  }
}
```
**Solution**: Ensure all required fields are provided and valid.

## Resources

- [MCP Specification v1.0](https://modelcontextprotocol.io/docs)
- [FinAegis API Documentation](../04-API/REST_API_REFERENCE.md)
- [Event Sourcing Guide](../02-ARCHITECTURE/EVENT_SOURCING.md)
- [Testing Guide](../06-DEVELOPMENT/TESTING_GUIDE.md)
- [AI Framework Overview](./README.md)