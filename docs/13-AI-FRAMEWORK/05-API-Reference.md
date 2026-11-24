# AI Framework API Reference

## Base URL

The endpoints live under `/api/ai` on the application host. The routes are defined in `app/Domain/AI/Routes/api.php`.

Several endpoints are still demo implementations. The conversation list, the feedback endpoint and all `/api/ai/mcp/*` endpoints return sample data or do not store anything. Each section below says so where it applies.

## Authentication

All AI endpoints require a Laravel Sanctum token:

```http
Authorization: Bearer {token}
```

## AI Chat Endpoints

### Send Message to AI Agent

```http
POST /api/ai/chat
```

The router sends the message to the matching agents and combines their answers. In demo mode (`AI_DEMO_MODE=true`) the agents answer from templates without a language model.

#### Request Body

```json
{
  "message": "What is my account balance?",
  "conversation_id": "9b1f6c2e-3d4a-4f5b-8c7d-1e2f3a4b5c6d",  // Optional UUID, generated if not provided
  "context": {                       // Optional context data
    "account_type": "checking",
    "previous_topic": "transfers"
  },
  "model": "gpt-4",                  // Optional: gpt-4, gpt-3.5-turbo, claude-3
  "temperature": 0.7,                // Optional: 0.0 to 2.0
  "stream": false                     // Optional
}
```

`message` is required and can be up to 4,000 characters.

#### Response

```json
{
  "conversation_id": "9b1f6c2e-3d4a-4f5b-8c7d-1e2f3a4b5c6d",
  "message_id": "4c3b2a19-8d7e-4f6a-b5c4-d3e2f1a0b9c8",
  "response": "Your current checking account balance is $12,456.78.",
  "confidence": 0.95,
  "tools_used": [
    "account.balance"
  ],
  "context": {
    "account_type": "checking"
  }
}
```

### Get User Conversations

```http
GET /api/ai/conversations?limit=10
```

This endpoint is a demo. It returns two sample conversations, whatever the user has done.

#### Response

```json
[
  {
    "id": "0d5e2f7a-...",
    "title": "Account Balance Inquiry",
    "last_message": "Your balance is $12,456.78",
    "message_count": 3,
    "created_at": "2026-11-23T08:00:00+00:00",
    "updated_at": "2026-11-23T08:00:00+00:00"
  }
]
```

### Get Conversation Details

```http
GET /api/ai/conversations/{conversationId}
```

The endpoint returns the user's conversation from the cache. For any other id it returns a sample conversation, so it never answers `404` today.

#### Response

```json
{
  "id": "9b1f6c2e-3d4a-4f5b-8c7d-1e2f3a4b5c6d",
  "messages": [
    {
      "role": "user",
      "content": "What is my account balance?",
      "timestamp": "2026-11-23T10:00:00+00:00"
    },
    {
      "role": "assistant",
      "content": "Your current checking account balance is $12,456.78.",
      "timestamp": "2026-11-23T10:00:05+00:00"
    }
  ]
}
```

### Delete Conversation

```http
DELETE /api/ai/conversations/{conversationId}
```

A successful delete returns `204 No Content`. An unknown conversation returns `404` with `{"error": "Conversation not found"}`.

### Submit Feedback

```http
POST /api/ai/feedback
```

#### Request Body

```json
{
  "message_id": "4c3b2a19-8d7e-4f6a-b5c4-d3e2f1a0b9c8",
  "rating": 5,
  "feedback": "Very helpful and accurate response"
}
```

`rating` is an integer from 1 to 5. `feedback` is optional and can be up to 1,000 characters.

#### Response

```json
{
  "message": "Feedback submitted successfully"
}
```

The feedback is not stored yet.

## MCP Tools Endpoints (demo)

These endpoints are a demo served by `MCPToolsController`. They list six sample tools of their own (`get_account_balance`, `authorize_transfer`, `check_fraud_risk`, `get_transaction_history`, `analyze_spending_patterns`, `create_budget_plan`) and return fixed sample data. They do not use the tool registry and do not touch real accounts. The public MCP server at `https://mcp.zelta.app/mcp` is the working tool interface; see [MCP_INTEGRATION.md](MCP_INTEGRATION.md).

### List Available Tools

```http
GET /api/ai/mcp/tools
```

#### Response

```json
{
  "data": [
    {
      "name": "get_account_balance",
      "description": "Retrieve the current balance for a customer account",
      "category": "account_management",
      "parameters": {
        "account_id": {"type": "string", "required": true},
        "include_pending": {"type": "boolean", "required": false},
        "currency": {"type": "string", "required": false}
      },
      "requires_auth": true,
      "rate_limit": 100
    }
  ]
}
```

### Get Tool Details

```http
GET /api/ai/mcp/tools/{tool}
```

The response describes one sample tool with its parameters and examples. An unknown tool returns `404`.

### Execute Tool

```http
POST /api/ai/mcp/tools/{tool}/execute
```

#### Request Body

```json
{
  "parameters": {
    "account_id": "ACC001",
    "currency": "USD"
  }
}
```

#### Response

```json
{
  "success": true,
  "tool": "get_account_balance",
  "result": {
    "account_id": "ACC001",
    "available_balance": 12456.78,
    "current_balance": 12556.78,
    "pending_balance": 100.00,
    "currency": "USD",
    "as_of": "2026-11-23T10:00:00+00:00"
  },
  "execution_time_ms": 0,
  "metadata": {
    "user_id": 1,
    "timestamp": "2026-11-23T10:00:00+00:00"
  }
}
```

The balance figures are fixed sample values. An unknown tool returns `404` with `{"success": false, "error": "Tool not found"}`.

### Register Custom Tool

```http
POST /api/ai/mcp/register
```

#### Request Body

```json
{
  "name": "custom_analytics",
  "description": "Custom analytics for user transactions",
  "endpoint": "https://api.example.com/analytics",
  "parameters": {
    "user_id": {"type": "integer", "required": true}
  },
  "category": "analytics",
  "requires_auth": true,
  "rate_limit": 100
}
```

The controller would answer `201` with a generated `tool_id` and would not store the tool. In practice the request fails first. Its validation checks the name against an `mcp_tools` table, and no migration creates that table, so the request ends in a server error.

## Error Responses

Laravel returns its standard error shapes.

### 422 Unprocessable Entity

```json
{
  "message": "The message field is required.",
  "errors": {
    "message": ["The message field is required."]
  }
}
```

### 401 Unauthorized

```json
{
  "message": "Unauthenticated."
}
```

### 404 Not Found

```json
{
  "error": "Conversation not found"
}
```

`DELETE /api/ai/conversations/{conversationId}` returns this when the conversation is not in the cache.

### 429 Too Many Requests

The response carries `Retry-After` and `X-RateLimit-*` headers.

## Rate Limiting

The `/api/ai/*` group uses the `api.rate_limit:private` middleware. `ApiRateLimitMiddleware` has no `private` tier, so it falls back to the `query` tier. Each user gets 100 requests per minute on each endpoint, and a client that goes over is blocked for 30 seconds.

## Not available

There is no AI WebSocket channel, no AI webhook registration endpoint and no dedicated AI SDK. Use the REST endpoints above or the public MCP server.

## Next Steps

- [MCP Integration Guide](MCP_INTEGRATION.md)
- [Workflow Development](03-Workflows.md)
- [Event Sourcing Patterns](04-Event-Sourcing.md)
