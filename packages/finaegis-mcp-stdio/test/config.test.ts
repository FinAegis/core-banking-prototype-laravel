import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { loadConfig } from '../src/config.js';

describe('loadConfig', () => {
  const original = { ...process.env };
  beforeEach(() => {
    process.env = { ...original };
  });
  afterEach(() => {
    process.env = { ...original };
  });

  it('uses production defaults when no env vars set', () => {
    delete process.env.MCP_SERVER_URL;
    delete process.env.MCP_AUTH_SERVER;
    delete process.env.MCP_OAUTH_NO_BROWSER;
    const cfg = loadConfig();
    expect(cfg.serverUrl).toBe('https://mcp.zelta.app/mcp');
    expect(cfg.authServer).toBe('https://zelta.app');
    expect(cfg.oauthNoBrowser).toBe(false);
    expect(cfg.keychainService).toBe('finaegis-mcp');
    expect(cfg.keychainAccount).toBe('default');
  });

  it('respects MCP_SERVER_URL override', () => {
    process.env.MCP_SERVER_URL = 'https://staging.example/mcp';
    expect(loadConfig().serverUrl).toBe('https://staging.example/mcp');
  });

  it('respects MCP_AUTH_SERVER override', () => {
    process.env.MCP_AUTH_SERVER = 'https://staging-auth.example';
    expect(loadConfig().authServer).toBe('https://staging-auth.example');
  });

  it('flips oauthNoBrowser only on the literal string "1"', () => {
    process.env.MCP_OAUTH_NO_BROWSER = '1';
    expect(loadConfig().oauthNoBrowser).toBe(true);

    process.env.MCP_OAUTH_NO_BROWSER = 'true';
    expect(loadConfig().oauthNoBrowser).toBe(false);

    process.env.MCP_OAUTH_NO_BROWSER = '';
    expect(loadConfig().oauthNoBrowser).toBe(false);
  });
});
