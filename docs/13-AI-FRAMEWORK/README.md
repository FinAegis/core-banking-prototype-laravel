# FinAegis AI Agent Framework

## Overview

The AI agent framework is a set of agents, workflows and tools in `app/Domain/AI/`. It answers questions in plain language and calls platform tools on the user's behalf. Every agent decision can be recorded as an event, so the history of what an agent did and why stays available for review.

The framework is reference code in an open-source platform. It is not a production banking service. In demo mode (`AI_DEMO_MODE=true`, the default) agents answer from templates and no language model is called. Today the agents answer from templates even with demo mode off. The orchestrator looks for API keys under `ai.providers.*`, which `config/ai.php` does not define, so connecting a language model needs code changes.

This framework is separate from the public MCP server at `https://mcp.zelta.app/mcp`. The public server lives in `app/Domain/MCP/` and exposes a fixed catalog of tools over OAuth. [MCP_INTEGRATION.md](MCP_INTEGRATION.md) describes it.

## Architecture

```
┌─────────────────────────────────────────────────────────┐
│                  AI Agent Layer                          │
├─────────────────────────────────────────────────────────┤
│ Agent Router │ Agents │ Tool Registry │ Conversation Store│
├─────────────────────────────────────────────────────────┤
│              Existing FinAegis Platform                  │
├─────────────────────────────────────────────────────────┤
│ CQRS │ Event Sourcing │ Workflows │ Domain Services     │
└─────────────────────────────────────────────────────────┘
```

### Internal tool registry

`App\Domain\AI\MCP\ToolRegistry` holds every tool class that agents and the public MCP server can call. `MCPToolServiceProvider` registers 28 tools. They include account, payment, transaction, exchange, compliance, agent protocol, x402, MPP, Visa CLI, SMS, ramp and wallet tools. The public MCP server's catalog in `config/mcp.php` offers 14 of them, and the production server exposes 10.

Examples of registered tool names are `account.balance`, `payment.transfer`, `exchange.quote`, `exchange.liquidity_pool`, `compliance.kyc`, `compliance.aml_screening`, `x402.payment` and `mpp.discovery`.

## Available agents

`AgentServiceProvider` registers five agents with `AgentRouterService`. The router picks agents by keywords in the question.

| Agent | Class | Purpose |
|---|---|---|
| Financial | `FinancialAgent` | Balances, transactions and spending questions |
| Transfer | `TransferAgent` | Payment and transfer requests |
| Trading | `TradingAgent` | Exchange quotes and market questions (example template; not investment advice) |
| Compliance | `ComplianceAgent` | KYC and AML lookups that support an operator's compliance process |
| General | `GeneralAgent` | Questions that fit no other agent |

## Workflows

The workflows in `app/Domain/AI/Workflows/` use Laravel Workflow. They are `CustomerServiceWorkflow`, `ComplianceWorkflow`, `RiskAssessmentSaga`, `TradingAgentWorkflow`, `HumanApprovalWorkflow` and `HumanInTheLoopWorkflow`. [AI_AGENT_WORKFLOWS.md](AI_AGENT_WORKFLOWS.md) describes them.

## Integration patterns

### Event-driven AI

An agent decision is recorded as an `AIDecisionMadeEvent` in the event store:

```php
class AIDecisionMadeEvent extends ShouldBeStored
{
    public function __construct(
        public string $conversationId,
        public string $agentType,
        public string $decision,
        public array $reasoning,
        public float $confidence,
        public bool $requiresApproval,
        public ?string $userId = null,
        public ?string $timestamp = null
    ) {}
}
```

### Human-in-the-loop

`HumanInTheLoopWorkflow` and `HumanApprovalWorkflow` stop a decision until a person approves it. `HumanInTheLoopWorkflow` keeps its confidence and value thresholds in class constants. The example below is not a class in the codebase. It shows the kind of rule the workflows apply.

```php
class AIApprovalWorkflow
{
    public function requiresApproval(AgentAction $action): bool
    {
        return $action->value > 10000 
            || $action->confidence < 0.95
            || $action->type === 'regulatory_filing';
    }
}
```

## Quick start

### Configuration

The framework ships with the application. No extra package or migration is needed. Set the provider keys in `.env`:

```bash
AI_DEMO_MODE=false            # true (default) answers from templates without an LLM
AI_LLM_PROVIDER=openai        # or claude
OPENAI_API_KEY=your-key-here
CLAUDE_API_KEY=your-key-here
```

Then run `php artisan config:cache`.

### Basic usage

```php
// Using the internal MCP server directly
use App\Domain\AI\ValueObjects\MCPRequest;

$mcpServer = app(\App\Domain\AI\MCP\MCPServer::class);
$request = MCPRequest::create('tools/call', [
    'name' => 'account.balance',
    'arguments' => ['account_uuid' => $accountId]
]);
$response = $mcpServer->handle($request);

// Using the customer service workflow
use Workflow\WorkflowStub;
use App\Domain\AI\Workflows\CustomerServiceWorkflow;

$workflow = WorkflowStub::make(CustomerServiceWorkflow::class);
$workflow->start(
    conversationId: (string) Str::uuid(),
    query: "What's my account balance?",
    userId: (string) auth()->id()
);

// Using the agent service (routes the question to the right agent)
$service = app(\App\Domain\AI\Services\AIAgentService::class);
$response = $service->chat("What is my balance?", (string) Str::uuid(), auth()->id());
```

## Documentation

- [00-Overview.md](00-Overview.md) gives an overview of the framework.
- [MCP_INTEGRATION.md](MCP_INTEGRATION.md) describes the public MCP server.
- [02-Agent-Creation.md](02-Agent-Creation.md) shows how to create an agent.
- [05-API-Reference.md](05-API-Reference.md) lists the `/api/ai/*` endpoints.
- [ADVANCED_AI_FEATURES.md](ADVANCED_AI_FEATURES.md) covers trading, multi-agent coordination and human approval.

## Security and compliance

### Audit trail

Agent decisions are recorded in the event store. A record holds the decision, the reasoning, the confidence score and whether a person must approve it.

### Compliance support

The framework has data-handling controls that help operators meet their GDPR obligations. Agents explain their decisions, and people can review and override them. The compliance agent and tools support an operator's own compliance process. They do not make a deployment compliant.

## Use cases

The framework shows how AI agents can support these tasks:

- answering routine customer questions about balances and transactions,
- flagging unusual transactions for review,
- running KYC and AML lookups for a compliance team,
- connecting existing AI tools through MCP.

## Contributing

Contributions to the AI agent framework are welcome. See the [Contributing Guide](../../CONTRIBUTING.md) for details.

## License

Apache 2.0 - See [LICENSE](../../LICENSE) for details.

## Resources

- [FinAegis Platform Documentation](../README.md)
- [Model Context Protocol](https://modelcontextprotocol.io)
- [Architecture Overview](../02-ARCHITECTURE/ARCHITECTURE.md)
- [API Documentation](../04-API/README.md)
