# MCP (Model Context Protocol) Integration Guide

## Introduction

The Model Context Protocol (MCP) is the foundation of FinAegis's AI tool system, providing a standardized way for AI agents to discover, execute, and monitor banking tools. This guide covers integration patterns, tool development, and best practices.

## Architecture Overview

```
┌─────────────────┐     ┌──────────────┐     ┌─────────────┐
│   AI Agent      │────▶│  MCP Server  │────▶│    Tools    │
└─────────────────┘     └──────────────┘     └─────────────┘
        │                       │                     │
        ▼                       ▼                     ▼
┌─────────────────┐     ┌──────────────┐     ┌─────────────┐
│  Conversation   │     │   Registry   │     │   Domain    │
│     Store       │     │              │     │   Services  │
└─────────────────┘     └──────────────┘     └─────────────┘
```

## Core Components

### MCPServer Class

Located at `app/Domain/AI/MCP/MCPServer.php`:

```php
namespace App\Domain\AI\MCP;

use App\Domain\AI\Aggregates\AIInteractionAggregate;
use App\Domain\AI\Events\ToolExecutedEvent;

class MCPServer
{
    public function __construct(
        private ToolRegistry $registry,
        private ResourceManager $resourceManager,
        private CacheManager $cache
    ) {}

    public function executeToool(string $toolName, array $params): mixed
    {
        // Record execution in event store
        $aggregate = AIInteractionAggregate::retrieve($interactionId);
        
        $aggregate->recordToolExecution(
            toolName: $toolName,
            parameters: $params,
            timestamp: now()
        );
        
        // Execute tool
        $tool = $this->registry->get($toolName);
        $result = $tool->execute($params);
        
        // Persist aggregate
        $aggregate->persist();
        
        return $result;
    }
}
```

### Tool Interface

All tools must implement the `MCPToolInterface`:

```php
namespace App\Domain\AI\MCP\Interfaces;

interface MCPToolInterface
{
    /**
     * Get tool metadata
     */
    public function getSchema(): array;
    
    /**
     * Execute the tool
     */
    public function execute(array $params): mixed;
    
    /**
     * Validate parameters
     */
    public function validate(array $params): bool;
    
    /**
     * Get caching configuration
     */
    public function getCacheConfig(): array;
}
```

## Creating Custom Tools

### Step 1: Define Tool Class

```php
namespace App\Domain\AI\Tools\Banking;

use App\Domain\AI\MCP\Interfaces\MCPToolInterface;
use App\Domain\AI\Events\ToolExecutedEvent;

class TransferFundsTool implements MCPToolInterface
{
    public function __construct(
        private TransferService $transferService,
        private EventStore $eventStore
    ) {}

    public function getSchema(): array
    {
        return [
            'name' => 'transfer_funds',
            'description' => 'Transfer funds between accounts',
            'parameters' => [
                'from_account' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Source account number'
                ],
                'to_account' => [
                    'type' => 'string',
                    'required' => true,
                    'description' => 'Destination account number'
                ],
                'amount' => [
                    'type' => 'number',
                    'required' => true,
                    'description' => 'Transfer amount'
                ],
                'currency' => [
                    'type' => 'string',
                    'required' => false,
                    'default' => 'USD',
                    'description' => 'Currency code'
                ]
            ]
        ];
    }

    public function validate(array $params): bool
    {
        // Validate required parameters
        if (!isset($params['from_account'], $params['to_account'], $params['amount'])) {
            return false;
        }

        // Validate amount is positive
        if ($params['amount'] <= 0) {
            return false;
        }

        return true;
    }

    public function execute(array $params): mixed
    {
        // Record event
        event(new ToolExecutedEvent(
            toolName: 'transfer_funds',
            parameters: $params,
            userId: auth()->id()
        ));

        // Execute transfer
        return $this->transferService->transfer(
            from: $params['from_account'],
            to: $params['to_account'],
            amount: $params['amount'],
            currency: $params['currency'] ?? 'USD'
        );
    }

    public function getCacheConfig(): array
    {
        return [
            'enabled' => false,  // Don't cache transfer operations
            'ttl' => 0
        ];
    }
}
```

### Step 2: Register Tool

Register the tool in a service provider:

```php
namespace App\Providers;

use App\Domain\AI\MCP\ToolRegistry;
use App\Domain\AI\Tools\Banking\TransferFundsTool;

class AIToolServiceProvider extends ServiceProvider
{
    public function boot(ToolRegistry $registry): void
    {
        // Register banking tools
        $registry->register(
            name: 'transfer_funds',
            tool: app(TransferFundsTool::class),
            metadata: [
                'category' => 'banking',
                'requires_auth' => true,
                'requires_2fa' => true,
                'risk_level' => 'high',
                'human_approval_threshold' => 10000,  // Require approval for >$10k
            ]
        );
    }
}
```

### Step 3: Configure Permissions

Add tool permissions in `config/ai.php`:

```php
return [
    'tools' => [
        'permissions' => [
            'transfer_funds' => [
                'roles' => ['customer', 'admin'],
                'scopes' => ['transfers:create'],
                'daily_limit' => 50000,
                'per_transaction_limit' => 10000,
            ]
        ]
    ]
];
```

## Tool Categories

### Account Tools
Tools for account management and information:
- Balance inquiries
- Transaction history
- Account creation
- Account freezing/unfreezing

### Payment Tools
Tools for payment operations:
- Payment initiation
- Payment status checking
- Payment cancellation
- Recurring payment setup

