export interface RelayConfig {
  serverUrl: string;
  authServer: string;
  oauthNoBrowser: boolean;
  keychainService: string;
  keychainAccount: string;
}

export function loadConfig(): RelayConfig {
  return {
    serverUrl:       process.env.MCP_SERVER_URL  ?? 'https://mcp.zelta.app/mcp',
    authServer:      process.env.MCP_AUTH_SERVER ?? 'https://zelta.app',
    oauthNoBrowser:  process.env.MCP_OAUTH_NO_BROWSER === '1',
    keychainService: 'finaegis-mcp',
    keychainAccount: 'default',
  };
}
