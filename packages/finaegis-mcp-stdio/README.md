# @finaegis/mcp

Connect Claude Desktop, Cursor, or Continue.dev to **Zelta** via the Model Context Protocol.

## Install

```bash
npx -y @finaegis/mcp
```

## Configure (Claude Desktop)

Add to `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "zelta": { "command": "npx", "args": ["-y", "@finaegis/mcp"] }
  }
}
```

First launch opens a browser for OAuth consent. The token is stored in your OS keychain. Subsequent launches are silent.

## Environment overrides

| Variable | Default |
|---|---|
| `MCP_SERVER_URL` | `https://mcp.zelta.app/mcp` |
| `MCP_AUTH_SERVER` | `https://zelta.app` |
| `MCP_TOKEN_PATH` | OS keychain via `keytar` |

## Troubleshooting

- **Browser does not open** — set `MCP_OAUTH_NO_BROWSER=1` and follow the URL printed to stderr.
- **Token expired** — delete the keychain entry: `npx @finaegis/mcp --logout`.
