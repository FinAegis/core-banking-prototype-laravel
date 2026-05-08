<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @php $brand = config('brand.name', 'Zelta'); @endphp

    <title>Data room &mdash; access required &mdash; {{ $brand }}</title>

    @include('partials.favicon')
    <meta name="robots" content="noindex,nofollow">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=space-grotesk:400,500,600,700&dm-sans:400,500,600,700&jetbrains-mono:400,500,700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="/css/app-landing.css">

    <style>
        body {
            background: #faf9f6;
            color: #0a0a0a;
            font-family: 'Space Grotesk', system-ui, sans-serif;
            min-height: 100vh;
        }
        ::selection { background: #ccff00; color: #000; }
        .bg-acid { background-color: #ccff00; }
        .bg-mint { background-color: #a8f0c4; }
        .text-text-sec { color: #404040; }
        .bru-border { border: 3px solid #0a0a0a; }
        .bru-card-lg { border: 3px solid #0a0a0a; box-shadow: 8px 8px 0 #0a0a0a; }
        .btn-hover { transition: transform 0.15s ease; }
        .btn-hover:hover { transform: scale(1.04); }
        .font-heading { font-family: 'Space Grotesk', system-ui, sans-serif; letter-spacing: -0.04em; }
        .font-mono { font-family: 'JetBrains Mono', ui-monospace, monospace; }
    </style>
</head>
<body class="antialiased">
    <header class="px-5 py-5 md:py-6">
        <div class="mx-auto max-w-4xl flex items-center justify-between">
            <a href="{{ url('/') }}" class="flex items-center gap-3 btn-hover">
                <div class="flex h-11 w-11 items-center justify-center rounded-xl text-xl font-black bg-mint bru-border" style="font-family: 'Space Grotesk', sans-serif; letter-spacing: -0.04em;">
                    Z
                </div>
                <span class="text-2xl font-black font-heading">{{ $brand }}</span>
            </a>
        </div>
    </header>

    <main class="px-5 pb-24">
        <div class="mx-auto max-w-2xl text-center mt-12">
            <div class="inline-block bg-acid bru-border px-4 py-1 text-sm font-black mb-6 font-mono" style="transform: rotate(-1deg);">
                403 &middot; ACCESS REQUIRED
            </div>
            <h1 class="text-4xl md:text-5xl font-black font-heading mb-6" style="line-height: 0.95;">
                You need to request access first.
            </h1>
            <div class="bg-white bru-card-lg p-6 md:p-8 mb-8 text-left">
                <p class="text-base md:text-lg font-medium text-text-sec mb-3">
                    The data room is gated. We review every request personally and grant access individually &mdash; usually within 48 hours.
                </p>
                <p class="text-sm text-text-sec font-medium">
                    Submit the inquiry form on the investor page and we&rsquo;ll come back to you with a link.
                </p>
            </div>
            <a href="{{ route('invest.show') }}#contact" class="btn-hover inline-flex items-center gap-2 px-6 py-3 text-base font-black bg-acid text-obsidian bru-border">
                Request access &rarr;
            </a>
        </div>
    </main>
</body>
</html>
