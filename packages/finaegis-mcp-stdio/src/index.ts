#!/usr/bin/env node
import { loadConfig } from './config.js';
import { OAuthHelper } from './oauth-helper.js';
import { StdioRelay } from './stdio-relay.js';

async function main(): Promise<void> {
  const cfg    = loadConfig();
  const helper = new OAuthHelper(cfg);

  if (process.argv.includes('--logout')) {
    await helper.logout();
    process.stderr.write('Logged out — credentials deleted.\n');

    return;
  }

  if (process.argv.includes('--login')) {
    // Drive the OAuth flow standalone, without waiting for an MCP client to
    // hand us a stdio request. Useful for first-time setup verification on
    // WSL2/headless boxes where the user wants to confirm auth works before
    // wiring the relay into Claude Desktop or another client.
    process.stderr.write(`Logging in to ${cfg.authServer} for ${cfg.serverUrl}...\n`);
    await helper.getAccessToken();
    process.stderr.write('Logged in. You can now configure your MCP client to use this relay.\n');

    return;
  }

  const relay = new StdioRelay(cfg, () => helper.getAccessToken());
  await relay.start();
}

main().catch((err) => {
  process.stderr.write(`fatal: ${String(err)}\n`);
  process.exit(1);
});