### Trading Tools
Tools for trading and exchange:
- Market data retrieval
- Order placement
- Portfolio analysis
- Trade execution

### Compliance Tools
Tools for regulatory compliance:
- KYC verification
- AML screening
- Transaction monitoring
- Regulatory reporting

## Resource Management

The MCP server can expose resources for AI agents:

```php
namespace App\Domain\AI\MCP;

class ResourceManager
{
    public function exposeResource(string $name, mixed $resource): void
    {
        // Register resource
        $this->resources[$name] = $resource;
        
        // Emit event for tracking
        event(new ResourceExposedEvent($name, get_class($resource)));
    }

    public function getResource(string $name): mixed
    {
        return $this->resources[$name] ?? null;
    }
}
```

Example resource exposure:

```php
// Expose account service as resource
$resourceManager->exposeResource(
    'account_service',
    app(AccountService::class)
);

// Expose read-only data
$resourceManager->exposeResource(
    'exchange_rates',
    Cache::remember('exchange_rates', 3600, fn() => 
        ExchangeRate::latest()->get()
    )
);
```

## Caching Strategy

Tools can define their caching behavior:

```php
public function getCacheConfig(): array
{
    return [
        'enabled' => true,
        'ttl' => 300,  // 5 minutes
        'key_prefix' => 'tool:balance:',
        'tags' => ['banking', 'account'],
        'invalidate_on' => [
            TransferCompletedEvent::class,
            DepositReceivedEvent::class,
        ]
    ];
}
```

## Error Handling

Tools should handle errors gracefully:

```php
public function execute(array $params): mixed
{
    try {
        $result = $this->performOperation($params);
        
        // Log success
        Log::info('Tool executed successfully', [
            'tool' => 'transfer_funds',
            'params' => $params,
            'result' => $result
        ]);
        
        return $result;
        
    } catch (InsufficientFundsException $e) {
        // Return structured error
        return [
            'success' => false,
            'error' => 'insufficient_funds',
            'message' => 'Account has insufficient funds',
            'required' => $e->getRequired(),
            'available' => $e->getAvailable()
        ];
        
    } catch (\Exception $e) {
        // Log error
        Log::error('Tool execution failed', [
            'tool' => 'transfer_funds',
            'error' => $e->getMessage()
        ]);
        
        // Return generic error
        return [
            'success' => false,
            'error' => 'execution_failed',
            'message' => 'Unable to complete operation'
        ];
    }
}
```

## Testing Tools

Tools should be thoroughly tested:

```php
namespace Tests\Feature\AI\Tools;

use Tests\TestCase;
use App\Domain\AI\Tools\Banking\TransferFundsTool;

class TransferFundsToolTest extends TestCase
{
    /** @test */
    public function it_transfers_funds_between_accounts(): void
    {
        // Arrange
        $tool = app(TransferFundsTool::class);
        $params = [
            'from_account' => 'ACC001',
            'to_account' => 'ACC002',
            'amount' => 100.00,
            'currency' => 'USD'
        ];
        
        // Act
        $result = $tool->execute($params);
        
        // Assert
        $this->assertTrue($result['success']);
        $this->assertEquals('ACC001', $result['from_account']);
        $this->assertEquals('ACC002', $result['to_account']);
        $this->assertEquals(100.00, $result['amount']);
        
        // Verify event was recorded
        Event::assertDispatched(ToolExecutedEvent::class);
    }
    
    /** @test */
    public function it_validates_parameters(): void
    {
        $tool = app(TransferFundsTool::class);
        
        // Missing required parameter
        $this->assertFalse($tool->validate([
            'from_account' => 'ACC001'
        ]));
        
        // Invalid amount
        $this->assertFalse($tool->validate([
            'from_account' => 'ACC001',
            'to_account' => 'ACC002',
            'amount' => -100
        ]));
    }
}
```

## Performance Monitoring

Monitor tool performance:

```php
namespace App\Domain\AI\MCP\Monitoring;

class ToolMonitor
{
    public function recordExecution(
        string $toolName,
        float $executionTime,
        bool $success
    ): void {
        // Record metrics
        Metrics::record('tool.execution', [
            'tool' => $toolName,
            'duration' => $executionTime,
            'success' => $success
        ]);
        
        // Alert if slow
        if ($executionTime > 5.0) {
            Alert::send('Slow tool execution', [
                'tool' => $toolName,
                'duration' => $executionTime
            ]);
        }
    }
}
```

## Security Considerations

1. **Authentication**: Always verify user authentication
2. **Authorization**: Check user permissions for tool access
3. **Rate Limiting**: Implement per-tool rate limits
4. **Input Validation**: Thoroughly validate all parameters
5. **Audit Logging**: Record all tool executions
6. **Encryption**: Encrypt sensitive parameters

## Best Practices

1. **Single Responsibility**: Each tool should do one thing well
2. **Idempotency**: Tools should be idempotent where possible
3. **Error Recovery**: Implement compensation for failed operations
4. **Documentation**: Provide clear descriptions and examples
5. **Testing**: Achieve >80% test coverage
6. **Monitoring**: Track execution metrics and errors
7. **Versioning**: Version tools for backward compatibility

## Next Steps

- [Creating Custom Agents](02-Agent-Creation.md)
- [Workflow Development](03-Workflows.md)
- [Event Sourcing Patterns](04-Event-Sourcing.md)