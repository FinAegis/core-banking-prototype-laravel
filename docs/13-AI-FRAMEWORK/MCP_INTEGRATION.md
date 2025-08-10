# MCP Integration Guide

## Model Context Protocol (MCP) Implementation

FinAegis implements a complete MCP server that exposes banking operations as AI-accessible tools and resources.

## Server Configuration

### Initialization

```php
use App\Domain\AI\MCP\MCPServer;
use App\Domain\AI\MCP\ToolRegistry;

// Initialize MCP server
$mcpServer = new MCPServer([
    'name' => 'finaegis-banking',
    'version' => '1.0.0',
    'capabilities' => [
        'tools' => true,
        'resources' => true,
        'prompts' => true,
        'sampling' => false,
    ],
]);

// Register tools
$toolRegistry = new ToolRegistry();
$toolRegistry->register('account.balance', AccountBalanceTool::class);
$toolRegistry->register('payment.transfer', PaymentTransferTool::class);
$toolRegistry->register('exchange.trade', ExchangeTradeTool::class);

$mcpServer->setToolRegistry($toolRegistry);
```

## Available Tools

### Account Management

#### account.create
Create a new account for a user.

```json
{
  "name": "account.create",
  "description": "Create a new bank account",
  "inputSchema": {
    "type": "object",
    "properties": {
      "user_id": {"type": "string"},
      "account_type": {"type": "string", "enum": ["checking", "savings", "investment"]},
      "currency": {"type": "string"},
      "initial_deposit": {"type": "number"}
    },
    "required": ["user_id", "account_type", "currency"]
  }
}
```

#### account.balance
Get the balance of an account.

```json
{
  "name": "account.balance",
  "description": "Get account balance and recent transactions",
  "inputSchema": {
    "type": "object",
    "properties": {
      "account_id": {"type": "string"},
      "include_transactions": {"type": "boolean"},
      "transaction_limit": {"type": "integer"}
    },
    "required": ["account_id"]
  }
}
```

### Payment Operations

#### payment.transfer
Transfer funds between accounts.

```json
{
  "name": "payment.transfer",
  "description": "Transfer funds between accounts",
  "inputSchema": {
    "type": "object",
    "properties": {
      "from_account": {"type": "string"},
      "to_account": {"type": "string"},
      "amount": {"type": "number"},
      "currency": {"type": "string"},
      "description": {"type": "string"}
    },
    "required": ["from_account", "to_account", "amount", "currency"]
  }
}
```

### Exchange Operations

#### exchange.trade
Execute a currency exchange trade.

```json
{
  "name": "exchange.trade",
  "description": "Execute currency exchange",
  "inputSchema": {
    "type": "object",
    "properties": {
      "account_id": {"type": "string"},
      "from_currency": {"type": "string"},
      "to_currency": {"type": "string"},
      "amount": {"type": "number"},
      "type": {"type": "string", "enum": ["market", "limit"]}
    },
    "required": ["account_id", "from_currency", "to_currency", "amount"]
  }
}
```

### Compliance Tools

#### compliance.kyc
Perform KYC verification.

```json
{
  "name": "compliance.kyc",
  "description": "Perform KYC verification",
  "inputSchema": {
    "type": "object",
    "properties": {
      "user_id": {"type": "string"},
      "document_type": {"type": "string"},
      "document_data": {"type": "object"}
    },
    "required": ["user_id", "document_type", "document_data"]
  }
}
```

#### compliance.aml
Check for AML compliance.

```json
{
  "name": "compliance.aml",
  "description": "Check AML compliance for transaction",
  "inputSchema": {
    "type": "object",
    "properties": {
      "transaction_id": {"type": "string"},
      "enhanced_due_diligence": {"type": "boolean"}
    },
    "required": ["transaction_id"]
  }
}
```

## Available Resources

### Ledger Resources

```json
{
  "uri": "ledger://accounts",
  "name": "Account Ledger",
  "description": "Access to account information and balances",
  "mimeType": "application/json"
}
```

### Exchange Resources

```json
{
  "uri": "exchange://orderbook",
  "name": "Exchange Order Book",
  "description": "Real-time order book data",
  "mimeType": "application/json"
}
```

### Compliance Resources

```json
{
  "uri": "compliance://reports",
  "name": "Compliance Reports",
  "description": "Access to compliance and regulatory reports",
  "mimeType": "application/json"
}
```

## Client Integration

### JavaScript/TypeScript

```typescript
import { Client } from "@modelcontextprotocol/sdk/client/index.js";
import { StdioClientTransport } from "@modelcontextprotocol/sdk/client/stdio.js";

// Connect to FinAegis MCP server
const transport = new StdioClientTransport({
  command: "php",
  args: ["artisan", "mcp:serve"],
});

const client = new Client({
  name: "ai-client",
  version: "1.0.0",
});

await client.connect(transport);

// Call a tool
const result = await client.callTool({
  name: "account.balance",
  arguments: {
    account_id: "acc_123456",
    include_transactions: true
  }
});

console.log(result);
```

