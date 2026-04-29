# @finaegis/mcp

Connect Claude Desktop, Cursor, or Continue.dev to **Zelta** via the Model Context Protocol.

## First-time login

```bash
npx -y @finaegis/mcp --login
```

This prints an authorization URL to the terminal and (where possible) opens it in your browser. After you complete consent, the token is persisted and the wrapper exits. You can re-run `--login` at any time to refresh credentials.

## Configure (Claude Desktop)

Once logged in, add to `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "zelta": { "command": "npx", "args": ["-y", "@finaegis/mcp"] }
  }
}
```

## Token storage

By default, tokens are stored in `$XDG_CONFIG_HOME/finaegis-mcp/tokens.json` (typically `~/.config/finaegis-mcp/tokens.json`) with file permissions `0600` (read/write for the owning user only). The file is plain JSON, not encrypted at rest.

**Optional: OS keychain backend.** If you prefer macOS Keychain / Windows Credential Manager / Linux libsecret, install `keytar` globally and opt in:

```bash
npm i -g keytar
FINAEGIS_MCP_TOKEN_STORE=keychain npx -y @finaegis/mcp --login
```

`keytar` is **not** a dependency of this package — it's a native module that fails to install on common environments (WSL2, Docker, Alpine, CI). Keeping the default install pure JavaScript means `npx -y @finaegis/mcp` just works everywhere.

## Environment overrides

| Variable | Default |
|---|---|
| `MCP_SERVER_URL` | `https://mcp.zelta.app/mcp` |
| `MCP_AUTH_SERVER` | `https://zelta.app` |
| `MCP_OAUTH_NO_BROWSER` | unset — set to `1` to print the auth URL instead of opening a browser |
| `FINAEGIS_MCP_TOKEN_STORE` | `file` (default) — set to `keychain` to use the OS keychain via `keytar` (must be installed separately) |

## Troubleshooting

- **Browser does not open** — set `MCP_OAUTH_NO_BROWSER=1` and follow the URL printed to stderr.
- **Token expired** — log out and re-auth: `npx @finaegis/mcp --logout`.
- **`libsecret-1.so.0: cannot open shared object file`** — only happens if you opted into `FINAEGIS_MCP_TOKEN_STORE=keychain` on a Linux system without `libsecret`. Unset the variable (the default file store needs no native deps) or install `libsecret-1-dev` + a keyring daemon.
