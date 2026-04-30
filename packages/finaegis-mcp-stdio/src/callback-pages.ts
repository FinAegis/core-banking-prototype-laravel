// Inline HTML responses served by the wrapper's loopback HTTP server.
// Kept self-contained so the wrapper has zero runtime asset dependencies.
//
// Visual style mirrors zelta.app: dark navy background, off-white text,
// emerald accent for success, amber for errors. The page auto-closes the
// browser tab on success after a short delay (most modern browsers will
// silently refuse window.close() when the tab wasn't opened by script,
// so we keep a manual "close window" CTA as the primary action).

const baseStyles = `
  <style>
    :root { color-scheme: dark; }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #0b0f17;
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
      color: #e2e8f0;
      padding: 24px;
    }
    .card {
      max-width: 460px;
      width: 100%;
      background: #111828;
      border: 1px solid rgba(255,255,255,0.06);
      border-radius: 16px;
      padding: 40px 32px;
      text-align: center;
      box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5);
    }
    .logo {
      font-size: 14px;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: #94a3b8;
      margin-bottom: 28px;
    }
    .icon {
      width: 64px;
      height: 64px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 24px;
      font-size: 32px;
    }
    .icon-success { background: rgba(16,185,129,0.12); color: #10b981; }
    .icon-error { background: rgba(245,158,11,0.12); color: #f59e0b; }
    h1 {
      font-size: 22px;
      font-weight: 700;
      margin: 0 0 12px;
      color: #f8fafc;
    }
    p {
      font-size: 15px;
      line-height: 1.6;
      color: #94a3b8;
      margin: 0 0 24px;
    }
    .hint {
      font-size: 13px;
      color: #64748b;
      margin-top: 8px;
    }
    code {
      background: rgba(255,255,255,0.04);
      padding: 2px 6px;
      border-radius: 4px;
      font-family: 'SF Mono', Monaco, Menlo, monospace;
      font-size: 12px;
      color: #cbd5e1;
    }
  </style>
`;

const layout = (innerHtml: string): string =>
  `<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Zelta MCP</title>${baseStyles}</head><body><div class="card"><div class="logo">Zelta MCP</div>${innerHtml}</div><script>setTimeout(function(){try{window.close()}catch(e){}},2500);</script></body></html>`;

export const successPage = (): string =>
  layout(`
    <div class="icon icon-success">✓</div>
    <h1>You're connected</h1>
    <p>Your MCP client is now authorized to access your Zelta account. You can close this window and return to your terminal.</p>
    <div class="hint">Tokens are stored locally in <code>~/.config/finaegis-mcp/tokens.json</code></div>
  `);

export const stateMismatchPage = (): string =>
  layout(`
    <div class="icon icon-error">!</div>
    <h1>Authorization failed</h1>
    <p>The state parameter didn't match — this usually means the request was tampered with or restarted in a different terminal. Close this window and run <code>--login</code> again.</p>
  `);

export const missingCodePage = (): string =>
  layout(`
    <div class="icon icon-error">!</div>
    <h1>Authorization incomplete</h1>
    <p>The redirect didn't carry an authorization code. Close this window and run <code>--login</code> again.</p>
  `);
