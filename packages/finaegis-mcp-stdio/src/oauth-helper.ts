import { createServer, IncomingMessage, ServerResponse } from 'node:http';
import { randomBytes, createHash } from 'node:crypto';
import { fetch } from 'undici';
import open from 'open';
import { RelayConfig } from './config.js';
import { getTokenStore } from './token-store.js';
import { successPage, stateMismatchPage, missingCodePage } from './callback-pages.js';

interface TokenSet {
  access_token: string;
  refresh_token?: string;
  expires_at: number;
  scope: string;
}

interface ClientCreds {
  client_id: string;
  client_secret: string;
}

const REDIRECT_PORT = 53682;
// RFC 8252 §7.3: loopback redirects MUST use the literal IP, not "localhost".
// Our own DCR validator rejects localhost for the same reason.
const REDIRECT_HOST = '127.0.0.1';
const REDIRECT_URI = `http://${REDIRECT_HOST}:${REDIRECT_PORT}/callback`;
const CALLBACK_TIMEOUT_MS = 300_000;

const REQUESTED_SCOPES = [
  'accounts:read',
  'accounts:write',
  'payments:read',
  'payments:write',
  'transactions:read',
  'exchange:read',
  'exchange:write',
  'ramp:read',
  'ramp:write',
  'sms:send',
].join(' ');

interface TokenResponse {
  access_token: string;
  refresh_token?: string;
  expires_in: number;
  scope: string;
}

export class OAuthHelper {
  constructor(private cfg: RelayConfig) {}

  async getAccessToken(): Promise<string> {
    const stored = await this.readToken();
    if (stored && stored.expires_at > Date.now() + 30_000) {
      return stored.access_token;
    }
    if (stored?.refresh_token) {
      const refreshed = await this.refresh(stored.refresh_token);
      if (refreshed) {
        await this.writeToken(refreshed);
        return refreshed.access_token;
      }
    }
    const fresh = await this.interactiveFlow();
    await this.writeToken(fresh);
    return fresh.access_token;
  }

  async logout(): Promise<void> {
    const store = await getTokenStore();
    await store.delete(this.cfg.keychainService, this.cfg.keychainAccount);
    await store.delete(this.cfg.keychainService, this.cfg.keychainAccount + '.dcr');
  }

  private async readToken(): Promise<TokenSet | null> {
    const store = await getTokenStore();
    const raw = await store.get(this.cfg.keychainService, this.cfg.keychainAccount);
    if (!raw) return null;
    try {
      return JSON.parse(raw) as TokenSet;
    } catch {
      return null;
    }
  }

  private async writeToken(t: TokenSet): Promise<void> {
    const store = await getTokenStore();
    await store.set(this.cfg.keychainService, this.cfg.keychainAccount, JSON.stringify(t));
  }

  private async refresh(refreshToken: string): Promise<TokenSet | null> {
    const dcr = await this.dcrIfNeeded();
    const body = new URLSearchParams({
      grant_type:    'refresh_token',
      refresh_token: refreshToken,
      client_id:     dcr.client_id,
      client_secret: dcr.client_secret,
    });
    const res = await fetch(`${this.cfg.authServer}/oauth/token`, {
      method:  'POST',
      body,
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    });
    if (!res.ok) return null;
    const data = (await res.json()) as TokenResponse;
    return {
      access_token:  data.access_token,
      refresh_token: data.refresh_token ?? refreshToken,
      expires_at:    Date.now() + data.expires_in * 1000,
      scope:         data.scope,
    };
  }

