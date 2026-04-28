# MCP Quickstart — Connect in 30 seconds

## Claude Desktop

Add to `~/Library/Application Support/Claude/claude_desktop_config.json` (macOS) or `%APPDATA%\Claude\claude_desktop_config.json` (Windows):

```json
{ "mcpServers": { "zelta": { "url": "https://mcp.zelta.app/mcp" } } }
```

Restart Claude Desktop. A consent screen opens in your browser. Choose a daily spending limit, click **Approve**.

## Cursor

`Settings → Features → MCP → Add Server`. URL: `https://mcp.zelta.app/mcp`.

## Continue.dev

`~/.continue/config.json`:

```json
{
  "experimental": {
    "modelContextProtocolServer": {
      "transport": { "type": "streamable-http", "url": "https://mcp.zelta.app/mcp" }
    }
  }
}
```

## Stdio-only clients (npm)

For clients that don't yet support remote streamable-HTTP MCP — install the relay:

```bash
npx -y @finaegis/mcp
```

Then point your client at `npx -y @finaegis/mcp` as the command. The relay handles OAuth itself and persists the token in your OS keychain (`finaegis-mcp` service). Clear it with `npx @finaegis/mcp --logout`.

## First call

Once connected, ask the agent:

> "What is my Zelta USD balance?"

It will call `account.balance` and surface the result.

## Try a write tool

> "Send $1 to my friend's account at jane@example.com"

The agent will call `payment.transfer` with an `idempotency_key` (UUID), and the response will include the settlement reference. Repeat the same prompt within 24 hours and the server returns the cached result rather than re-charging.

## Spending limit

The default per-token limit is **$500 / 24h rolling window**, applied across `payment.transfer` + `exchange.trade` + `ramp.start` + `sms.send` settlement combined. You set this on the consent screen at first connection. Hitting the limit returns `-32003 SPENDING_LIMIT_EXCEEDED` with `data.window_resets_at` so the agent can tell you when it resets.

## Troubleshooting

| Symptom | Fix |
|---|---|
| Browser doesn't open (npm relay) | `MCP_OAUTH_NO_BROWSER=1 npx -y @finaegis/mcp` — URL prints to stderr |
| `-32001 UNAUTHENTICATED` | Token expired or missing — re-authorize. For npm: `npx @finaegis/mcp --logout` then run again |
| `-32004 TOOL_DISABLED` | Operator-disabled. Status: [status.zelta.app](https://status.zelta.app) |
| Agent doesn't see a tool you expected | The token's scope didn't include it. Re-authorize with the right scopes checked |