### Python

```python
from mcp import Client
import asyncio

async def main():
    # Connect to FinAegis MCP server
    client = Client()
    await client.connect_stdio(
        command=["php", "artisan", "mcp:serve"]
    )
    
    # Get account balance
    result = await client.call_tool(
        "account.balance",
        arguments={
            "account_id": "acc_123456",
            "include_transactions": True
        }
    )
    
    print(result)

asyncio.run(main())
```

## Tool Implementation

### Creating a Custom Tool

```php
namespace App\Domain\AI\MCP\Tools;

use App\Domain\AI\MCP\Contracts\MCPTool;
use App\Domain\Account\Services\AccountService;

class AccountBalanceTool implements MCPTool
{
    private AccountService $accountService;
    
    public function __construct(AccountService $accountService)
    {
        $this->accountService = $accountService;
    }
    
    public function getName(): string
    {
        return 'account.balance';
    }
    
    public function getDescription(): string
    {
        return 'Get account balance and recent transactions';
    }
    
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'account_id' => [
                    'type' => 'string',
                    'description' => 'The account UUID'
                ],
                'include_transactions' => [
                    'type' => 'boolean',
                    'description' => 'Include recent transactions',
                    'default' => false
                ],
                'transaction_limit' => [
                    'type' => 'integer',
                    'description' => 'Number of transactions to include',
                    'default' => 10
                ]
            ],
            'required' => ['account_id']
        ];
    }
    
    public function execute(array $arguments): array
    {
        // Validate permissions
        $this->validateAccess($arguments['account_id']);
        
        // Get account balance
        $account = $this->accountService->findByUuid($arguments['account_id']);
        $balance = $account->getBalance();
        
        $result = [
            'account_id' => $account->uuid,
            'balance' => $balance->amount,
            'currency' => $balance->currency,
            'updated_at' => $balance->updated_at
        ];
        
        // Include transactions if requested
        if ($arguments['include_transactions'] ?? false) {
            $limit = $arguments['transaction_limit'] ?? 10;
            $transactions = $account->transactions()
                ->latest()
                ->limit($limit)
                ->get();
                
            $result['transactions'] = $transactions->map(function ($tx) {
                return [
                    'id' => $tx->uuid,
                    'type' => $tx->type,
                    'amount' => $tx->amount,
                    'description' => $tx->description,
                    'created_at' => $tx->created_at
                ];
            });
        }
        
        // Record AI access event
        event(new AIToolAccessedEvent(
            tool: $this->getName(),
            arguments: $arguments,
            result: $result
        ));
        
        return $result;
    }
    
    private function validateAccess(string $accountId): void
    {
        // Implement access control logic
        if (!$this->accountService->canAccess($accountId)) {
            throw new UnauthorizedException('Access denied to account');
        }
    }
}
```

### Registering Tools

```php
// app/Providers/MCPServiceProvider.php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Domain\AI\MCP\ToolRegistry;

class MCPServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ToolRegistry::class, function ($app) {
            $registry = new ToolRegistry();
            
            // Account tools
            $registry->register('account.create', AccountCreateTool::class);
            $registry->register('account.balance', AccountBalanceTool::class);
            $registry->register('account.transactions', AccountTransactionsTool::class);
            
            // Payment tools
            $registry->register('payment.transfer', PaymentTransferTool::class);
            $registry->register('payment.schedule', PaymentScheduleTool::class);
            
            // Exchange tools
            $registry->register('exchange.trade', ExchangeTradeTool::class);
            $registry->register('exchange.rates', ExchangeRatesTool::class);
            
            // Compliance tools
            $registry->register('compliance.kyc', ComplianceKYCTool::class);
            $registry->register('compliance.aml', ComplianceAMLTool::class);
            
            return $registry;
        });
    }
}
```

## Security Considerations

### Authentication

```php
// Implement MCP authentication
class MCPAuthMiddleware
{
    public function handle(MCPRequest $request, Closure $next)
    {
        $token = $request->getHeader('Authorization');
        
        if (!$this->validateToken($token)) {
            throw new UnauthorizedException('Invalid MCP token');
        }
        
        // Set authenticated context
        $request->setContext([
            'user_id' => $this->getUserFromToken($token),
            'permissions' => $this->getPermissions($token)
        ]);
        
        return $next($request);
    }
}
```

### Rate Limiting

```php
// Implement rate limiting for MCP calls
class MCPRateLimiter
{
    public function handle(MCPRequest $request, Closure $next)
    {
        $key = 'mcp:' . $request->getContext('user_id');
        
        if (RateLimiter::tooManyAttempts($key, 100)) {
            $seconds = RateLimiter::availableIn($key);
            throw new TooManyRequestsException($seconds);
        }
        
        RateLimiter::hit($key);
        
        return $next($request);
    }
}
```

### Audit Logging

