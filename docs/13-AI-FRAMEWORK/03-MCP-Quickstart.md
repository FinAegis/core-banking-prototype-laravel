# MCP Quickstart

This page shows how to connect an AI client to the Zelta MCP server at `https://mcp.zelta.app/mcp`. Clients that support remote MCP servers connect to the URL directly. Clients that only run local (stdio) servers use the `@finaegis/mcp` relay from npm.

## Remote URL clients

### Cursor

Open `Settings → Features → MCP → Add Server` and enter the URL `https://mcp.zelta.app/mcp`.

### Continue.dev

Add the server to `~/.continue/config.json`:

```json
{
  "experimental": {
    "modelContextProtocolServer": {
      "transport": { "type": "streamable-http", "url": "https://mcp.zelta.app/mcp" }
    }
  }
}
```

On the first connection a consent screen opens in your browser. It lists the scopes the client asks for and a daily spending limit. Click **Approve** to issue the token.

## Stdio-only clients (npm relay)

The relay is a small Node.js program (Node 20 or newer). It speaks stdio to your client and forwards each request to the remote server. Log in once first:

```bash
npx -y @finaegis/mcp --login
```

This prints an authorization URL and opens it in your browser where it can. After you approve, the relay saves the token and exits.

Then point your client at the relay. For Claude Desktop, add this to `~/Library/Application Support/Claude/claude_desktop_config.json` (macOS) or `%APPDATA%\Claude\claude_desktop_config.json` (Windows):

```json
{
  "mcpServers": {
    "zelta": { "command": "npx", "args": ["-y", "@finaegis/mcp"] }
  }
}
```

The relay stores tokens in `~/.config/finaegis-mcp/tokens.json` (or under `$XDG_CONFIG_HOME`) with file mode `0600`. The file is plain JSON and is not encrypted. To use the operating system keychain instead, install `keytar` globally and set `FINAEGIS_MCP_TOKEN_STORE=keychain`. Run `npx @finaegis/mcp --logout` to delete the saved credentials.

## First call

Once connected, ask the agent:

> "Show my recent Zelta wallet activity"

It will call `wallet.activity` and show the result.

## Write tools

Write tools such as `ramp.start` need a write scope and an `idempotency_key` in every call. The production server keeps `account.create`, `payment.transfer`, `exchange.trade` and `sms.send` switched off, so it exposes 10 of the 14 catalog tools.

## Spending limit

You choose a daily spending limit on the consent screen when you approve the client. The choices are $50, $500, $2,000, $10,000 or no limit, and $500 is the default. The server saves the limit for your approval of that client. A refreshed token keeps the same limit and the same amount already spent.

The payment tools are `payment.transfer`, `ramp.start` and `sms.send`. Their amounts count against your daily limit over a rolling 24-hour window. A call over the limit returns `-32003 SPENDING_LIMIT_EXCEEDED` with `data.window_resets_at`, so the agent can tell you when the window resets. `exchange.trade` does not count against this limit.

## Troubleshooting

| Symptom | Fix |
|---|---|
| Browser doesn't open (npm relay) | `MCP_OAUTH_NO_BROWSER=1 npx -y @finaegis/mcp --login` — URL prints to stderr |
| `-32001 UNAUTHENTICATED` | Token expired or missing — re-authorize. For npm: `npx @finaegis/mcp --logout` then `--login` again |
| `-32004 TOOL_DISABLED` | The operator has switched the tool off (`MCP_TOOL_*`) |
| `-32003` with `error_code: NO_POLICY` | No spending limit is saved for this approval, usually because it was made before v7.18.1. Connect again so the consent screen appears; for the npm relay, run `--logout` and then `--login` |
| Agent doesn't see a tool you expected | The token lacks the tool's scope, or the tool is switched off. Re-authorize with a client that requests the scope |
