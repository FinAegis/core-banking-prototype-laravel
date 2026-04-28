# Q13: Bidirectional MCP Setup — Details for VertexSMS

**Context:** VertexSMS asked for more details on what the bidirectional setup involves before committing.

---

## What "Bidirectional" Means

Right now the plan is one-directional:
- **Zelta → VertexSMS**: AI agents call Zelta's MCP `send_sms` tool, which internally calls VertexSMS API to deliver the SMS.

Bidirectional adds the reverse:
- **VertexSMS → Zelta**: VertexSMS appears as a discoverable service provider *inside* Zelta's MCP ecosystem. Any Zelta-connected agent can discover VertexSMS as an SMS rail alongside other future providers.

## What VertexSMS Would Provide

| Item | Description | Effort |
|------|------------|--------|
| **Sandbox API token** | A test-mode token for our MCP server to call your API | 0 — you already gave us one |
| **Signed DLR callbacks** | HMAC-SHA256 on DLR webhooks (already confirmed in Q2) | 0 — already agreed |
| **Rate card endpoint** | `GET /rates/?format=json` (already exists) | 0 |
| **Logo + description** | Brand assets for the provider listing (64x64 icon, one-line tagline) | 5 minutes |

**Total effort from VertexSMS: near zero** — everything technical is already in place.

## What Zelta Builds (Already Done)

- `SmsSendTool` MCP tool is registered and exposes `sms.send` with payment auto-handling (per-message x402 settlement).
- VertexSMS client is wired as the SMS provider behind this tool.
- Any spec-compliant MCP client (Claude Desktop, Cursor, Continue.dev, custom agents) can discover the tool via the public server at `https://mcp.zelta.app/mcp` and its RFC 9728 metadata at `https://mcp.zelta.app/.well-known/oauth-protected-resource`.

## Connection (the real handshake)

VertexSMS-style integrations use the **client-credentials** grant — the partner pre-registers a confidential client and is bound to its own funded sub-account, with the same per-token spending-limit policy as user-delegated tokens.

```
1. Partner → POST https://zelta.app/oauth/token
              grant_type=client_credentials
              scope=sms:send
              client_id=<id> client_secret=<secret>
              ← { access_token, expires_in, scope }

2. Partner → POST https://mcp.zelta.app/mcp
              Authorization: Bearer <access_token>
              { "jsonrpc": "2.0", "method": "tools/call",
                "params": { "name": "sms.send", "arguments": { ... } } }
              ← JSON-RPC envelope with delivery receipt
```

The partner's confidential client is registered manually (not via DCR) so the operator can bind it to a specific sub-account. `client_credentials` tokens are NOT allowed for user-bound tools (they return `-32006 USER_CONTEXT_REQUIRED`); `sms.send` is callable in this mode because per-message settlement debits the partner sub-account, not a user.

For the full OAuth contract see [13-AI-FRAMEWORK/04-MCP-OAuth-Reference.md](../13-AI-FRAMEWORK/04-MCP-OAuth-Reference.md).

## What "Discoverable" Means for VertexSMS

When an AI agent connects to Zelta's MCP server, it sees available tools:

```json
{
  "tools": [
    {
      "name": "send_sms",
      "description": "Send SMS via VertexSMS. Pay per-message via USDC, Stripe, or Lightning.",
      "provider": "VertexSMS",
      "inputSchema": {
        "type": "object",
        "properties": {
          "to": { "type": "string", "description": "E.164 phone number" },
          "from": { "type": "string", "description": "Sender ID" },
          "message": { "type": "string", "description": "Message body" }
        },
        "required": ["to", "message"]
      }
    }
  ]
}
```

The agent doesn't need to know about VertexSMS directly — it discovers SMS capability through Zelta's tool registry. Payment is handled transparently by Zelta's SDK.

## Benefits for VertexSMS

1. **Zero integration work** — everything is already built on our side
2. **New distribution channel** — every Zelta-connected AI agent is a potential VertexSMS customer
3. **No API changes** — we call your existing `POST /sms` endpoint
4. **Revenue from day one** — agents pay per-message, settlement to your account via Stripe Connect or USDC

## Next Step

If you're open to this, we just need:
1. Confirmation we can list "VertexSMS" as the provider name in the tool description
2. A small logo/icon for the provider listing (optional, we can use a text placeholder)

No code changes or API work needed from your side.
