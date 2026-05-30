<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        $brand   = config('brand.name');
        $mcpHost = (string) config('mcp.host', 'mcp.zelta.app');
        $mcpUrl  = 'https://' . $mcpHost . '/mcp';
    @endphp

    <title>Connect to your AI — {{ $brand }}</title>

    @include('partials.favicon')

    @include('partials.seo', [
        'title'       => 'Connect ' . $brand . ' to your AI',
        'description' => 'Connect Claude Desktop, Claude.ai, Cursor, or Continue.dev to your ' . $brand . ' wallet through one OAuth-protected MCP endpoint. Set a daily spending cap, pick scopes, and go.',
        'keywords'    => $brand . ', MCP, Claude, Cursor, AI wallet, agentic payments',
        'robots'      => 'noindex',
    ])

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,600,700&dm-sans:400,500,600,700&jetbrains-mono:400,500,700&display=swap" rel="stylesheet" />

    <link rel="stylesheet" href="/css/app-landing.css">

    <style>
        html { scroll-behavior: smooth; }
        body {
            background: #faf9f6;
            color: #0a0a0a;
            font-family: 'Space Grotesk', system-ui, sans-serif;
            min-height: 100vh;
        }
        ::selection { background: #ccff00; color: #000; }

        .bg-z-purple { background-color: #c8a8f0; }
        .bg-acid { background-color: #ccff00; }
        .bg-mint { background-color: #a8f0c4; }
        .bg-obsidian { background-color: #0a0a0a; }
        .bg-bg-tertiary { background-color: #f5f5f5; }
        .text-text-sec { color: #404040; }
        .text-text-muted { color: #737373; }
        .text-z-purple { color: #c8a8f0; }
        .text-z-green { color: #16a34a; }
        .text-acid { color: #ccff00; }

        .bru-border { border: 3px solid #0a0a0a; }
        .bru-card { border: 3px solid #0a0a0a; box-shadow: 6px 6px 0px #0a0a0a; }
        .bru-card-sm { border: 3px solid #0a0a0a; box-shadow: 3px 3px 0px #0a0a0a; }
        .bru-card-lg { border: 3px solid #0a0a0a; box-shadow: 8px 8px 0px #0a0a0a; }

        .btn-hover { transition: transform 0.15s ease; }
        .btn-hover:hover { transform: scale(1.06); }
        .btn-hover:active { transform: scale(0.95); }
        .btn-hover:focus-visible { outline: 3px solid #7000ff; outline-offset: 2px; }

        button:focus-visible, a:focus-visible { outline: 3px solid #7000ff; outline-offset: 2px; }

        .font-heading { font-family: 'Space Grotesk', system-ui, sans-serif; letter-spacing: -0.04em; }
        .font-mono { font-family: 'JetBrains Mono', ui-monospace, monospace; }

        /* Tabs */
        .client-tab[aria-selected="true"] {
            background: #0a0a0a;
            color: #fff;
        }
        .client-panel { display: none; }
        .client-panel.active { display: block; }

        /* Copy button */
        .copy-btn-feedback {
            position: absolute;
            top: 0.75rem;
            right: 0.75rem;
            padding: 0.3rem 0.6rem;
            background: #16a34a;
            color: #fff;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 600;
            opacity: 0;
            transform: translateY(-6px);
            transition: opacity 0.2s ease, transform 0.2s ease;
            pointer-events: none;
        }
        .copy-btn-feedback.shown {
            opacity: 1;
            transform: translateY(0);
        }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                transition-duration: 0.01ms !important;
            }
            html { scroll-behavior: auto; }
        }
    </style>
</head>
<body class="antialiased">

    {{-- Top bar --}}
    <header class="px-5 py-5 md:py-6">
        <div class="mx-auto max-w-5xl flex items-center justify-between">
            <a href="{{ url('/') }}" class="flex items-center gap-3 btn-hover">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl text-xl font-black bg-mint bru-border" style="font-family: 'Space Grotesk', sans-serif; letter-spacing: -0.04em;">
                    Z
                </div>
                <span class="text-2xl font-black font-heading">{{ $brand }}</span>
            </a>
            <a href="{{ url('/') }}" class="text-sm font-semibold text-text-sec hover:text-black">← Home</a>
        </div>
    </header>

    <main class="px-5 pb-24">
        <div class="mx-auto max-w-3xl">

            {{-- Hero --}}
            <div class="text-center mb-12 mt-4">
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-acid bru-border text-sm font-bold mb-6" style="transform: rotate(-1deg);">
                    <span class="w-2 h-2 rounded-full bg-obsidian"></span>
                    AGENTIC PAYMENTS
                </div>
                <h1 class="text-4xl sm:text-5xl md:text-6xl font-black mb-5 font-heading" style="line-height: 0.95;">
                    Connect <span class="text-z-purple">Claude</span> to your wallet
                </h1>
                <p class="text-lg md:text-xl max-w-xl mx-auto text-text-sec font-medium">
                    Three steps. About four minutes. Free.
                </p>
            </div>

            {{-- Step 1: Sign in --}}
            <section class="mb-8 bg-white bru-card-lg rounded-3xl p-6 md:p-8 relative">
                <div class="absolute -top-5 left-6 w-14 h-14 rounded-2xl bg-obsidian text-white font-black flex items-center justify-center text-3xl bru-border" style="transform: rotate(-4deg);">
                    1
                </div>
                <div class="mt-6">
                    <h2 class="font-black font-heading text-2xl md:text-3xl mb-3">Sign in to {{ $brand }}</h2>
                    <p class="text-base font-medium text-text-sec mb-6">
                        Email-only, passwordless. If you don't have an account yet, signing in creates one.
                    </p>
                    <a href="{{ route('login') }}" class="btn-hover inline-flex items-center gap-2 rounded-full px-7 py-3 text-base font-black bg-acid text-obsidian bru-border">
                        Sign in →
                    </a>
                </div>
            </section>

            {{-- Step 2: Add to AI --}}
            <section class="mb-8 bg-bg-tertiary bru-card-lg rounded-3xl p-6 md:p-8 relative">
                <div class="absolute -top-5 left-6 w-14 h-14 rounded-2xl bg-z-purple text-obsidian font-black flex items-center justify-center text-3xl bru-border" style="transform: rotate(4deg);">
                    2
                </div>
                <div class="mt-6">
                    <h2 class="font-black font-heading text-2xl md:text-3xl mb-3">Add the connector to your AI</h2>
                    <p class="text-base font-medium text-text-sec mb-6">
                        Pick the AI you use:
                    </p>

                    {{-- Client picker tabs --}}
                    <div role="tablist" aria-label="Pick your AI client" class="flex flex-wrap gap-2 mb-5">
                        @foreach (['claude-desktop' => 'Claude Desktop', 'claude-ai' => 'Claude.ai', 'cursor' => 'Cursor', 'continue' => 'Continue.dev', 'stdio' => 'Other (stdio)'] as $key => $label)
                            <button
                                type="button"
                                role="tab"
                                id="tab-{{ $key }}"
                                aria-controls="panel-{{ $key }}"
                                aria-selected="{{ $key === 'claude-desktop' ? 'true' : 'false' }}"
                                data-client="{{ $key }}"
                                class="client-tab btn-hover px-4 py-2 rounded-full text-sm font-bold bru-border bg-white"
                            >{{ $label }}</button>
                        @endforeach
                    </div>

                    {{-- Panels --}}
                    @php
                        $panels = [
                            'claude-desktop' => [
                                'instructions' => 'In Claude Desktop, edit the file at <code class="font-mono text-xs bg-white px-1.5 py-0.5 rounded bru-border">~/Library/Application Support/Claude/claude_desktop_config.json</code> (or the equivalent on your OS). Restart Claude Desktop after saving.',
                                'snippet' => '{
  "mcpServers": {
    "' . strtolower($brand) . '": {
      "url": "' . $mcpUrl . '"
    }
  }
}',
                            ],
                            'claude-ai' => [
                                'instructions' => 'In Claude.ai, open Settings → Connectors → Add a custom connector. Paste the URL.',
                                'snippet' => $mcpUrl,
                            ],
                            'cursor' => [
                                'instructions' => 'In Cursor, open Settings → Features → MCP → Add Server. Paste the URL.',
                                'snippet' => $mcpUrl,
                            ],
                            'continue' => [
                                'instructions' => 'Add this block under <code class="font-mono text-xs bg-white px-1.5 py-0.5 rounded bru-border">experimental.modelContextProtocolServer</code> in your <code class="font-mono text-xs bg-white px-1.5 py-0.5 rounded bru-border">config.json</code>.',
                                'snippet' => '{
  "url": "' . $mcpUrl . '"
}',
                            ],
                            'stdio' => [
                                'instructions' => 'For older clients without remote streamable-HTTP support, use the npm wrapper. Token persists in your OS keychain.',
                                'snippet' => 'npx -y @finaegis/mcp',
                            ],
                        ];
                    @endphp
                    @foreach ($panels as $key => $panel)
                        <div
                            role="tabpanel"
                            id="panel-{{ $key }}"
                            aria-labelledby="tab-{{ $key }}"
                            class="client-panel {{ $key === 'claude-desktop' ? 'active' : '' }}"
                        >
                            <p class="text-sm text-text-sec mb-3">{!! $panel['instructions'] !!}</p>
                            <div class="relative">
                                <pre class="bg-obsidian text-acid font-mono text-sm rounded-2xl p-4 pr-20 bru-border overflow-x-auto whitespace-pre">{{ $panel['snippet'] }}</pre>
                                <button
                                    type="button"
                                    onclick="copyToClipboard(this, {{ Js::from($panel['snippet']) }})"
                                    class="absolute top-3 right-3 px-3 py-1.5 rounded-lg bg-white text-obsidian text-xs font-bold bru-border btn-hover"
                                    aria-label="Copy snippet"
                                >Copy</button>
                                <span class="copy-btn-feedback" role="status">Copied</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            {{-- Step 3: Authorize --}}
            <section class="mb-8 bg-acid bru-card-lg rounded-3xl p-6 md:p-8 relative">
                <div class="absolute -top-5 left-6 w-14 h-14 rounded-2xl bg-white text-obsidian font-black flex items-center justify-center text-3xl bru-border" style="transform: rotate(-3deg);">
                    3
                </div>
                <div class="mt-6">
                    <h2 class="font-black font-heading text-2xl md:text-3xl mb-3">Authorize</h2>
                    <p class="text-base font-medium text-obsidian mb-4">
                        First time you use the connector in your AI client, it opens a browser for OAuth. Pick the scopes you want, set a daily spending cap, approve. The token persists per client — subsequent launches are silent.
                    </p>
                    <p class="text-sm font-bold text-obsidian inline-flex items-center gap-2">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        DONE — TRY: <em class="not-italic ml-1">"check my balance"</em>
                    </p>
                </div>
            </section>

            {{-- Reassurance --}}
            <div class="mt-12 grid grid-cols-1 sm:grid-cols-3 gap-4">
                @foreach ([
                    ['title' => 'You approve scopes', 'desc' => '12 read/write scopes. Pick what your AI gets.'],
                    ['title' => 'Daily spending cap', 'desc' => 'Set on consent. Server-enforced.'],
                    ['title' => 'Revoke any time', 'desc' => 'Tokens listed in your account settings.'],
                ] as $item)
                    <div class="bg-white bru-card rounded-2xl p-5">
                        <div class="flex items-center gap-2 mb-2">
                            <svg class="w-5 h-5 text-z-green" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                            <h3 class="font-black font-heading text-base">{{ $item['title'] }}</h3>
                        </div>
                        <p class="text-sm text-text-sec font-medium">{{ $item['desc'] }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Footer --}}
            <p class="text-center text-sm text-text-muted mt-12">
                Want the technical details? <a href="{{ config('brand.docs_url', 'https://finaegis.org/developers/mcp-tools') }}" target="_blank" rel="noopener" class="font-semibold text-obsidian underline hover:no-underline">Developer reference →</a>
            </p>

        </div>
    </main>

    <script>
        // Tab switching
        document.querySelectorAll('.client-tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                var key = tab.dataset.client;
                document.querySelectorAll('.client-tab').forEach(function (t) {
                    t.setAttribute('aria-selected', t === tab ? 'true' : 'false');
                });
                document.querySelectorAll('.client-panel').forEach(function (panel) {
                    panel.classList.toggle('active', panel.id === 'panel-' + key);
                });
            });
        });

        // Copy snippet to clipboard
        function copyToClipboard(button, text) {
            var feedback = button.parentElement.querySelector('.copy-btn-feedback');
            var done = function () {
                if (feedback) {
                    feedback.classList.add('shown');
                    setTimeout(function () { feedback.classList.remove('shown'); }, 1400);
                }
            };
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done).catch(function () {
                    fallbackCopy(text, done);
                });
            } else {
                fallbackCopy(text, done);
            }
        }

        function fallbackCopy(text, done) {
            var ta = document.createElement('textarea');
            ta.value = text;
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); done(); } catch (e) { /* noop */ }
            document.body.removeChild(ta);
        }
    </script>

</body>
</html>
