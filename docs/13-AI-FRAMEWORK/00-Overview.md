# AI Agent Framework Documentation

## Overview

The FinAegis AI agent framework is reference code for AI agents in financial software. It is built with Domain-Driven Design and event sourcing. Agents answer questions in plain language, call platform tools and record their decisions as events. The recorded history supports an operator's own review and compliance process.

The framework lives in `app/Domain/AI/`. The public MCP server at `https://mcp.zelta.app/mcp` is a separate domain, `app/Domain/MCP/`, that reuses the framework's tool registry. [MCP_INTEGRATION.md](MCP_INTEGRATION.md) describes the public server.

## Architecture

### Core components

1. **Internal MCP server and tool registry** (`app/Domain/AI/MCP/`)
   - `ToolRegistry` holds the tool classes and their schemas.
   - `MCPServer` handles `tools/list`, `tools/call` and resource requests inside the application.
   - `ResourceManager` serves read-only resources.

2. **AI agents** (`app/Domain/AI/Agents/`)
   - `FinancialAgent`, `TransferAgent`, `TradingAgent`, `ComplianceAgent` and `GeneralAgent`.
   - `AgentRouterService` picks the agents for a question, and `AgentOrchestratorService` combines their answers.

3. **Domain layer** (`app/Domain/AI/`)
   - `AIInteractionAggregate` records each interaction as events.
   - Domain events form the audit trail.
   - Workflows and sagas orchestrate multi-step work.

4. **Infrastructure layer** (`app/Infrastructure/AI/`)
   - `OpenAIProvider` and `ClaudeProvider` call the language model APIs.
   - `ConversationStore` keeps conversations in Redis.
   - `PineconeProvider` connects an optional Pinecone vector database.

## Key features

### Event sourcing

Agent decisions and interactions are stored as events. Examples are:
- `AIDecisionMadeEvent`
- `ToolExecutedEvent`
- `ConversationStartedEvent` and `ConversationEndedEvent`
- `HumanInterventionRequestedEvent`
- `HumanApprovalReceivedEvent`
- `LLMRequestMadeEvent` and `LLMResponseReceivedEvent`

### Workflow orchestration

Multi-step operations run on Laravel Workflow:
- `CustomerServiceWorkflow`
- `ComplianceWorkflow`
- `RiskAssessmentSaga`
- `TradingAgentWorkflow`
- `HumanApprovalWorkflow` and `HumanInTheLoopWorkflow`

### Human-in-the-loop

`HumanInTheLoopWorkflow` has confidence and value thresholds per operation type. A decision below the confidence threshold or above the value threshold waits for a person to approve it.

### Multi-agent coordination

`MultiAgentCoordinationService` lets agents share a task. It picks the agents that can handle the task, splits it into subtasks and combines the answers. When the agents disagree, it resolves the conflict by weighted voting or by the most confident answer.

## Tools

`MCPToolServiceProvider` registers 28 tools in `ToolRegistry`. The main groups are:

| Group | Tool names |
|---|---|
| Account | `account.balance`, `account.create`, `account.deposit`, `account.withdraw` |
| Payments | `payment.transfer`, `payment.status` |
| Transactions | `transactions.query`, `transactions.spending_analysis` |
| Exchange | `exchange.quote`, `exchange.trade`, `exchange.liquidity_pool` |
| Compliance | `compliance.kyc`, `compliance.aml_screening` |
| Agent protocol | `agent_protocol.payment`, `agent_protocol.escrow`, `agent_protocol.reputation`, `agent_protocol.mandate`, `agent_protocol.vdc` |
| Machine payments | `x402.payment`, `mpp.payment`, `mpp.discovery`, `visacli.payment`, `visacli.cards` |
| Other | `sms.send`, `ramp.start`, `ramp.status`, `wallet.addresses`, `wallet.activity` |

