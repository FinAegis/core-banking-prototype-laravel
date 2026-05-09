<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    @php
        // Brand decision: header/nav/footer stay "Zelta" (matching the rest of zelta.app),
        // but the investor-facing body copy says "FinAegis" because investors invest
        // in the legal entity. This is a deliberate, documented exception.
        $brand = config('brand.name', 'Zelta');
    @endphp

    <title>FinAegis. Two ways to invest. — {{ $brand }}</title>

    @include('partials.favicon')

    {{-- TODO: remove noindex when ready to launch publicly --}}
    <meta name="robots" content="noindex,nofollow">

    {{-- Open Graph / Twitter --}}
    {{-- TODO: founder to add 1200x630 OG card image at public/images/og/invest-card.png before launch. --}}
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url('/invest') }}">
    <meta property="og:title" content="FinAegis. Two ways to invest.">
    <meta property="og:description" content="Regulated EU banking, or non-custodial software. Founder: ex-CEO of Paysera.">
    <meta property="og:image" content="{{ asset('images/og/invest-card.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:site_name" content="{{ $brand }}">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="FinAegis. Two ways to invest.">
    <meta name="twitter:description" content="Regulated EU banking, or non-custodial software. Founder: ex-CEO of Paysera.">
    <meta name="twitter:image" content="{{ asset('images/og/invest-card.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,600,700&dm-sans:400,500,600,700&jetbrains-mono:400,500,700&display=swap" rel="stylesheet" />

    {{-- Pre-compiled Tailwind utility CSS used by the Zelta brutalist pages. --}}
    <link rel="stylesheet" href="/css/app-landing.css">

    <style>
        html { scroll-behavior: smooth; }
        body {
            background: #faf9f6;
            color: #0a0a0a;
            font-family: 'Space Grotesk', system-ui, sans-serif;
            min-height: 100vh;
            overflow-x: hidden;
        }
        ::selection { background: #ccff00; color: #000; }

        /* Brutalist palette + utilities (kept in-file so the page renders even if app-landing.css is missing) */
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
        .btn-hover:hover { transform: scale(1.04); }
        .btn-hover:active { transform: scale(0.96); }
        .btn-hover:focus-visible { outline: 3px solid #7000ff; outline-offset: 2px; }
        button:focus-visible, a:focus-visible, input:focus-visible, select:focus-visible, textarea:focus-visible {
            outline: 3px solid #7000ff; outline-offset: 2px;
        }

        .font-heading { font-family: 'Space Grotesk', system-ui, sans-serif; letter-spacing: -0.04em; }
        .font-mono { font-family: 'JetBrains Mono', ui-monospace, monospace; }

        /* Comparison table — desktop */
        .compare-table { border-collapse: separate; border-spacing: 0; width: 100%; }
        .compare-table th, .compare-table td {
            padding: 0.85rem 1rem;
            border-bottom: 3px solid #0a0a0a;
            border-right: 3px solid #0a0a0a;
            font-size: 0.875rem;
            vertical-align: top;
            text-align: left;
        }
        .compare-table tr > *:first-child { border-left: 3px solid #0a0a0a; }
        .compare-table tr:first-child > * { border-top: 3px solid #0a0a0a; }
        .compare-table thead th { font-weight: 800; background: #f5f5f5; font-size: 0.95rem; }
        .compare-table thead th:nth-child(2) { background: #ccff00; }
        .compare-table thead th:nth-child(3) { background: #c8a8f0; }
        .compare-table tbody th { font-weight: 700; background: #fff; }

        /* Hide desktop comparison table on phone, show stacked cards instead */
        .compare-stack { display: none; }
        @media (max-width: 767px) {
            .compare-table-wrap { display: none; }
            .compare-stack { display: block; }
        }

        /* Stacked use-of-funds bar */
        .uof-bar {
            display: flex;
            border: 3px solid #0a0a0a;
            box-shadow: 6px 6px 0 #0a0a0a;
            background: #fff;
            overflow: hidden;
        }
        .uof-bar > div {
            padding: 1rem 0.75rem;
            font-size: 0.8rem;
            font-weight: 700;
            border-right: 3px solid #0a0a0a;
            min-width: 0;
        }
        .uof-bar > div:last-child { border-right: 0; }
        @media (max-width: 767px) {
            .uof-bar { flex-direction: column; }
            .uof-bar > div { border-right: 0; border-bottom: 3px solid #0a0a0a; }
            .uof-bar > div:last-child { border-bottom: 0; }
        }

        /* Form polish */
        .field-label { display: block; font-weight: 700; font-size: 0.875rem; margin-bottom: 0.4rem; }
        .field-input,
        .field-select,
        .field-textarea {
            width: 100%;
            padding: 0.7rem 0.85rem;
            border: 3px solid #0a0a0a;
            background: #fff;
            font-size: 1rem;
            font-family: inherit;
            border-radius: 0;
        }
        .field-textarea { resize: vertical; min-height: 110px; }
        .field-error { color: #b91c1c; font-size: 0.8rem; margin-top: 0.35rem; font-weight: 600; }

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

    {{-- Skip link --}}
    <a href="#main" class="sr-only focus:not-sr-only" style="position:absolute;left:1rem;top:1rem;padding:0.5rem 1rem;background:#ccff00;border:3px solid #000;font-weight:700;z-index:50;">Skip to content</a>

    {{-- Top bar: Zelta brand stays here (header/nav/footer never say FinAegis). --}}
    <header class="px-5 py-5 md:py-6">
        <div class="mx-auto max-w-6xl flex items-center justify-between">
            <a href="{{ url('/') }}" class="flex items-center gap-3 btn-hover">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl text-xl font-black bg-mint bru-border" style="font-family: 'Space Grotesk', sans-serif; letter-spacing: -0.04em;">
                    Z
                </div>
                <span class="text-2xl font-black font-heading">{{ $brand }}</span>
            </a>
            <a href="{{ url('/') }}" class="text-sm font-semibold text-text-sec hover:text-black">&larr; Home</a>
        </div>
    </header>

    <main id="main" class="px-5 pb-24">
        <div class="mx-auto max-w-6xl">

            {{-- ═══════════════════════════════════════
                 HERO
                 ═══════════════════════════════════════ --}}
            <section class="text-center mb-16 mt-4">
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-acid bru-border text-sm font-bold mb-6" style="transform: rotate(-1deg);">
                    <span class="w-2 h-2 rounded-full bg-obsidian"></span>
                    INVESTOR PAGE &middot; FINAEGIS LTD.
                </div>
                <h1 class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-black mb-6 font-heading max-w-5xl mx-auto" style="line-height: 0.95;">
                    FinAegis. <span class="block sm:inline">Two ways to invest.</span> <span class="block">One <span class="bg-z-purple px-2 inline-block" style="box-decoration-break: clone; -webkit-box-decoration-break: clone;">operator</span> who&rsquo;s done this before.</span>
                </h1>
                <p class="text-lg md:text-xl max-w-2xl mx-auto text-text-sec font-medium mb-8">
                    Choose the path that matches your thesis: regulated EU banking infrastructure, or non-custodial software margins without a licence.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center mb-6">
                    <a href="#licensed" class="btn-hover inline-flex items-center justify-center gap-2 px-6 py-3 text-base font-black bg-acid text-obsidian bru-border">
                        MiCA path <span class="font-mono">&rarr;&nbsp;&euro;1.0M</span>&nbsp;raise
                    </a>
                    <a href="#non-custodial" class="btn-hover inline-flex items-center justify-center gap-2 px-6 py-3 text-base font-black bg-z-purple text-obsidian bru-border">
                        Non-custodial path <span class="font-mono">&rarr;&nbsp;&euro;300&ndash;500k</span>&nbsp;raise
                    </a>
                </div>
                <p class="text-sm text-text-sec max-w-2xl mx-auto font-medium">
                    Founded by the former CEO of Paysera (1M+ users scaled, MLRO/CFO/legal in-house). Production codebase live.
                </p>
            </section>

            {{-- ═══════════════════════════════════════
                 TWO-PATHS COMPARISON TABLE
                 ═══════════════════════════════════════ --}}
            <section class="mb-16">
                <h2 class="text-3xl md:text-4xl font-black font-heading mb-6 text-center">Side by side.</h2>
                <p class="text-center text-text-sec font-medium max-w-2xl mx-auto mb-10">
                    Two paths, one company. Same engineering team, same founder, very different capital and regulatory profiles.
                </p>

                {{-- Desktop / tablet table --}}
                <div class="compare-table-wrap overflow-x-auto">
                    <table class="compare-table bru-card" style="background:#fff;">
                        <thead>
                            <tr>
                                <th>Dimension</th>
                                <th>Path A &mdash; Licensed</th>
                                <th>Path B &mdash; Non-custodial</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <th>Capital ask</th>
                                <td><span class="font-mono font-bold">&euro;1.0M</span> (founder-discounted from <span class="font-mono">&euro;1.5&ndash;2M</span>)</td>
                                <td><span class="font-mono font-bold">&euro;300&ndash;500k</span></td>
                            </tr>
                            <tr>
                                <th>Use of capital</th>
                                <td><span class="font-mono">&euro;250k</span> locked regulatory capital + <span class="font-mono">&euro;160k</span> setup + <span class="font-mono">&euro;450k</span> runway + <span class="font-mono">&euro;140k</span> buffer</td>
                                <td>Engineering + GTM + 12&nbsp;mo runway</td>
                            </tr>
                            <tr>
                                <th>Regulatory burden</th>
                                <td>MiCA Class&nbsp;2 + EMI Lithuania</td>
                                <td>None (software vendor)</td>
                            </tr>
                            <tr>
                                <th>Time to revenue</th>
                                <td><span class="font-mono">M3</span> (post-licence-grant)</td>
                                <td><span class="font-mono">M0</span> (already shipping)</td>
                            </tr>
                            <tr>
                                <th>Monthly break-even</th>
                                <td><span class="font-mono">~M17</span> / <span class="font-mono">~16k</span> active users</td>
                                <td><span class="font-mono">~M22</span> / <span class="font-mono">~33k</span> active users</td>
                            </tr>
                            <tr>
                                <th>Cumulative cash break-even</th>
                                <td><span class="font-mono">~M30</span> / <span class="font-mono">~30k</span> users</td>
                                <td><span class="font-mono">~M28</span> / <span class="font-mono">~28k</span> users</td>
                            </tr>
                            <tr>
                                <th>ARPU at scale</th>
                                <td><span class="font-mono">~&euro;3.50</span></td>
                                <td><span class="font-mono">~&euro;3.31</span></td>
                            </tr>
                            <tr>
                                <th>Moat</th>
                                <td>Regulatory (12+ month licence cycle)</td>
                                <td>Engineering velocity + founder distribution</td>
                            </tr>
                            <tr>
                                <th>Exit profile</th>
                                <td>Acquihire by EU bank, or PE roll-up</td>
                                <td>SaaS multiple acquisition by crypto-fiat ramp</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Mobile stack — accordion-style cards per row --}}
                <div class="compare-stack space-y-4">
                    @php
                        $rows = [
                            ['Capital ask',                'Path A — Licensed', '<span class="font-mono font-bold">€1.0M</span> (founder-discounted from <span class="font-mono">€1.5–2M</span>)', 'Path B — Non-custodial', '<span class="font-mono font-bold">€300–500k</span>'],
                            ['Use of capital',             'Path A',            '<span class="font-mono">€250k</span> locked regulatory capital + <span class="font-mono">€160k</span> setup + <span class="font-mono">€450k</span> runway + <span class="font-mono">€140k</span> buffer', 'Path B', 'Engineering + GTM + 12 mo runway'],
                            ['Regulatory burden',          'Path A',            'MiCA Class 2 + EMI Lithuania', 'Path B', 'None (software vendor)'],
                            ['Time to revenue',            'Path A',            '<span class="font-mono">M3</span> (post-licence-grant)', 'Path B', '<span class="font-mono">M0</span> (already shipping)'],
                            ['Monthly break-even',         'Path A',            '<span class="font-mono">~M17</span> / <span class="font-mono">~16k</span> active users', 'Path B', '<span class="font-mono">~M22</span> / <span class="font-mono">~33k</span> active users'],
                            ['Cumulative cash break-even', 'Path A',            '<span class="font-mono">~M30</span> / <span class="font-mono">~30k</span> users', 'Path B', '<span class="font-mono">~M28</span> / <span class="font-mono">~28k</span> users'],
                            ['ARPU at scale',              'Path A',            '<span class="font-mono">~€3.50</span>', 'Path B', '<span class="font-mono">~€3.31</span>'],
                            ['Moat',                       'Path A',            'Regulatory (12+ month licence cycle)', 'Path B', 'Engineering velocity + founder distribution'],
                            ['Exit profile',               'Path A',            'Acquihire by EU bank, or PE roll-up', 'Path B', 'SaaS multiple acquisition by crypto-fiat ramp'],
                        ];
                    @endphp
                    @foreach ($rows as $row)
                        <details class="bg-white bru-card-sm" {{ $loop->first ? 'open' : '' }}>
                            <summary class="cursor-pointer p-4 font-black text-base">{{ $row[0] }}</summary>
                            <div class="px-4 pb-4 pt-1 space-y-3 text-sm">
                                <div class="bg-acid bru-border p-3">
                                    <div class="text-xs font-black uppercase tracking-wide mb-1">A &mdash; Licensed</div>
                                    <div>{!! $row[2] !!}</div>
                                </div>
                                <div class="bg-z-purple bru-border p-3">
                                    <div class="text-xs font-black uppercase tracking-wide mb-1">B &mdash; Non-custodial</div>
                                    <div>{!! $row[4] !!}</div>
                                </div>
                            </div>
                        </details>
                    @endforeach
                </div>
            </section>

            {{-- ═══════════════════════════════════════
                 PATH A — LICENSED
                 ═══════════════════════════════════════ --}}
            <section id="licensed" class="mb-16 scroll-mt-24">
                <div class="bg-acid bru-card-lg p-6 md:p-10">
                    <div class="inline-block px-3 py-1 bg-obsidian text-acid font-black text-xs tracking-widest mb-4 bru-border" style="transform: rotate(-1deg);">PATH A</div>
                    <h2 class="text-3xl md:text-5xl font-black font-heading mb-4">Licensed banking. <span class="block">€1.0M raise.</span></h2>
                    <p class="text-base md:text-lg font-medium text-obsidian max-w-3xl mb-8">
                        MiCA Class&nbsp;2 + EMI Lithuania. Regulatory moat takes a year to replicate; we have the in-house team to clear the licence cycle without hiring out.
                    </p>

                    {{-- Use of funds bar --}}
                    <h3 class="font-black font-heading text-xl mb-3">Use of funds</h3>
                    <div class="uof-bar mb-2" aria-label="Use of funds breakdown">
                        <div class="bg-mint" style="flex: 250;">
                            <div class="font-mono font-black text-base">&euro;250k</div>
                            <div class="text-xs">Regulatory capital (locked)</div>
                        </div>
                        <div class="bg-z-purple" style="flex: 160;">
                            <div class="font-mono font-black text-base">&euro;160k</div>
                            <div class="text-xs">Licence setup</div>
                        </div>
                        <div class="bg-white" style="flex: 450;">
                            <div class="font-mono font-black text-base">&euro;450k</div>
                            <div class="text-xs">18-month runway</div>
                        </div>
                        <div class="bg-bg-tertiary" style="flex: 140;">
                            <div class="font-mono font-black text-base">&euro;140k</div>
                            <div class="text-xs">Buffer</div>
                        </div>
                    </div>
                    <p class="text-xs text-text-sec font-mono mb-8">€250k + €160k + €450k + €140k = €1.0M</p>

                    {{-- 18-month cumulative cash chart (SVG, brutalist styled) --}}
                    <h3 class="font-black font-heading text-xl mb-3">18-month plan</h3>
                    <div class="bru-border bg-obsidian p-4 md:p-6">
                        <svg viewBox="0 0 600 320" xmlns="http://www.w3.org/2000/svg"
                             class="w-full h-auto"
                             role="img"
                             aria-label="Cumulative cash position over 18 months: starts at minus 1.0 million euros at month 0, reaches break-even at month 17, ends at plus 80 thousand euros at month 18.">
                            <defs>
                                <pattern id="grid-licensed" width="40" height="40" patternUnits="userSpaceOnUse">
                                    <path d="M 40 0 L 0 0 0 40" fill="none" stroke="#1f1f1f" stroke-width="1"/>
                                </pattern>
                            </defs>
                            <rect width="600" height="320" fill="url(#grid-licensed)"/>

                            {{-- Burn area below the curve, filled with acid green --}}
                            <path d="M 80 280 L 160 236 L 240 193 L 400 149 L 533 62 L 560 44 L 560 280 Z"
                                  fill="#ccff00" fill-opacity="0.15" stroke="none"/>

                            {{-- Break-even (zero) line --}}
                            <line x1="80" y1="62" x2="560" y2="62" stroke="#a8f0c4" stroke-width="2" stroke-dasharray="6,4"/>
                            <text x="84" y="58" fill="#a8f0c4" font-size="10" font-weight="700"
                                  font-family="JetBrains Mono, monospace">BREAK-EVEN €0</text>

                            {{-- Y-axis --}}
                            <line x1="80" y1="40" x2="80" y2="280" stroke="#ccff00" stroke-width="2"/>
                            {{-- X-axis --}}
                            <line x1="80" y1="280" x2="560" y2="280" stroke="#ccff00" stroke-width="2"/>

                            {{-- Y-axis labels --}}
                            <text x="74" y="48" fill="#737373" font-size="10" text-anchor="end"
                                  font-family="JetBrains Mono, monospace">+€80k</text>
                            <text x="74" y="195" fill="#737373" font-size="10" text-anchor="end"
                                  font-family="JetBrains Mono, monospace">-€600k</text>
                            <text x="74" y="284" fill="#737373" font-size="10" text-anchor="end"
                                  font-family="JetBrains Mono, monospace">-€1.0M</text>

                            {{-- X-axis labels --}}
                            <text x="80"  y="298" fill="#737373" font-size="10" text-anchor="middle" font-family="JetBrains Mono, monospace">M0</text>
                            <text x="160" y="298" fill="#737373" font-size="10" text-anchor="middle" font-family="JetBrains Mono, monospace">M3</text>
                            <text x="240" y="298" fill="#737373" font-size="10" text-anchor="middle" font-family="JetBrains Mono, monospace">M6</text>
                            <text x="400" y="298" fill="#737373" font-size="10" text-anchor="middle" font-family="JetBrains Mono, monospace">M12</text>
                            <text x="533" y="298" fill="#a8f0c4" font-size="10" text-anchor="middle" font-weight="700" font-family="JetBrains Mono, monospace">M17</text>
                            <text x="560" y="298" fill="#737373" font-size="10" text-anchor="middle" font-family="JetBrains Mono, monospace">M18</text>

                            {{-- Connecting line --}}
                            <polyline points="80,280 160,236 240,193 400,149 533,62 560,44"
                                      fill="none" stroke="#ccff00" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>

                            {{-- Data points --}}
                            <circle cx="80"  cy="280" r="5" fill="#ccff00" stroke="#0a0a0a" stroke-width="2"/>
                            <circle cx="160" cy="236" r="5" fill="#ccff00" stroke="#0a0a0a" stroke-width="2"/>
                            <circle cx="240" cy="193" r="5" fill="#ccff00" stroke="#0a0a0a" stroke-width="2"/>
                            <circle cx="400" cy="149" r="5" fill="#ccff00" stroke="#0a0a0a" stroke-width="2"/>
                            <circle cx="533" cy="62"  r="6" fill="#a8f0c4" stroke="#0a0a0a" stroke-width="2"/>
                            <circle cx="560" cy="44"  r="5" fill="#a8f0c4" stroke="#0a0a0a" stroke-width="2"/>

                            {{-- Annotation: revenue starts at M3 --}}
                            <g font-family="JetBrains Mono, monospace" font-size="9" fill="#ccff00">
                                <text x="172" y="228" font-weight="700">revenue starts</text>
                                <line x1="160" y1="232" x2="170" y2="226" stroke="#ccff00" stroke-width="1"/>
                            </g>
                        </svg>
                    </div>
                    <p class="text-xs text-text-sec font-medium mt-2 mb-8">Full month-by-month detail in data room.</p>

                    {{-- Why MiCA + EMI --}}
                    <h3 class="font-black font-heading text-xl mb-3">Why MiCA + EMI</h3>
                    <ul class="space-y-3">
                        @foreach ([
                            'Regulatory moat takes 12+ months to replicate; licensed competitor count in Lithuania ≤ 5',
                            'EMI unlocks IBANs + card issuance — recurring revenue from interchange',
                            'MiCA opens crypto custody + structured products — second product line',
                        ] as $item)
                            <li class="flex gap-3 items-start bg-white bru-border p-4">
                                <span class="flex-none w-6 h-6 bg-obsidian text-acid font-black flex items-center justify-center text-sm" aria-hidden="true">✓</span>
                                <span class="text-sm md:text-base font-medium">{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>

            {{-- ═══════════════════════════════════════
                 PATH B — NON-CUSTODIAL
                 ═══════════════════════════════════════ --}}
            <section id="non-custodial" class="mb-16 scroll-mt-24">
                <div class="bg-z-purple bru-card-lg p-6 md:p-10">
                    <div class="inline-block px-3 py-1 bg-obsidian text-acid font-black text-xs tracking-widest mb-4 bru-border" style="transform: rotate(1deg);">PATH B</div>
                    <h2 class="text-3xl md:text-5xl font-black font-heading mb-4">Non-custodial SaaS. <span class="block">€300&ndash;500k raise.</span></h2>
                    <p class="text-base md:text-lg font-medium text-obsidian max-w-3xl mb-8">
                        Smart wallets, software margins, no licence. Already shipping. Range tracks GTM ambition: <span class="font-mono">€300k</span> for an organic launch, <span class="font-mono">€500k</span> for paid acquisition.
                    </p>

                    {{-- Use of funds bar — anchored at mid-range €400k --}}
                    <h3 class="font-black font-heading text-xl mb-3">Use of funds</h3>
                    <div class="uof-bar mb-2" aria-label="Use of funds breakdown at mid-range €400k">
                        <div class="bg-acid" style="flex: 160;">
                            <div class="font-mono font-black text-base">&euro;160k</div>
                            <div class="text-xs">Engineering buildout</div>
                        </div>
                        <div class="bg-mint" style="flex: 120;">
                            <div class="font-mono font-black text-base">&euro;120k</div>
                            <div class="text-xs">GTM / paid acquisition</div>
                        </div>
                        <div class="bg-white" style="flex: 120;">
                            <div class="font-mono font-black text-base">&euro;120k</div>
                            <div class="text-xs">12-month runway</div>
                        </div>
                    </div>
                    <p class="text-xs text-text-sec font-mono mb-8">Anchored at mid-range €400k. Lower bound (€300k) cuts GTM; upper bound (€500k) extends runway to 18 months.</p>

                    {{-- Pricing cards --}}
                    <h3 class="font-black font-heading text-xl mb-3">Unit economics &mdash; pricing tiers</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-8">
                        {{-- Free --}}
                        <div class="bg-white bru-card p-6">
                            <div class="text-xs font-black tracking-widest text-text-muted mb-2">FREE</div>
                            <div class="font-mono font-black text-3xl mb-4">&euro;0<span class="text-base font-medium">/mo</span></div>
                            <ul class="space-y-2 text-sm">
                                <li class="flex justify-between"><span>Send fee</span><span class="font-mono font-bold">&euro;0.20</span></li>
                                <li class="flex justify-between"><span>Ramp margin</span><span class="font-mono font-bold">1%</span></li>
                                <li class="flex justify-between"><span>Swap margin</span><span class="font-mono font-bold">0.5%</span></li>
                            </ul>
                        </div>

                        {{-- Pro --}}
                        <div class="bg-acid bru-card p-6 relative">
                            <div class="absolute -top-3 -right-3 bg-obsidian text-acid font-black text-xs px-3 py-1 bru-border tracking-widest" style="transform: rotate(3deg);">RECOMMENDED</div>
                            <div class="text-xs font-black tracking-widest text-obsidian mb-2">PRO</div>
                            <div class="font-mono font-black text-3xl mb-1">&euro;4.99<span class="text-base font-medium">/mo</span></div>
                            <div class="text-xs font-medium text-obsidian mb-4">or <span class="font-mono">&euro;49/yr</span> &middot; 7-day trial</div>
                            <ul class="space-y-2 text-sm">
                                <li class="flex justify-between"><span>Send fee</span><span class="font-mono font-bold">&euro;0.05</span></li>
                                <li class="flex justify-between"><span>Ramp margin</span><span class="font-mono font-bold">0.5%</span></li>
                                <li class="flex justify-between"><span>Swap margin</span><span class="font-mono font-bold">0.2%</span></li>
                            </ul>
                        </div>
                    </div>

                    {{-- Why non-custodial works --}}
                    <h3 class="font-black font-heading text-xl mb-3">Why non-custodial works</h3>
                    <ul class="space-y-3">
                        @foreach ([
                            'Smart wallet (ERC-4337) means user holds keys; we never hold client money',
                            'Stripe Bridge handles fiat ramp; we charge software margin only — no MoR risk',
                            'Same regulatory category as Spotify (software vendor), not as a payment service',
                        ] as $item)
                            <li class="flex gap-3 items-start bg-white bru-border p-4">
                                <span class="flex-none w-6 h-6 bg-obsidian text-acid font-black flex items-center justify-center text-sm" aria-hidden="true">✓</span>
                                <span class="text-sm md:text-base font-medium">{{ $item }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>

            {{-- ═══════════════════════════════════════
                 FOUNDER CREDIBILITY
                 ═══════════════════════════════════════ --}}
            <section class="mb-16">
                <div class="bg-white bru-card-lg p-6 md:p-10">
                    <div class="inline-block px-3 py-1 bg-mint text-obsidian font-black text-xs tracking-widest mb-4 bru-border">FOUNDER</div>
                    <h2 class="text-3xl md:text-4xl font-black font-heading mb-6">Operator credibility.</h2>

                    <div class="space-y-0 mb-8">
                        @php
                            $credRows = [
                                ['Prior role',         'CEO, Paysera (Lithuania&rsquo;s largest fintech, 1M+ users at exit)'],
                                ['Stack capability',   'MLRO + CFO + legal in-house — saves <span class="font-mono">~&euro;200k/year</span> vs hiring out'],
                                ['Network',            'Direct working relationships with Bank of Lithuania, EU passport regulators, Stripe / Pimlico / Privy partnerships'],
                                ['References',        'Available in data room (3 prior board members + 2 regulators)'],
                            ];
                        @endphp
                        @foreach ($credRows as $r)
                            <div class="grid grid-cols-1 md:grid-cols-[200px_1fr] gap-2 md:gap-6 py-3 border-b-2 border-obsidian/10">
                                <div class="font-black font-heading text-sm md:text-base text-text-sec uppercase tracking-wide">{{ $r[0] }}</div>
                                <div class="text-base font-medium">{!! $r[1] !!}</div>
                            </div>
                        @endforeach
                    </div>

                    <a href="#contact" class="btn-hover inline-flex items-center gap-2 px-6 py-3 text-sm font-black bg-acid text-obsidian bru-border">
                        Request founder bio + references &rarr;
                    </a>
                </div>
            </section>

            {{-- ═══════════════════════════════════════
                 ENGINEERING RIGOR
                 ═══════════════════════════════════════ --}}
            <section class="mb-16">
                <h2 class="text-3xl md:text-4xl font-black font-heading mb-3">Engineering rigor.</h2>
                <p class="text-text-sec font-medium max-w-2xl mb-8">Three claims, each backed by something a technical diligence partner can read.</p>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    {{-- 1 --}}
                    <div class="bg-white bru-card p-6">
                        <div class="font-mono text-xs font-black bg-acid bru-border inline-block px-2 py-0.5 mb-3">01</div>
                        <h3 class="font-black font-heading text-lg mb-2">Production codebase live</h3>
                        <p class="text-sm font-medium text-text-sec mb-3">
                            Backend on Laravel 12 / PHP 8.4 with 57 bounded contexts, event-sourced, multi-tenant. Mobile on Expo SDK 54 / React Native New Architecture. Both repos auditable.
                        </p>
                        <span class="inline-flex items-center gap-1.5 text-xs font-mono font-bold bg-bg-tertiary text-text-sec border-2 border-text-muted/40 px-2 py-0.5" title="Available in data room after inquiry submission">
                            <svg width="10" height="10" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M5 6V4.5a3 3 0 0 1 6 0V6h.5A1.5 1.5 0 0 1 13 7.5v6A1.5 1.5 0 0 1 11.5 15h-7A1.5 1.5 0 0 1 3 13.5v-6A1.5 1.5 0 0 1 4.5 6H5zm1.5 0h3V4.5a1.5 1.5 0 0 0-3 0V6z"/></svg>
                            data room
                        </span>
                    </div>

                    {{-- 2 --}}
                    <div class="bg-white bru-card p-6">
                        <div class="font-mono text-xs font-black bg-z-purple bru-border inline-block px-2 py-0.5 mb-3">02</div>
                        <h3 class="font-black font-heading text-lg mb-2">v1.3.0 fully spec&rsquo;d</h3>
                        <p class="text-sm font-medium text-text-sec mb-3">
                            16 architectural decisions documented across subscription, quote pipeline, entitlements, reconciliation, GDPR compliance, App Store / Google Play submission readiness.
                        </p>
                        <span class="inline-flex items-center gap-1.5 text-xs font-mono font-bold bg-bg-tertiary text-text-sec border-2 border-text-muted/40 px-2 py-0.5" title="Available in data room after inquiry submission">
                            <svg width="10" height="10" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M5 6V4.5a3 3 0 0 1 6 0V6h.5A1.5 1.5 0 0 1 13 7.5v6A1.5 1.5 0 0 1 11.5 15h-7A1.5 1.5 0 0 1 3 13.5v-6A1.5 1.5 0 0 1 4.5 6H5zm1.5 0h3V4.5a1.5 1.5 0 0 0-3 0V6z"/></svg>
                            engineering handover doc
                        </span>
                    </div>

                    {{-- 3 --}}
                    <div class="bg-white bru-card p-6">
                        <div class="font-mono text-xs font-black bg-mint bru-border inline-block px-2 py-0.5 mb-3">03</div>
                        <h3 class="font-black font-heading text-lg mb-2">Realistic timelines</h3>
                        <p class="text-sm font-medium text-text-sec mb-3">
                            Ship date for v1.3.0 (commercial pricing) is <span class="font-mono">~7.25</span> calendar weeks from kickoff. EU localization (v1.3.1) adds <span class="font-mono">~3&ndash;4</span> weeks. No vapor.
                        </p>
                        <span class="inline-flex items-center gap-1.5 text-xs font-mono font-bold bg-bg-tertiary text-text-sec border-2 border-text-muted/40 px-2 py-0.5" title="Available in data room after inquiry submission">
                            <svg width="10" height="10" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M5 6V4.5a3 3 0 0 1 6 0V6h.5A1.5 1.5 0 0 1 13 7.5v6A1.5 1.5 0 0 1 11.5 15h-7A1.5 1.5 0 0 1 3 13.5v-6A1.5 1.5 0 0 1 4.5 6H5zm1.5 0h3V4.5a1.5 1.5 0 0 0-3 0V6z"/></svg>
                            plan + commit log
                        </span>
                    </div>
                </div>
            </section>

            {{-- Traction section: omit until there's content. --}}

            {{-- ═══════════════════════════════════════
                 DATA ROOM CTA + CONTACT FORM
                 ═══════════════════════════════════════ --}}
            <section id="contact" class="mb-12 scroll-mt-24">
                <div class="bg-obsidian text-white bru-card-lg p-6 md:p-10">
                    <h2 class="text-3xl md:text-5xl font-black font-heading mb-4 max-w-3xl">
                        See the numbers, the model, and the team&rsquo;s references.
                    </h2>
                    <p class="text-base md:text-lg text-white/80 max-w-2xl mb-8 font-medium">
                        Submit below. We review every inquiry personally. Expect a reply within 48 hours.
                    </p>

                    @if ($errors->any())
                        <div class="bg-acid text-obsidian bru-border p-4 mb-6">
                            <p class="font-black mb-1">Please fix the following:</p>
                            <ul class="list-disc pl-5 text-sm space-y-0.5">
                                @foreach ($errors->all() as $err)
                                    <li>{{ $err }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('invest.submit') }}" class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        @csrf

                        {{-- Honeypot — humans never see this; bots fill it; controller silently swallows. --}}
                        <input type="text" name="website" tabindex="-1" autocomplete="off" aria-hidden="true" tabindex="-1" style="position:absolute;left:-9999px;top:-9999px;height:1px;width:1px;opacity:0;">

                        <div>
                            <label for="f-name" class="field-label text-white">Name</label>
                            <input id="f-name" type="text" name="name" required minlength="2" maxlength="120" value="{{ old('name') }}" class="field-input">
                        </div>

                        <div>
                            <label for="f-email" class="field-label text-white">Email</label>
                            <input id="f-email" type="email" name="email" required maxlength="255" value="{{ old('email') }}" class="field-input">
                        </div>

                        <div class="md:col-span-2">
                            <label for="f-linkedin" class="field-label text-white">LinkedIn URL</label>
                            <input id="f-linkedin" type="url" name="linkedin_url" required maxlength="500" placeholder="https://linkedin.com/in/your-handle" value="{{ old('linkedin_url') }}" class="field-input">
                        </div>

                        <div>
                            <span class="field-label text-white">Investing as</span>
                            <div class="space-y-2 bg-white p-3 bru-border" role="radiogroup" aria-label="Investing as">
                                @foreach ([
                                    'angel'         => 'Angel',
                                    'vc'            => 'VC',
                                    'family_office' => 'Family office',
                                    'other'         => 'Other',
                                ] as $value => $label)
                                    <label class="flex items-center gap-2 text-obsidian font-medium cursor-pointer">
                                        <input type="radio" name="investing_as" value="{{ $value }}" {{ old('investing_as') === $value ? 'checked' : '' }} required>
                                        <span class="text-sm">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <span class="field-label text-white">Path of interest</span>
                            <div class="space-y-2 bg-white p-3 bru-border" role="radiogroup" aria-label="Path of interest">
                                @foreach ([
                                    'licensed'      => 'Licensed (MiCA + EMI)',
                                    'non_custodial' => 'Non-custodial SaaS',
                                    'both'          => 'Both / undecided',
                                ] as $value => $label)
                                    <label class="flex items-center gap-2 text-obsidian font-medium cursor-pointer">
                                        <input type="radio" name="path_of_interest" value="{{ $value }}" {{ old('path_of_interest') === $value ? 'checked' : '' }} required>
                                        <span class="text-sm">{{ $label }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label for="f-check" class="field-label text-white">Check size range</label>
                            <select id="f-check" name="check_size_range" required class="field-select">
                                <option value="" {{ old('check_size_range') ? '' : 'selected' }} disabled>Select a range</option>
                                @foreach ([
                                    'under_25k'  => 'Under €25k',
                                    '25k_100k'   => '€25k – €100k',
                                    '100k_500k'  => '€100k – €500k',
                                    '500k_plus'  => '€500k+',
                                ] as $value => $label)
                                    <option value="{{ $value }}" {{ old('check_size_range') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="md:col-span-2">
                            <label for="f-q" class="field-label text-white">What questions do you want answered first? <span class="font-medium text-white/60">(optional, 500 chars)</span></label>
                            <textarea id="f-q" name="questions" maxlength="500" rows="4" class="field-textarea">{{ old('questions') }}</textarea>
                        </div>

                        <div class="md:col-span-2 flex items-start gap-3 bg-white p-4 bru-border">
                            <input type="checkbox" id="f-gdpr" name="gdpr_consent" value="1" required class="mt-1 flex-none w-5 h-5">
                            <label for="f-gdpr" class="text-obsidian text-sm font-medium">
                                I consent to FinAegis storing this submission to contact me about this investment opportunity.
                                <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener" class="font-bold underline hover:no-underline">Privacy Policy</a>.
                            </label>
                        </div>

                        <div class="md:col-span-2">
                            <button type="submit" class="btn-hover inline-flex items-center gap-2 px-7 py-3.5 text-base font-black bg-acid text-obsidian bru-border">
                                Submit inquiry &rarr;
                            </button>
                            <p class="text-xs text-white/60 mt-3">5 submissions per IP per 24 hours. We never share your details.</p>
                        </div>
                    </form>
                </div>
            </section>

        </div>
    </main>

    {{-- ═══════════════════════════════════════
         FOOTER
         ═══════════════════════════════════════ --}}
    <footer class="px-5 mt-8 pb-12">
        <div class="mx-auto max-w-6xl bg-white bru-card p-6 md:p-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div>
                    <div class="font-black font-heading text-base mb-2">FinAegis Ltd.</div>
                    <p class="text-sm text-text-sec font-medium">Legal entity behind {{ $brand }}. Registered in Lithuania.</p>
                </div>
                <div>
                    <div class="font-black font-heading text-base mb-2">Contact</div>
                    <p class="text-sm text-text-sec font-medium">
                        <a href="mailto:invest@finaegis.com" class="font-bold underline hover:no-underline">invest@finaegis.com</a><br>
                        Replies within 48 hours.
                    </p>
                </div>
                <div>
                    <div class="font-black font-heading text-base mb-2">Legal</div>
                    <ul class="text-sm text-text-sec font-medium space-y-1">
                        <li><a href="{{ route('legal.privacy') }}" class="font-bold underline hover:no-underline">Privacy Policy</a></li>
                        <li><a href="{{ route('legal.terms') }}" class="font-bold underline hover:no-underline">Terms of Service</a></li>
                    </ul>
                </div>
            </div>
            <div class="border-t-2 border-obsidian/10 pt-4 text-xs text-text-muted leading-relaxed">
                <p class="mb-2">
                    <strong class="text-text-sec">For accredited investors only.</strong>
                    This page is provided for informational purposes and does not constitute an offer to sell or a solicitation to buy any securities. Any investment in FinAegis Ltd. requires a separately executed subscription agreement and is subject to applicable securities laws in your jurisdiction. Past performance is not indicative of future results.
                </p>
                <p class="font-mono">© {{ date('Y') }} FinAegis Ltd. All rights reserved.</p>
            </div>
        </div>
    </footer>

</body>
</html>
