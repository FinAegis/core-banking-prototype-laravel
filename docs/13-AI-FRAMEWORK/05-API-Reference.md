# AI Framework API Reference

## Base URL
```
https://api.finaegis.org/api/ai
```

## Authentication

All AI endpoints require authentication via Laravel Sanctum:

```http
Authorization: Bearer {token}
```

## AI Chat Endpoints

### Send Message to AI Agent

```http
POST /api/ai/chat
```

#### Request Body

```json
{
  "message": "What is my account balance?",
  "conversation_id": "conv_123abc",  // Optional, will be generated if not provided
  "context": {                       // Optional context data
    "account_type": "checking",
    "previous_topic": "transfers"
  },
  "model": "gpt-4",                  // Optional: gpt-4, gpt-3.5-turbo, claude-3
  "temperature": 0.7,                // Optional: 0.0 to 2.0
  "stream": false                     // Optional: Enable streaming response
}
```

#### Response

```json
{
  "conversation_id": "conv_123abc",
  "message_id": "msg_456def",
  "response": "Your current checking account balance is $12,456.78.",
  "confidence": 0.95,
  "tools_used": [
    "CheckBalanceTool",
    "AccountLookupTool"
  ],
  "context": {
    "account_number": "****1234",
    "last_updated": "2024-09-08T10:30:00Z"
  }
}
```

### Get User Conversations

```http
GET /api/ai/conversations?limit=10
```

#### Response

```json
{
  "data": [
    {
      "id": "conv_123abc",
      "title": "Account Balance Inquiry",
      "last_message": "Your balance is $12,456.78",
      "message_count": 5,
      "created_at": "2024-09-08T09:00:00Z",
      "updated_at": "2024-09-08T10:30:00Z"
    }
  ],
  "meta": {
    "total": 25,
    "per_page": 10,
    "current_page": 1
  }
}
```

### Get Conversation Details

```http
GET /api/ai/conversations/{conversationId}
```

#### Response

```json
{
  "id": "conv_123abc",
  "messages": [
    {
      "role": "user",
      "content": "What is my account balance?",
      "timestamp": "2024-09-08T10:00:00Z"
    },
    {
      "role": "assistant",
      "content": "Your current checking account balance is $12,456.78.",
      "timestamp": "2024-09-08T10:00:05Z",
      "tools_used": ["CheckBalanceTool"],
      "confidence": 0.95
    }
  ],
  "context": {
    "user_id": 123,
    "account_type": "checking"
  },
  "created_at": "2024-09-08T10:00:00Z"
}
```

### Delete Conversation

```http
DELETE /api/ai/conversations/{conversationId}
```

#### Response

```json
{
  "success": true,
  "message": "Conversation deleted successfully"
}
```

### Submit Feedback

```http
POST /api/ai/feedback
```

#### Request Body

```json
{
  "message_id": "msg_456def",
  "rating": 5,
  "feedback": "Very helpful and accurate response"
}
```

#### Response

```json
{
  "success": true,
  "message": "Feedback recorded successfully"
}
```

## MCP Tools Endpoints

### List Available Tools

```http
GET /api/ai/mcp/tools
```

#### Response

```json
{
  "data": [
    {
      "name": "CheckBalanceTool",
      "category": "account",
      "description": "Check account balance",
      "parameters": {
        "account_id": {
          "type": "string",
          "required": false,
          "description": "Account ID (optional, defaults to primary)"
        }
      },
      "requires_auth": true,
      "cache_ttl": 300
    },
    {
      "name": "TransferFundsTool",
      "category": "payment",
      "description": "Transfer funds between accounts",
      "parameters": {
        "from_account": {
          "type": "string",
          "required": true,
          "description": "Source account"
        },
        "to_account": {
          "type": "string",
          "required": true,
          "description": "Destination account"
        },
        "amount": {
          "type": "number",
          "required": true,
          "description": "Transfer amount"
        }
      },
      "requires_auth": true,
      "requires_2fa": true,
      "cache_ttl": 0
    }
  ]
}
```

### Get Tool Details

```http
GET /api/ai/mcp/tools/{toolName}
```

#### Response

```json
{
  "name": "CheckBalanceTool",
  "category": "account",
  "description": "Check account balance for a specified account",
  "version": "1.0.0",
  "parameters": {
    "account_id": {
      "type": "string",
      "required": false,
      "description": "Account ID (defaults to primary account)",
      "example": "ACC001"
    },
    "currency": {
      "type": "string",
      "required": false,
      "description": "Currency code for balance",
      "example": "USD",
      "default": "USD"
    }
  },
  "response_schema": {
    "type": "object",
    "properties": {
      "balance": {"type": "number"},
      "currency": {"type": "string"},
      "available": {"type": "number"},
      "pending": {"type": "number"}
    }
  },
  "examples": [
    {
      "input": {"account_id": "ACC001"},
      "output": {
        "balance": 12456.78,
        "currency": "USD",
        "available": 12000.00,
        "pending": 456.78
      }
    }
  ],
  "requires_auth": true,
  "rate_limit": "100/hour",
  "cache_ttl": 300
}
```

### Execute Tool

```http
POST /api/ai/mcp/tools/{toolName}/execute
```

#### Request Body

```json
{
  "parameters": {
    "account_id": "ACC001",
    "currency": "USD"
  },
  "context": {
    "conversation_id": "conv_123abc",
    "user_intent": "balance_check"
  }
}
```

#### Response

