#!/usr/bin/env node
import { loadConfig } from './config.js';
import { OAuthHelper } from './oauth-helper.js';
import { StdioRelay } from './stdio-relay.js';

async function main(): Promise<void> {
  const cfg    = loadConfig();
  const helper = new OAuthHelper(cfg);

  if (process.argv.includes('--logout')) {
    await helper.logout();
    process.stderr.write('Logged out — keychain entry deleted.\n');

    return;
  }

  const relay = new StdioRelay(cfg, () => helper.getAccessToken());
  await relay.start();
}

main().catch((err) => {
  process.stderr.write(`fatal: ${String(err)}\n`);
  process.exit(1);
});