```php
// Log all MCP tool calls
class MCPAuditLogger
{
    public function handle(MCPRequest $request, Closure $next)
    {
        $startTime = microtime(true);
        
        try {
            $response = $next($request);
            
            // Log successful call
            Log::channel('mcp')->info('MCP tool called', [
                'tool' => $request->getTool(),
                'arguments' => $request->getArguments(),
                'user_id' => $request->getContext('user_id'),
                'duration' => microtime(true) - $startTime,
                'status' => 'success'
            ]);
            
            return $response;
        } catch (\Exception $e) {
            // Log failed call
            Log::channel('mcp')->error('MCP tool failed', [
                'tool' => $request->getTool(),
                'arguments' => $request->getArguments(),
                'user_id' => $request->getContext('user_id'),
                'duration' => microtime(true) - $startTime,
                'error' => $e->getMessage(),
                'status' => 'failed'
            ]);
            
            throw $e;
        }
    }
}
```

## Testing MCP Tools

### Unit Testing

```php
namespace Tests\Unit\MCP\Tools;

use Tests\TestCase;
use App\Domain\AI\MCP\Tools\AccountBalanceTool;
use App\Domain\Account\Models\Account;

class AccountBalanceToolTest extends TestCase
{
    public function test_it_returns_account_balance()
    {
        // Arrange
        $account = Account::factory()->create([
            'balance' => 1000.00,
            'currency' => 'USD'
        ]);
        
        $tool = app(AccountBalanceTool::class);
        
        // Act
        $result = $tool->execute([
            'account_id' => $account->uuid,
            'include_transactions' => false
        ]);
        
        // Assert
        $this->assertEquals(1000.00, $result['balance']);
        $this->assertEquals('USD', $result['currency']);
        $this->assertEquals($account->uuid, $result['account_id']);
    }
    
    public function test_it_includes_transactions_when_requested()
    {
        // Arrange
        $account = Account::factory()
            ->has(Transaction::factory()->count(5))
            ->create();
        
        $tool = app(AccountBalanceTool::class);
        
        // Act
        $result = $tool->execute([
            'account_id' => $account->uuid,
            'include_transactions' => true,
            'transaction_limit' => 3
        ]);
        
        // Assert
        $this->assertArrayHasKey('transactions', $result);
        $this->assertCount(3, $result['transactions']);
    }
}
```

### Integration Testing

```php
namespace Tests\Feature\MCP;

use Tests\TestCase;
use App\Domain\AI\MCP\MCPServer;

class MCPServerTest extends TestCase
{
    public function test_mcp_server_handles_tool_calls()
    {
        // Arrange
        $server = app(MCPServer::class);
        $request = [
            'jsonrpc' => '2.0',
            'method' => 'tools/call',
            'params' => [
                'name' => 'account.balance',
                'arguments' => [
                    'account_id' => 'acc_123456'
                ]
            ],
            'id' => 1
        ];
        
        // Act
        $response = $server->handle($request);
        
        // Assert
        $this->assertEquals('2.0', $response['jsonrpc']);
        $this->assertEquals(1, $response['id']);
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('balance', $response['result']);
    }
}
```

## Monitoring

### Prometheus Metrics

```php
// Track MCP metrics
class MCPMetricsCollector
{
    private PrometheusExporter $exporter;
    
    public function recordToolCall(string $tool, float $duration, bool $success)
    {
        $this->exporter->histogram(
            'mcp_tool_duration_seconds',
            $duration,
            ['tool' => $tool, 'status' => $success ? 'success' : 'failure']
        );
        
        $this->exporter->counter(
            'mcp_tool_calls_total',
            1,
            ['tool' => $tool, 'status' => $success ? 'success' : 'failure']
        );
    }
}
```

### Dashboard

Create a monitoring dashboard for MCP operations:

```php
// routes/web.php
Route::get('/admin/mcp/dashboard', function () {
    $metrics = app(MCPMetricsCollector::class)->getMetrics();
    
    return view('admin.mcp.dashboard', [
        'total_calls' => $metrics['total_calls'],
        'success_rate' => $metrics['success_rate'],
        'avg_duration' => $metrics['avg_duration'],
        'top_tools' => $metrics['top_tools'],
        'recent_errors' => $metrics['recent_errors']
    ]);
})->middleware(['auth', 'admin']);
```

## Troubleshooting

### Common Issues

1. **Tool Not Found**
   - Verify tool is registered in ToolRegistry
   - Check tool name matches exactly
   - Ensure service provider is loaded

2. **Permission Denied**
   - Check user has required permissions
   - Verify authentication token is valid
   - Review access control policies

3. **Rate Limit Exceeded**
   - Implement exponential backoff
   - Request rate limit increase
   - Use batch operations where possible

4. **Timeout Errors**
   - Optimize database queries
   - Implement caching
   - Use async processing for long operations

## Resources

- [MCP Specification](https://spec.modelcontextprotocol.io)
- [MCP SDK Documentation](https://github.com/modelcontextprotocol/sdk)
- [FinAegis API Reference](../04-API/README.md)