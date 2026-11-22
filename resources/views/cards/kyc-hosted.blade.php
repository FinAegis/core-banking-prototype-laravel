<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Identity check · {{ $brand }}</title>
    <style>
        :root { color-scheme: light dark; --bg: #ffffff; --fg: #111827; --muted: #4b5563; --accent: #111827; --accent-fg: #ffffff; }
        @media (prefers-color-scheme: dark) { :root { --bg: #0b0f17; --fg: #f3f4f6; --muted: #9ca3af; --accent: #f3f4f6; --accent-fg: #0b0f17; } }
        * { box-sizing: border-box; }
        body { margin: 0; background: var(--bg); color: var(--fg); font: 16px/1.5 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        main { max-width: 640px; margin: 0 auto; padding: 24px 16px 48px; }
        h1 { font-size: 22px; margin: 0 0 8px; }
        p { color: var(--muted); margin: 0 0 16px; }
        .button { display: inline-block; padding: 12px 20px; border-radius: 10px; background: var(--accent); color: var(--accent-fg); text-decoration: none; font-weight: 600; }
        [hidden] { display: none !important; }
        #sumsub-websdk-container { margin-top: 16px; min-height: 480px; }
    </style>
</head>
<body>
<main>
    <h1>Identity check</h1>

    @if ($state === 'ready')
        <section id="intro">
            <p>Follow the steps below to confirm your identity. The card provider needs this check before it can issue your {{ $brand }} card.</p>
        </section>
        <section id="sent" hidden>
            <p>Thank you. Your identity check has been sent for review. You can go back to the {{ $brand }} app.</p>
            @if ($returnUrl !== '')
                <a class="button" href="{{ $returnUrl }}">Back to the app</a>
            @endif
        </section>
        <section id="failed" hidden>
            <p>The identity check stopped because of an error. Go back to the {{ $brand }} app and start it again.</p>
            @if ($returnUrl !== '')
                <a class="button" href="{{ $returnUrl }}">Back to the app</a>
            @endif
        </section>
        <div id="sumsub-websdk-container"></div>

        {{-- SumSub's loader lives at an unversioned URL that SumSub updates, so
             it cannot carry an integrity hash; the page CSP allows scripts only
             from static.sumsub.com. --}}
        <script src="https://static.sumsub.com/idensic/static/sns-websdk-builder.js"></script>
        <script>
            (function () {
                var returnUrl = @json($returnUrl);
                var refreshUrl = @json($refreshUrl);
                var submittedUrl = @json($submittedUrl);
                var finished = false;

                function show(id) {
                    document.getElementById('intro').hidden = true;
                    document.getElementById(id).hidden = false;
                }

                function sent() {
                    if (finished) { return; }
                    finished = true;
                    show('sent');

                    // Tell the server the check was sent, then return to the app.
                    var back = function () { if (returnUrl) { window.location.href = returnUrl; } };
                    var csrf = document.querySelector('meta[name="csrf-token"]');
                    var timer = window.setTimeout(back, 1500);
                    fetch(submittedUrl, {
                        method: 'POST',
                        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf ? csrf.getAttribute('content') : '' },
                        credentials: 'same-origin',
                        keepalive: true,
                        referrerPolicy: 'no-referrer'
                    }).catch(function () {}).then(function () { window.clearTimeout(timer); back(); });
                }

                function statusChanged(payload) {
                    var status = payload && payload.reviewStatus;
                    if (status === 'pending' || status === 'queued' || status === 'onHold' || status === 'completed') { sent(); }
                }

                function newToken() {
                    return fetch(refreshUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin', referrerPolicy: 'no-referrer' })
                        .then(function (response) {
                            if (!response.ok) { throw new Error('token'); }
                            return response.json();
                        })
                        .then(function (body) { return body.token; });
                }

                if (typeof window.snsWebSdk === 'undefined') {
                    show('failed');
                    return;
                }

                window.snsWebSdk
                    .init(@json($token), newToken)
                    .withConf({ lang: 'en' })
                    .withOptions({ addViewportTag: false, adaptIframeHeight: true })
                    .on('idCheck.onApplicantSubmitted', sent)
                    .on('idCheck.onApplicantResubmitted', sent)
                    .on('idCheck.onApplicantStatusChanged', statusChanged)
                    .on('idCheck.applicantStatus', statusChanged)
                    .on('idCheck.onError', function () { show('failed'); })
                    .build()
                    .launch('#sumsub-websdk-container');
            })();
        </script>
    @elseif ($state === 'submitted')
        <p>Your identity check has already been sent. You can go back to the {{ $brand }} app to see its status.</p>
        @if ($returnUrl !== '')
            <a class="button" href="{{ $returnUrl }}">Back to the app</a>
        @endif
    @elseif ($state === 'unavailable')
        <p>The identity check could not start right now. Please try again in a few minutes.</p>
    @else
        <p>This link has expired. Go back to the {{ $brand }} app and start the identity check again.</p>
        @if ($returnUrl !== '')
            <a class="button" href="{{ $returnUrl }}">Back to the app</a>
        @endif
    @endif
</main>
</body>
</html>