`exchange.trade` refuses to run while the trading engine is off, and it is off in production (`TRADING_ENABLED=false`). `exchange.quote` only reads exchange rates and works without it. `exchange.liquidity_pool` can create, list and inspect pools and show the caller's positions. It cannot add or remove liquidity, because that flow was removed in v7.18.0.

## Getting started

### Configuration

1. Make sure Redis and the database are configured.
2. Set up a vector database if you want semantic search. This step is optional.
3. Set the AI provider credentials:

```bash
# .env configuration
AI_DEMO_MODE=false        # default true: agents answer from templates, no LLM call
AI_LLM_PROVIDER=openai    # or claude
OPENAI_API_KEY=your-key-here
CLAUDE_API_KEY=your-key-here

# Vector database (optional)
PINECONE_API_KEY=your-key-here
PINECONE_ENVIRONMENT=your-environment

# Redis for conversation store
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

The agents still answer from templates today, whatever these settings say. `AgentOrchestratorService` looks for `ai.providers.openai.api_key` and `ai.providers.anthropic.api_key`, and `config/ai.php` does not define them. No code registers a provider with `LLMOrchestrationService` either. Connecting a language model to the agents therefore needs code changes.

### Basic usage

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
// - message_id
// - content (the answer text)
// - confidence
// - tools_used (e.g. account.balance)
// - context
```

### Tool registration

```php
// Register a custom tool (it must implement MCPToolInterface)
$toolRegistry = app(ToolRegistry::class);

$toolRegistry->register(new CustomBankingTool());
```

The tool's `getName()`, `getCategory()`, `getInputSchema()` and `getOutputSchema()` methods describe it to the registry. To offer a tool on the public MCP server, it must also be added to the `tools` catalog in `config/mcp.php`.

## API endpoints

### AI chat
- `POST /api/ai/chat` sends a message to the agents.
- `GET /api/ai/conversations` lists the user's conversations.
- `GET /api/ai/conversations/{id}` returns one conversation.
- `DELETE /api/ai/conversations/{id}` deletes a conversation.
- `POST /api/ai/feedback` accepts a rating for an answer. The rating is not stored yet.

### MCP tools (demo)
- `GET /api/ai/mcp/tools` lists tools.
- `GET /api/ai/mcp/tools/{tool}` returns tool details.
- `POST /api/ai/mcp/tools/{tool}/execute` runs a tool.
- `POST /api/ai/mcp/register` registers a tool.

These four endpoints are a demo. They list six sample tools and return fixed sample data. They do not use `ToolRegistry`.

## Testing

```bash
# Run AI framework tests
./vendor/bin/pest tests/Feature/AI/
./vendor/bin/pest tests/Unit/Domain/AI/

# Test specific components
./vendor/bin/pest tests/Feature/AI/FastAgentWorkflowTest.php
./vendor/bin/pest tests/Feature/AI/MCPServerTest.php
```

## Security considerations

1. **Authentication.** The `/api/ai/*` endpoints need a Sanctum token.
2. **Authorization.** Each tool checks whether the user may run it.
3. **Audit trail.** Agent decisions are stored as events.
4. **Data privacy.** Conversations are kept per user.
5. **Rate limiting.** The `/api/ai/*` endpoints are rate limited.
6. **Confidence thresholds.** Low-confidence decisions wait for a person.

## Performance

- The internal MCP server caches tool lists and cacheable tool results.
- Long-running work can run in queued workflows.
- A vector database can add semantic search when it is configured.

## Monitoring

`MCPServer` records how long each tool call takes and whether the result came from the cache. `LLMOrchestrationService` writes each completion it handles, demo answers included, to the `ai_llm_usage` table.

## Next steps

- [MCP Integration Guide](MCP_INTEGRATION.md)
- [Creating Custom Agents](02-Agent-Creation.md)
- [Workflow Development](03-Workflows.md)
- [Event Sourcing Patterns](04-Event-Sourcing.md)
- [API Reference](05-API-Reference.md)
