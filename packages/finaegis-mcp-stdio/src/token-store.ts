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

  // Default to the file store: keytar is not a dependency from 0.1.4 onward
  // because its native build broke `npx` installs on WSL2, Docker, Alpine,
  // and CI. Users who want the OS keychain can `npm i -g keytar` and set
  // FINAEGIS_MCP_TOKEN_STORE=keychain.
  const force = process.env.FINAEGIS_MCP_TOKEN_STORE;
  if (force === 'keychain') {
    cached = await loadKeychainStore();
    return cached;
  }
  cached = createFileStore();
  return cached;
}

interface KeytarLike {
  getPassword(service: string, account: string): Promise<string | null>;
  setPassword(service: string, account: string, value: string): Promise<void>;
  deletePassword(service: string, account: string): Promise<boolean>;
  findCredentials(service: string): Promise<{ account: string; password: string }[]>;
}

async function loadKeychainStore(): Promise<TokenStore> {
  // Dynamic import so a missing keytar package (we ship without it from
  // 0.1.4) or a missing libsecret / D-Bus session surfaces as a catchable
  // error here instead of crashing module load. Users who want the keychain
  // backend can `npm i -g keytar` and set FINAEGIS_MCP_TOKEN_STORE=keychain.
  let mod: { default?: KeytarLike } & KeytarLike;
  try {
    // keytar is intentionally not declared as a (optional)Dependency from
    // 0.1.4 onward — its native build hangs `npx` installs on common
    // environments. Users opt in by `npm i -g keytar`. The dynamic specifier
    // hides the import from TS resolution, which would otherwise fail in CI
    // where keytar isn't in node_modules at build time.
    const specifier = 'keytar';
    mod = (await import(specifier)) as { default?: KeytarLike } & KeytarLike;
  } catch (err) {
    if ((err as NodeJS.ErrnoException).code === 'ERR_MODULE_NOT_FOUND' || (err as NodeJS.ErrnoException).code === 'MODULE_NOT_FOUND') {
      throw new Error(
        '[@finaegis/mcp] keytar is not installed. Install it globally to use the OS keychain backend: `npm i -g keytar`. The default file store works on all platforms with no extra dependencies.',
      );
    }
    throw err;
  }
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
