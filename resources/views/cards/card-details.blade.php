<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Card details · {{ $brand }}</title>
    <style>
        :root { color-scheme: light dark; --bg: #ffffff; --fg: #111827; --muted: #4b5563; --line: #e5e7eb; --accent: #111827; --accent-fg: #ffffff; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0b0f17; --fg: #f3f4f6; --muted: #9ca3af; --line: #1f2937; --accent: #f3f4f6; --accent-fg: #0b0f17; } }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--fg); font: 16px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        main { max-width: 480px; margin: 0 auto; padding: 24px 16px 48px; }
        h1 { font-size: 22px; margin: 0 0 8px; }
        p { color: var(--muted); margin: 0 0 16px; }
        dl { margin: 16px 0 24px; }
        .row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 12px 0; border-bottom: 1px solid var(--line); }
        dt { color: var(--muted); font-size: 14px; }
        dd { margin: 4px 0 0; min-height: 24px; font-family: ui-monospace, Menlo, monospace; font-size: 18px; letter-spacing: 1px; }
        .copy { position: relative; min-width: 64px; padding: 8px 12px; border: 1px solid var(--line); border-radius: 8px; font-size: 14px; text-align: center; }
        .button { display: inline-block; padding: 12px 20px; border: 0; border-radius: 10px; background: var(--accent); color: var(--accent-fg); font: inherit; font-weight: 600; }
        [hidden] { display: none !important; }
    </style>
</head>
<body>
<main>
    <h1>Card details</h1>

    @if ($state === 'ready')
        <section id="details">
            <p>Your card number, expiry date and security code are shown below. Keep them private.</p>
            <dl>
                <div class="row"><div><dt>Card number</dt><dd id="card-pan"></dd></div><div class="copy" id="copy-card-pan">Copy</div></div>
                <div class="row"><div><dt>Expiry date</dt><dd id="card-exp"></dd></div><div class="copy" id="copy-card-exp">Copy</div></div>
                <div class="row"><div><dt>Security code</dt><dd id="card-cvv"></dd></div><div class="copy" id="copy-card-cvv">Copy</div></div>
            </dl>
            <p>This link works only once. To see the details again, go back to the app.</p>
            <button type="button" class="button" id="done">Done</button>
        </section>
        <section id="failed" hidden>
            <p>The card details could not be shown right now. Please try again in a few minutes.</p>
        </section>

        {{-- Interlace Widget.js renders the card data in its own frames; the
             page CSP allows its script and https frames only. --}}
        <script src="{{ $scriptUrl }}" type="text/javascript"></script>
        <script>
            (function () {
                var native = window.ReactNativeWebView;
                function post(message) {
                    if (native && typeof native.postMessage === 'function') { native.postMessage(JSON.stringify(message)); }
                }
                function failed() {
                    document.getElementById('details').hidden = true;
                    document.getElementById('failed').hidden = false;
                    post({ type: 'error', reason: 'widget' });
                }

                document.getElementById('done').addEventListener('click', function () { post({ type: 'closed' }); });

                if (typeof window.widget === 'undefined' || typeof window.widget.bootstrap !== 'function') {
                    failed();
                    return;
                }

                var text = { span: { 'font-family': 'ui-monospace, Menlo, monospace', 'font-size': '18px', 'letter-spacing': '1px' } };
                window.widget.bootstrap({
                    clientAccessToken: @json($token),
                    component: {
                        showPan: {
                            cardPan: { domId: 'card-pan', format: true, styles: text },
                            copyCardPan: { domId: 'copy-card-pan' },
                            cardExp: { domId: 'card-exp', format: true, styles: text },
                            copyCardExp: { domId: 'copy-card-exp' },
                            cardCvv: { domId: 'card-cvv', styles: text },
                            copyCardCvv: { domId: 'copy-card-cvv' }
                        }
                    },
                    callbackEvents: {
                        onSuccess: function () { post({ type: 'loaded' }); },
                        onFailure: failed
                    }
                });
            })();
        </script>
    @else
        @if ($state === 'used')
            <p>This link has already been used. Go back to the app and open the card details again.</p>
        @elseif ($state === 'unavailable')
            <p>The card details could not be shown right now. Please try again in a few minutes.</p>
        @else
            <p>This link has expired. Go back to the app and open the card details again.</p>
        @endif
        <script>
            (function () {
                var native = window.ReactNativeWebView;
                if (native && typeof native.postMessage === 'function') {
                    native.postMessage(JSON.stringify({ type: 'error', reason: @json($state) }));
                }
            })();
        </script>
    @endif
</main>
</body>
</html>
