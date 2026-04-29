import { describe, it, expect, beforeEach, afterEach } from 'vitest';
import { promises as fs } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { getTokenStore, _resetTokenStoreForTesting } from '../src/token-store.js';

describe('token-store file backend', () => {
  let dir: string;
  const originalConfigHome = process.env.XDG_CONFIG_HOME;
  const originalForce = process.env.FINAEGIS_MCP_TOKEN_STORE;

  beforeEach(async () => {
    dir = await fs.mkdtemp(path.join(tmpdir(), 'finaegis-mcp-test-'));
    process.env.XDG_CONFIG_HOME = dir;
    process.env.FINAEGIS_MCP_TOKEN_STORE = 'file';
    _resetTokenStoreForTesting();
  });

  afterEach(async () => {
    process.env.XDG_CONFIG_HOME = originalConfigHome;
    process.env.FINAEGIS_MCP_TOKEN_STORE = originalForce;
    _resetTokenStoreForTesting();
    await fs.rm(dir, { recursive: true, force: true });
  });

  it('returns null when no entry exists', async () => {
    const store = await getTokenStore();
    expect(store.kind).toBe('file');
    expect(await store.get('svc', 'acct')).toBeNull();
  });

  it('round-trips a value', async () => {
    const store = await getTokenStore();
    await store.set('svc', 'acct', 'hello');
    expect(await store.get('svc', 'acct')).toBe('hello');
  });

  it('keeps separate accounts independent', async () => {
    const store = await getTokenStore();
    await store.set('svc', 'a', 'one');
    await store.set('svc', 'b', 'two');
    expect(await store.get('svc', 'a')).toBe('one');
    expect(await store.get('svc', 'b')).toBe('two');
  });

  it('delete removes the entry', async () => {
    const store = await getTokenStore();
    await store.set('svc', 'acct', 'hello');
    await store.delete('svc', 'acct');
    expect(await store.get('svc', 'acct')).toBeNull();
  });

  it('persists across calls (re-reads file)', async () => {
    const first = await getTokenStore();
    await first.set('svc', 'acct', 'persisted');
    _resetTokenStoreForTesting();

    const second = await getTokenStore();
    expect(await second.get('svc', 'acct')).toBe('persisted');
  });

  it('writes file with 0600 mode', async () => {
    if (process.platform === 'win32') return; // Windows has no POSIX modes.

    const store = await getTokenStore();
    await store.set('svc', 'acct', 'whatever');
    const file = path.join(dir, 'finaegis-mcp', 'tokens.json');
    const stat = await fs.stat(file);
    expect(stat.mode & 0o777).toBe(0o600);
  });
});
