import { fetch } from 'undici';
import { RelayConfig } from './config.js';

interface JsonRpcMessage {
  jsonrpc: '2.0';
  id?: number | string | null;
  method?: string;
  params?: unknown;
  result?: unknown;
  error?: unknown;
}

/**
 * Bridges a stdio MCP client (Claude Desktop, Cursor, Continue.dev) to the
 * remote streamable-HTTP MCP server at https://mcp.zelta.app/mcp.
 *
 * Each newline-delimited JSON-RPC envelope arriving on stdin is forwarded as
 * a POST with the cached bearer token; the server's JSON response is written
 * back to stdout, also newline-delimited.
 *
 * We deliberately request `Accept: application/json` (no SSE) — the stdio
 * client can only consume JSON-RPC envelopes, and an SSE response stream
 * would corrupt the protocol mid-flight. The remote server falls back to
 * application/json when SSE isn't accepted.
 *
 * On transport failure (DNS, TLS, network), we synthesize a JSON-RPC error
 * envelope so the client unblocks instead of hanging on the request id.
 */
export class StdioRelay {
  constructor(
    private cfg: RelayConfig,
    private getToken: () => Promise<string>,
  ) {}

  async start(): Promise<void> {
    process.stdin.setEncoding('utf-8');

    let buffer = '';
    process.stdin.on('data', (chunk) => {
      buffer += chunk;
      const lines = buffer.split('\n');
      buffer = lines.pop() ?? '';
      for (const line of lines) {
        if (line.trim() === '') continue;
        this.handleLine(line).catch((err) => {
          process.stderr.write(`relay error: ${String(err)}\n`);
        });
      }
    });

    process.stdin.on('end', () => process.exit(0));
  }

  private async handleLine(line: string): Promise<void> {
    let envelope: JsonRpcMessage;
    try {
      envelope = JSON.parse(line);
    } catch {
      // Not valid JSON-RPC — drop silently. The client framed something we
      // can't parse; logging would just spam stderr on partial reads.
      return;
    }

    let token: string;
    try {
      token = await this.getToken();
    } catch (err) {
      this.writeTransportError(envelope, `auth-token unavailable: ${String(err)}`);
      return;
    }

    let responseText: string;
    let status: number;
    try {
      const response = await fetch(this.cfg.serverUrl, {
        method:  'POST',
        headers: {
          'Content-Type':  'application/json',
          'Accept':        'application/json',
          'Authorization': `Bearer ${token}`,
        },
        body: JSON.stringify(envelope),
      });
      status = response.status;
      responseText = await response.text();
    } catch (err) {
      this.writeTransportError(envelope, `transport: ${String(err)}`);
      return;
    }

    // 202 / empty body = notification ack; nothing to forward.
    if (status === 202 || responseText.length === 0) {
      return;
    }

    process.stdout.write(responseText + '\n');
  }

  private writeTransportError(envelope: JsonRpcMessage, detail: string): void {
    // Notifications (no id) get no response per JSON-RPC 2.0 §4.1, so a
    // failed POST for a notification can only be reported on stderr.
    if (envelope.id === undefined || envelope.id === null) {
      process.stderr.write(`${detail}\n`);
      return;
    }

    const errorEnvelope = {
      jsonrpc: '2.0',
      id:      envelope.id,
      error:   {
        code:    -32099,
        message: 'TRANSPORT_ERROR',
        data:    { detail },
      },
    };
    process.stdout.write(JSON.stringify(errorEnvelope) + '\n');
  }
}