  private async interactiveFlow(): Promise<TokenSet> {
    const dcr = await this.dcrIfNeeded();

    const verifier = randomBytes(32).toString('base64url');
    const challenge = createHash('sha256').update(verifier).digest('base64url');
    const state = randomBytes(16).toString('base64url');

    const authUrl = `${this.cfg.authServer}/oauth/authorize?` + new URLSearchParams({
      response_type:         'code',
      client_id:             dcr.client_id,
      redirect_uri:          REDIRECT_URI,
      scope:                 REQUESTED_SCOPES,
      state,
      code_challenge:        challenge,
      code_challenge_method: 'S256',
    }).toString();

    // Start the loopback server BEFORE opening the browser so we don't race
    // a fast-clicking user against the bind.
    const codePromise = this.waitForCode(state);

    // Always print the URL — on WSL2, Docker, and bare SSH sessions, open()
    // can return without throwing while no browser actually launches, leaving
    // the user staring at a silent terminal. Printing first makes the URL
    // available regardless of what the browser-open call does.
    process.stderr.write(`Open this URL to authorize:\n  ${authUrl}\n`);

    if (!this.cfg.oauthNoBrowser) {
      try {
        await open(authUrl);
      } catch {
        // Browser open failed — the URL is already printed above.
      }
    }

    const code = await codePromise;
    const tokenBody = new URLSearchParams({
      grant_type:    'authorization_code',
      code,
      redirect_uri:  REDIRECT_URI,
      client_id:     dcr.client_id,
      client_secret: dcr.client_secret,
      code_verifier: verifier,
    });
    const tokenRes = await fetch(`${this.cfg.authServer}/oauth/token`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body:    tokenBody,
    });
    if (!tokenRes.ok) {
      throw new Error(`token exchange failed: ${tokenRes.status} ${await tokenRes.text()}`);
    }
    const data = (await tokenRes.json()) as TokenResponse;

    return {
      access_token:  data.access_token,
      refresh_token: data.refresh_token,
      expires_at:    Date.now() + data.expires_in * 1000,
      scope:         data.scope,
    };
  }

  private waitForCode(expectedState: string): Promise<string> {
    return new Promise<string>((resolve, reject) => {
      const server = createServer((req: IncomingMessage, res: ServerResponse) => {
        // Only GET on /callback. Anything else is either a probe or an
        // attacker trying to fish at the loopback port.
        if (req.method !== 'GET') {
          res.writeHead(405).end();
          return;
        }
        const url = new URL(req.url ?? '/', `http://${REDIRECT_HOST}:${REDIRECT_PORT}`);
        if (url.pathname !== '/callback') {
          res.writeHead(404).end();
          return;
        }
        const got = url.searchParams.get('state');
        if (got !== expectedState) {
          res.writeHead(400, { 'Content-Type': 'text/html; charset=utf-8' });
          res.end(stateMismatchPage());
          cleanup();
          reject(new Error('state mismatch'));
          return;
        }
        const c = url.searchParams.get('code');
        if (!c) {
          res.writeHead(400, { 'Content-Type': 'text/html; charset=utf-8' });
          res.end(missingCodePage());
          cleanup();
          reject(new Error('authorization response missing code'));
          return;
        }
        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(successPage());
        cleanup();
        resolve(c);
      });

      const timer = setTimeout(() => {
        cleanup();
        reject(new Error('timed out waiting for browser callback'));
      }, CALLBACK_TIMEOUT_MS);

      const cleanup = (): void => {
        clearTimeout(timer);
        server.close();
      };

      server.on('error', (err) => {
        cleanup();
        reject(err);
      });

      // Bind to 127.0.0.1 explicitly. Listening on 0.0.0.0 would expose the
      // auth code endpoint to the LAN, which is the threat RFC 8252 closes.
      server.listen(REDIRECT_PORT, REDIRECT_HOST);
    });
  }

  /**
   * Lazily DCR-register on first run; persist client_id+secret to keychain
   * under a separate account.
   */
  private async dcrIfNeeded(): Promise<ClientCreds> {
    const store = await getTokenStore();
    const stored = await store.get(this.cfg.keychainService, this.cfg.keychainAccount + '.dcr');
    if (stored) {
      try {
        return JSON.parse(stored) as ClientCreds;
      } catch {
        // Corrupted entry — fall through and re-register.
      }
    }

    const res = await fetch(`${this.cfg.authServer}/oauth/register`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      // RFC 7591 client metadata. client_name is what the user sees on the
      // consent screen — kept generic so the AS's brand-impersonation
      // blocklist accepts us. Brand identity is conveyed through client_uri,
      // which the consent template can render as a verification link.
      body:    JSON.stringify({
        client_name:   'MCP Stdio Relay',
        client_uri:    'https://www.npmjs.com/package/@finaegis/mcp',
        redirect_uris: [REDIRECT_URI],
        grant_types:   ['authorization_code', 'refresh_token'],
      }),
    });
    if (!res.ok) {
      throw new Error(`DCR failed: ${res.status} ${await res.text()}`);
    }
    const data = (await res.json()) as ClientCreds;
    await store.set(this.cfg.keychainService, this.cfg.keychainAccount + '.dcr', JSON.stringify(data));
    return data;
  }
}
