# @finaegis/mcp

Connect Claude Desktop, Cursor, or Continue.dev to **Zelta** via the Model Context Protocol.

## First-time login

```bash
npx -y @finaegis/mcp --login
```

This prints an authorization URL to the terminal and (where possible) opens it in your browser. After you complete consent, the token is persisted and the wrapper exits. You can re-run `--login` at any time to refresh credentials.

On Linux without `libsecret` (WSL2, Docker, Alpine, headless servers), set `NPM_CONFIG_OMIT=optional` to skip installing the native `keytar` dependency:

```bash
NPM_CONFIG_OMIT=optional npx -y @finaegis/mcp@0.1.2 --login
```

## Configure (Claude Desktop)

Once logged in, add to `claude_desktop_config.json`:

```json
{
  "mcpServers": {
    "zelta": { "command": "npx", "args": ["-y", "@finaegis/mcp"] }
  }
}
```

On Linux without `libsecret`, include the env var:

```json
{
  "mcpServers": {
    "zelta": {
      "command": "npx",
      "args": ["-y", "@finaegis/mcp"],
      "env": { "NPM_CONFIG_OMIT": "optional" }
    }
  }
}
```

Tokens are stored in your OS keychain (macOS Keychain, Windows Credential Manager, or Linux libsecret/gnome-keyring) when available. Subsequent launches are silent.

If the OS keychain is unavailable — common on **WSL2, Docker, Alpine, and headless Linux** — the relay automatically falls back to a file at `$XDG_CONFIG_HOME/finaegis-mcp/tokens.json` with permissions `0600` (read/write for the owning user only). The file is plain JSON, not encrypted at rest. A one-line warning is printed to stderr the first time this happens.

## Environment overrides

| Variable | Default |
|---|---|
| `MCP_SERVER_URL` | `https://mcp.zelta.app/mcp` |
| `MCP_AUTH_SERVER` | `https://zelta.app` |
| `MCP_OAUTH_NO_BROWSER` | unset — set to `1` to print the auth URL instead of opening a browser |
| `FINAEGIS_MCP_TOKEN_STORE` | auto — set to `file` to force the file store, or `keychain` to require the OS keychain (errors if missing) |

## Troubleshooting

- **Browser does not open** — set `MCP_OAUTH_NO_BROWSER=1` and follow the URL printed to stderr.
- **Token expired** — log out and re-auth: `npx @finaegis/mcp --logout`.
- **`libsecret-1.so.0: cannot open shared object file`** — the keychain backend can't load. The relay handles this automatically and falls back to the file store; you can also force it with `FINAEGIS_MCP_TOKEN_STORE=file`.
