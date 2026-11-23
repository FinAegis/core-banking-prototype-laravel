# Q13: Bidirectional MCP Setup — Details for VertexSMS

**Context:** VertexSMS asked for more details on what the bidirectional setup involves before committing.

> Integration adapters for third-party APIs; no partnership or endorsement implied.

**Status at v7.18.1:** The SMS tool is built, but it is switched off on the production site. `.env.zelta.example` sets `SMS_ENABLED=false` and `MCP_TOOL_SMS_SEND=false`. The sections below describe how the tool works when an operator switches it on.

---

## What "Bidirectional" Means

Today the setup works in one direction. AI agents call the `sms.send` tool on Zelta's MCP server, and Zelta calls the VertexSMS API to deliver the message.

The bidirectional setup adds the other direction. VertexSMS would appear as a named SMS provider inside Zelta's MCP server. Any agent connected to Zelta could then find VertexSMS as an SMS option, next to any providers added later.

## What VertexSMS Would Provide

| Item | Description | Effort |
|------|------------|--------|
| **Sandbox API token** | A test-mode token for our MCP server to call your API | 0 — you already gave us one |
| **Signed DLR callbacks** | HMAC-SHA256 on DLR webhooks (already confirmed in Q2) | 0 |
| **Rate card endpoint** | `GET /rates/?format=json` (already exists) | 0 |
| **Logo + description** | Brand assets for the provider listing (64x64 icon, one-line tagline) | 5 minutes |

VertexSMS would need to do almost nothing. Everything technical is already in place.

## What Zelta Has Built

- `SmsSendTool` is registered on the MCP server as the `sms.send` tool.
- The VertexSMS client is the SMS provider behind this tool.
- Any MCP client that follows the specification can find the tool on the public server at `https://mcp.zelta.app/mcp`. Examples are Claude Desktop, Cursor, Continue.dev and custom agents. The server publishes its RFC 9728 metadata at `https://mcp.zelta.app/.well-known/oauth-protected-resource`.

A send through `sms.send` is not paid per message. It needs a signed-in user and counts against the daily limits for unpaid sends, both per user and across all users. Those limits are shared with `POST /api/v1/sms/send` and reset at 00:00 UTC. On the public MCP server each send also counts against the daily spending limit that the user chose when connecting the agent. A message must fit in one SMS part, which is 160 GSM characters, or 70 when it has other characters. Paid sends go through `POST /api/v1/sms/send` when the payment gate is on (see [VERTEXSMS_X402_INTEGRATION.md](VERTEXSMS_X402_INTEGRATION.md)).

## Connection

An agent connects with an OAuth token that belongs to a Zelta user. The client registers itself at `POST /oauth/register` and gets the user's consent through the authorization code flow. The token needs the `sms:send` scope.

```
1. Agent → authorization code flow with the user's consent
              scope=sms:send
              ← { access_token, expires_in, scope }

2. Agent → POST https://mcp.zelta.app/mcp
              Authorization: Bearer <access_token>
              { "jsonrpc": "2.0", "method": "tools/call",
                "params": { "name": "sms.send",
                            "arguments": { "to": "...", "message": "...", "idempotency_key": "<uuid>" } } }
              ← JSON-RPC envelope with the message id and status
```

A token without a user cannot call the MCP server. The authorization server metadata lists the `client_credentials` grant, but such a token gets `401` with `-32001`. The router also returns `-32006 USER_CONTEXT_REQUIRED` for `sms.send` as a second guard. A partner sub-account bound to a `client_credentials` client is not built.

For the full OAuth contract see [13-AI-FRAMEWORK/04-MCP-OAuth-Reference.md](../13-AI-FRAMEWORK/04-MCP-OAuth-Reference.md).

## What "Discoverable" Means for VertexSMS

When an AI agent connects to Zelta's MCP server, `tools/list` shows the SMS tool like this (shortened):

```json
{
  "tools": [
    {
      "name": "sms.send",
      "description": "Send an SMS message via VertexSMS while SMS sending is enabled. Requires a signed-in user. ...",
      "inputSchema": {
        "type": "object",
        "properties": {
          "to": { "type": "string", "description": "Destination phone number in E.164 format (e.g., +37069912345)" },
          "from": { "type": "string", "description": "Sender ID: 1-20 letters, digits or spaces (default: Zelta)" },
          "message": { "type": "string", "description": "SMS message text that fits in one SMS part: up to 160 GSM characters, or 70 when it has other characters" },
          "check_price_only": { "type": "boolean", "description": "If true, returns price without sending. Default: false." },
          "idempotency_key": { "type": "string", "format": "uuid" }
        },
        "required": ["to", "message", "idempotency_key"]
      },
      "annotations": { "title": "Send an SMS", "readOnlyHint": false, "destructiveHint": true }
    }
  ]
}
```

The agent does not need to know VertexSMS directly. It finds the SMS capability through Zelta's tool list.

## Benefits for VertexSMS

1. VertexSMS needs no integration work, because everything is already built on our side.
2. Every agent connected to Zelta becomes a possible VertexSMS customer, which gives VertexSMS a new distribution channel.
3. VertexSMS needs no API changes, because we call your existing `POST /sms` endpoint.
4. Agents can pay per message through `POST /api/v1/sms/send` once the payment gate is switched on. The settlement design is still under discussion; see [VERTEXSMS_X402_INTEGRATION §6](VERTEXSMS_X402_INTEGRATION.md#6-money-flow).

## Next Step

If you are open to this, we need two things from you.
1. Confirmation that we can name "VertexSMS" as the provider in the tool description.
2. A small logo or icon for the provider listing. This is optional, and we can use a text placeholder.

No code changes or API work are needed from your side.
