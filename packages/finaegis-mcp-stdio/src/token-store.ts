import { promises as fs } from 'node:fs';
import { homedir } from 'node:os';
import path from 'node:path';

// Mirrors the keytar surface we actually use, so the rest of the wrapper
// doesn't have to know which backend is in play.
export interface TokenStore {
  get(service: string, account: string): Promise<string | null>;
  set(service: string, account: string, value: string): Promise<void>;
  delete(service: string, account: string): Promise<void>;
  readonly kind: 'keychain' | 'file';
}

let cached: TokenStore | undefined;

export async function getTokenStore(): Promise<TokenStore> {
  if (cached) return cached;

  const force = process.env.FINAEGIS_MCP_TOKEN_STORE;
  if (force === 'file') {
    cached = createFileStore();
    return cached;
  }
  if (force === 'keychain') {
    cached = await tryKeychainOrThrow();
    return cached;
  }

  // Default: prefer the OS keychain, fall back to a file if it's not viable
  // (libsecret missing on WSL2 / Alpine / Docker, no D-Bus session, etc.).
  const keychain = await tryKeychain();
  if (keychain) {
    cached = keychain;
    return cached;
  }

  const fallback = createFileStore();
  process.stderr.write(
    `[@finaegis/mcp] OS keychain unavailable; falling back to file store at ${fallback.path}.\n` +
      `[@finaegis/mcp] Set FINAEGIS_MCP_TOKEN_STORE=keychain to require the keychain (will error if missing).\n`,
  );
  cached = fallback;
  return cached;
}

async function tryKeychain(): Promise<TokenStore | null> {
  try {
    return await loadKeychainStore();
  } catch {
    return null;
  }
}

async function tryKeychainOrThrow(): Promise<TokenStore> {
  return loadKeychainStore();
}

interface KeytarLike {
  getPassword(service: string, account: string): Promise<string | null>;
  setPassword(service: string, account: string, value: string): Promise<void>;
  deletePassword(service: string, account: string): Promise<boolean>;
  findCredentials(service: string): Promise<{ account: string; password: string }[]>;
}

async function loadKeychainStore(): Promise<TokenStore> {
  // Dynamic import so a missing libsecret / D-Bus session surfaces as a
  // catchable error here instead of crashing module load.
  const mod = (await import('keytar')) as { default?: KeytarLike } & KeytarLike;
  const keytar: KeytarLike = mod.default ?? mod;
  // Round-trip a no-op call so libsecret/D-Bus problems surface here, not on
  // the first real getPassword later.
  await keytar.findCredentials('finaegis-mcp.healthcheck');
  return {
    kind: 'keychain',
    get: (service, account) => keytar.getPassword(service, account),
    set: (service, account, value) => keytar.setPassword(service, account, value),
    delete: async (service, account) => {
      await keytar.deletePassword(service, account);
    },
  };
}

interface FileStore extends TokenStore {
  readonly path: string;
}

function createFileStore(): FileStore {
  const baseDir = configBaseDir();
  const dir = path.join(baseDir, 'finaegis-mcp');
  const file = path.join(dir, 'tokens.json');

  const read = async (): Promise<Record<string, string>> => {
    try {
      const raw = await fs.readFile(file, 'utf8');
      return JSON.parse(raw) as Record<string, string>;
    } catch (err) {
      if ((err as NodeJS.ErrnoException).code === 'ENOENT') return {};
      throw err;
    }
  };

  const write = async (data: Record<string, string>): Promise<void> => {
    await fs.mkdir(dir, { recursive: true, mode: 0o700 });
    const tmp = `${file}.tmp`;
    await fs.writeFile(tmp, JSON.stringify(data, null, 2), { mode: 0o600 });
    await fs.rename(tmp, file);
  };

  const key = (service: string, account: string): string => `${service}::${account}`;

  return {
    kind: 'file',
    path: file,
    async get(service, account) {
      const data = await read();
      return data[key(service, account)] ?? null;
    },
    async set(service, account, value) {
      const data = await read();
      data[key(service, account)] = value;
      await write(data);
    },
    async delete(service, account) {
      const data = await read();
      delete data[key(service, account)];
      await write(data);
    },
  };
}

function configBaseDir(): string {
  if (process.env.XDG_CONFIG_HOME) return process.env.XDG_CONFIG_HOME;
  if (process.platform === 'win32' && process.env.APPDATA) return process.env.APPDATA;
  return path.join(homedir(), '.config');
}

// Test hook so config tests can reset between cases.
export function _resetTokenStoreForTesting(): void {
  cached = undefined;
}