```json
{
  "success": true,
  "result": {
    "balance": 12456.78,
    "currency": "USD",
    "available": 12000.00,
    "pending": 456.78,
    "last_updated": "2024-09-08T10:30:00Z"
  },
  "execution_time": 0.125,
  "cached": false,
  "tool_version": "1.0.0"
}
```

### Register Custom Tool

```http
POST /api/ai/mcp/tools/register
```

#### Request Body

```json
{
  "name": "CustomAnalyticsTool",
  "description": "Custom analytics for user transactions",
  "category": "analytics",
  "endpoint": "https://api.example.com/analytics",
  "parameters": {
    "user_id": {
      "type": "integer",
      "required": true,
      "description": "User ID for analytics"
    },
    "date_range": {
      "type": "object",
      "required": false,
      "properties": {
        "start": {"type": "string", "format": "date"},
        "end": {"type": "string", "format": "date"}
      }
    }
  },
  "headers": {
    "X-API-Key": "your-api-key"
  },
  "cache_ttl": 3600
}
```

#### Response

```json
{
  "success": true,
  "tool_id": "tool_custom_analytics_001",
  "message": "Tool registered successfully"
}
```

## Error Responses

### 400 Bad Request

```json
{
  "error": "validation_error",
  "message": "The given data was invalid",
  "errors": {
    "message": ["The message field is required"],
    "model": ["The selected model is invalid"]
  }
}
```

### 401 Unauthorized

```json
{
  "error": "unauthorized",
  "message": "Unauthenticated"
}
```

### 403 Forbidden

```json
{
  "error": "forbidden",
  "message": "You do not have permission to execute this tool"
}
```

### 404 Not Found

```json
{
  "error": "not_found",
  "message": "Conversation not found"
}
```

### 429 Too Many Requests

```json
{
  "error": "rate_limit_exceeded",
  "message": "Too many requests",
  "retry_after": 60
}
```

### 500 Internal Server Error

```json
{
  "error": "internal_error",
  "message": "An unexpected error occurred",
  "trace_id": "trace_123abc"
}
```

## WebSocket Events

### Connect to WebSocket

```javascript
const socket = new WebSocket('wss://api.finaegis.org/ai/ws');

socket.onopen = () => {
  // Authenticate
  socket.send(JSON.stringify({
    type: 'auth',
    token: 'your-sanctum-token'
  }));
  
  // Subscribe to conversation
  socket.send(JSON.stringify({
    type: 'subscribe',
    conversation_id: 'conv_123abc'
  }));
};
```

### Event Types

#### Message Stream

```json
{
  "type": "message_stream",
  "conversation_id": "conv_123abc",
  "content": "Your account balance",
  "complete": false
}
```

#### Tool Execution

```json
{
  "type": "tool_execution",
  "conversation_id": "conv_123abc",
  "tool": "CheckBalanceTool",
  "status": "executing"
}
```

#### Human Intervention Request

```json
{
  "type": "human_intervention",
  "conversation_id": "conv_123abc",
  "reason": "Low confidence decision",
  "context": {
    "confidence": 0.45,
    "decision": "large_transfer"
  }
}
```

## Rate Limiting

| Endpoint | Rate Limit | Window |
|----------|------------|--------|
| Chat | 60 requests | 1 minute |
| Tool Execution | 100 requests | 1 hour |
| Tool Registration | 10 requests | 1 hour |
| Conversations List | 120 requests | 1 minute |

## SDK Examples

### JavaScript/TypeScript

```typescript
import { FinAegisAI } from '@finaegis/ai-sdk';

const ai = new FinAegisAI({
  apiKey: 'your-api-key',
  baseUrl: 'https://api.finaegis.org'
});

// Send message
const response = await ai.chat({
  message: 'What is my balance?',
  conversationId: 'conv_123'
});

// Execute tool
const result = await ai.executeTool('CheckBalanceTool', {
  account_id: 'ACC001'
});
```

### Python

```python
from finaegis import AIClient

client = AIClient(
    api_key='your-api-key',
    base_url='https://api.finaegis.org'
)

# Send message
response = client.chat(
    message='What is my balance?',
    conversation_id='conv_123'
)

# Execute tool
result = client.execute_tool(
    'CheckBalanceTool',
    parameters={'account_id': 'ACC001'}
)
```

### PHP

```php
use FinAegis\AI\Client;

$client = new Client([
    'api_key' => 'your-api-key',
    'base_url' => 'https://api.finaegis.org'
]);

// Send message
$response = $client->chat([
    'message' => 'What is my balance?',
    'conversation_id' => 'conv_123'
]);

// Execute tool
$result = $client->executeTool('CheckBalanceTool', [
    'account_id' => 'ACC001'
]);
```

## Webhook Integration

Register webhooks to receive AI events:

```http
POST /api/ai/webhooks
```

```json
{
  "url": "https://your-app.com/webhooks/ai",
  "events": [
    "conversation.started",
    "decision.made",
    "tool.executed",
    "human.intervention.requested"
  ],
  "secret": "webhook-secret-key"
}
```

Webhook payload example:

```json
{
  "event": "decision.made",
  "timestamp": "2024-09-08T10:30:00Z",
  "data": {
    "conversation_id": "conv_123abc",
    "decision": "transfer_approved",
    "confidence": 0.92,
    "factors": {
      "risk_score": 0.15,
      "account_history": "good",
      "amount_threshold": "within_limits"
    }
  },
  "signature": "sha256=..."
}
```

## Next Steps

- [Testing Guide](06-Testing.md)
- [Deployment Guide](07-Deployment.md)
- [Monitoring Guide](08-Monitoring.md)