@extends('layouts.public')

@php $brand = config('brand.name', 'Zelta'); @endphp

@section('title', 'API Sandbox - ' . $brand . ' Developer Documentation')

@section('seo')
    @include('partials.seo', [
        'title' => 'API Sandbox - ' . $brand . ' Developer Documentation',
        'description' => $brand . ' API Sandbox — Test API endpoints directly from your browser. No Postman or cURL needed. Live interactive API testing environment.',
        'keywords' => $brand . ', API sandbox, API testing, interactive, REST API, developer tools',
    ])
@endsection

@push('styles')
<link href="https://fonts.bunny.net/css?family=fira-code:400,500&display=swap" rel="stylesheet" />
<style>
    .code-font { font-family: 'Fira Code', monospace; }
    .sandbox-gradient { background: linear-gradient(135deg, #0c1222 0%, #131b2e 50%, #1e293b 100%); }
    .method-badge-get    { background: #dcfce7; color: #166534; }
    .method-badge-post   { background: #dbeafe; color: #1e40af; }
    .method-badge-put    { background: #fef9c3; color: #854d0e; }
    .method-badge-delete { background: #fee2e2; color: #991b1b; }
    .status-2xx { background: #dcfce7; color: #166534; }
    .status-4xx { background: #fee2e2; color: #991b1b; }
    .status-5xx { background: #fef9c3; color: #854d0e; }
    .response-panel {
        background: #0f1419;
        border-radius: 0.75rem;
        font-family: 'Fira Code', monospace;
        font-size: 0.875rem;
        line-height: 1.6;
        min-height: 200px;
    }
    .example-card {
        transition: all 0.2s ease;
        border: 2px solid transparent;
    }
    .example-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
        border-color: #e5e7eb;
    }
    select, textarea, input[type="text"] {
        font-family: 'Figtree', sans-serif;
    }
    textarea.code-font {
        font-family: 'Fira Code', monospace;
        font-size: 0.8125rem;
    }
    .json-key     { color: #60a5fa; }
    .json-string  { color: #34d399; }
    .json-number  { color: #d97706; }
    .json-bool    { color: #f87171; }
    .json-null    { color: #94a3b8; }
</style>
@endpush

@section('content')

<!-- Hero -->
<section class="sandbox-gradient text-white relative overflow-hidden">
    <div class="absolute inset-0" aria-hidden="true">
        <div class="absolute top-20 left-10 w-72 h-72 bg-indigo-400 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-pulse"></div>
        <div class="absolute top-40 right-10 w-72 h-72 bg-cyan-400 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-pulse" style="animation-delay: 1s;"></div>
    </div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
        <div class="text-center">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-white/10 text-white/80 border border-white/20 mb-4">Interactive Tester</span>
            <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight mb-4">API Sandbox</h1>
            <p class="text-xl text-white/80 max-w-2xl mx-auto">
                Test API endpoints directly from your browser. No Postman or cURL needed.
            </p>
        </div>
    </div>
</section>

<!-- Sandbox Tool -->
<section class="bg-white py-16">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold mb-2">Request Builder</h2>
        <p class="text-slate-500 mb-8">Compose and send API requests live. Responses are shown below.</p>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
            <!-- Left: form -->
            <div class="space-y-5">
                <!-- Method + URL -->
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Method &amp; URL</label>
                    <div class="flex gap-2">
                        <select id="sb-method" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                            <option value="GET">GET</option>
                            <option value="POST">POST</option>
                            <option value="PUT">PUT</option>
                            <option value="DELETE">DELETE</option>
                        </select>
                        <input id="sb-url" type="text"
                            value="{{ rtrim(config('app.url', 'https://api.zelta.io'), '/') }}/api/v1/x402/status"
                            class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                </div>

                <!-- Headers -->
                <div>
                    <label for="sb-headers" class="block text-sm font-semibold text-slate-700 mb-1">Headers <span class="text-slate-400 font-normal">(one per line, Key: Value)</span></label>
                    <textarea id="sb-headers" rows="4" class="code-font w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-y">Accept: application/json
Authorization: Bearer &lt;your-token&gt;</textarea>
                </div>

                <!-- Body -->
                <div id="sb-body-wrapper">
                    <label for="sb-body" class="block text-sm font-semibold text-slate-700 mb-1">Request Body <span class="text-slate-400 font-normal">(JSON)</span></label>
                    <textarea id="sb-body" rows="6" class="code-font w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-500 resize-y" placeholder='{ "key": "value" }'></textarea>
                </div>

                <button id="sb-send"
                    class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-indigo-600 hover:bg-indigo-700 active:bg-indigo-800 text-white font-semibold py-3 px-6 transition-colors focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span id="sb-send-label">Send Request</span>
                </button>
            </div>

            <!-- Right: response -->
            <div class="space-y-4">
                <div class="flex items-center gap-3">
                    <h3 class="text-base font-semibold text-slate-700">Response</h3>
                    <span id="sb-status-badge" class="hidden text-xs font-bold px-2 py-0.5 rounded-full"></span>
                    <span id="sb-time-badge" class="hidden text-xs text-slate-400"></span>
                </div>
                <div class="response-panel p-4 overflow-auto max-h-96">
                    <pre id="sb-response" class="text-slate-400 text-sm">// Response will appear here after sending a request.</pre>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Pre-built Examples -->
<section class="bg-slate-50 py-16">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold mb-2">Quick Examples</h2>
        <p class="text-slate-500 mb-8">Click "Try it" to load the example into the builder above, then hit Send.</p>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">

            <!-- x402 Status -->
            <div class="example-card bg-white rounded-xl border border-slate-200 p-6 flex flex-col">
                <div class="flex items-center gap-2 mb-3">
                    <span class="method-badge-get text-xs font-bold px-2 py-0.5 rounded-full uppercase">GET</span>
                    <span class="text-xs text-slate-500 code-font truncate">/api/v1/x402/status</span>
                </div>
                <h3 class="text-base font-bold text-slate-800 mb-1">x402 Status</h3>
                <p class="text-sm text-slate-500 flex-1 mb-4">Returns the x402 payment protocol configuration and supported networks.</p>
                <button class="try-example-btn mt-auto w-full text-center text-sm font-semibold text-indigo-600 border border-indigo-200 rounded-lg py-2 hover:bg-indigo-50 transition-colors"
                    data-method="GET"
                    data-url="/api/v1/x402/status"
                    data-body="">
                    Try it &rarr;
                </button>
            </div>

            <!-- MPP Discovery -->
            <div class="example-card bg-white rounded-xl border border-slate-200 p-6 flex flex-col">
                <div class="flex items-center gap-2 mb-3">
                    <span class="method-badge-get text-xs font-bold px-2 py-0.5 rounded-full uppercase">GET</span>
                    <span class="text-xs text-slate-500 code-font truncate">/.well-known/mpp-configuration</span>
                </div>
                <h3 class="text-base font-bold text-slate-800 mb-1">MPP Discovery</h3>
                <p class="text-sm text-slate-500 flex-1 mb-4">Multi-Party Payment protocol discovery document listing supported payment rails.</p>
                <button class="try-example-btn mt-auto w-full text-center text-sm font-semibold text-indigo-600 border border-indigo-200 rounded-lg py-2 hover:bg-indigo-50 transition-colors"
                    data-method="GET"
                    data-url="/api/.well-known/mpp-configuration"
                    data-body="">
                    Try it &rarr;
                </button>
            </div>

            <!-- A2A Agent Card -->
            <div class="example-card bg-white rounded-xl border border-slate-200 p-6 flex flex-col">
                <div class="flex items-center gap-2 mb-3">
                    <span class="method-badge-get text-xs font-bold px-2 py-0.5 rounded-full uppercase">GET</span>
                    <span class="text-xs text-slate-500 code-font truncate">/.well-known/agent.json</span>
                </div>
                <h3 class="text-base font-bold text-slate-800 mb-1">A2A Agent Card</h3>
                <p class="text-sm text-slate-500 flex-1 mb-4">Agent-to-Agent protocol card exposing capabilities for AI agent orchestration.</p>
                <button class="try-example-btn mt-auto w-full text-center text-sm font-semibold text-indigo-600 border border-indigo-200 rounded-lg py-2 hover:bg-indigo-50 transition-colors"
                    data-method="GET"
                    data-url="/api/.well-known/agent.json"
                    data-body="">
                    Try it &rarr;
                </button>
            </div>

        </div>
    </div>
</section>

<!-- Tips Section -->
<section class="bg-white py-12 border-t border-slate-100">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-xl font-bold text-slate-800 mb-6">Tips</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 text-sm text-slate-600">
            <div class="flex gap-3">
                <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                </div>
                <div>
                    <p class="font-semibold text-slate-700 mb-1">Authentication</p>
                    Replace <code class="bg-slate-100 px-1 rounded">&lt;your-token&gt;</code> in the Authorization header with a valid API token from your dashboard.
                </div>
            </div>
            <div class="flex gap-3">
                <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </div>
                <div>
                    <p class="font-semibold text-slate-700 mb-1">JSON Body</p>
                    For POST and PUT requests, enter valid JSON in the body field. The <code class="bg-slate-100 px-1 rounded">Content-Type: application/json</code> header is added automatically.
                </div>
            </div>
            <div class="flex gap-3">
                <div class="w-8 h-8 rounded-full bg-indigo-100 flex items-center justify-center shrink-0">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="font-semibold text-slate-700 mb-1">CORS</p>
                    Requests are sent from your browser directly to the API. Ensure your token has the correct scopes. Cross-origin restrictions apply.
                </div>
            </div>
        </div>
    </div>
</section>

@endsection

@push('scripts')
<script>
(function () {
    'use strict';

    var baseUrl = '{{ rtrim(config('app.url', ''), '/') }}';
    var methodEl   = document.getElementById('sb-method');
    var urlEl      = document.getElementById('sb-url');
    var headersEl  = document.getElementById('sb-headers');
    var bodyEl     = document.getElementById('sb-body');
    var bodyWrap   = document.getElementById('sb-body-wrapper');
    var sendBtn    = document.getElementById('sb-send');
    var sendLabel  = document.getElementById('sb-send-label');
    var responseEl = document.getElementById('sb-response');
    var statusBadge = document.getElementById('sb-status-badge');
    var timeBadge   = document.getElementById('sb-time-badge');

    // Show/hide body field based on method
    function toggleBody() {
        var m = methodEl.value;
        bodyWrap.style.display = (m === 'POST' || m === 'PUT') ? 'block' : 'none';
    }
    methodEl.addEventListener('change', toggleBody);
    toggleBody();

    // Parse headers textarea into object
    function parseHeaders(raw) {
        var headers = {};
        raw.split('\n').forEach(function (line) {
            var idx = line.indexOf(':');
            if (idx > -1) {
                var key = line.slice(0, idx).trim();
                var val = line.slice(idx + 1).trim();
                if (key) { headers[key] = val; }
            }
        });
        return headers;
    }

    // Escape HTML special chars — all external text is run through this before DOM insertion
    function escHtml(str) {
        return str.replace(/&/g, '&amp;')
                  .replace(/</g, '&lt;')
                  .replace(/>/g, '&gt;')
                  .replace(/"/g, '&quot;');
    }

    // Syntax-highlight a JSON string; escHtml is called first so no raw user HTML is injected
    function colorizeJson(jsonStr) {
        var escaped = escHtml(jsonStr);
        // Wrap tokens with CSS class spans (tokens are already HTML-escaped)
        return escaped.replace(
            /(&quot;(?:\\u[0-9a-fA-F]{4}|\\[^u]|[^\\&])*&quot;\s*:?|true|false|null|-?\d+(?:\.\d*)?(?:[eE][+\-]?\d+)?)/g,
            function (match) {
                var cls;
                if (match.slice(-1) === ':') {
                    cls = 'json-key';   // object key ending with :
                } else if (match.charAt(0) === '&') {
                    cls = 'json-string'; // &quot; = escaped quote → string value
                } else if (match === 'true' || match === 'false') {
                    cls = 'json-bool';
                } else if (match === 'null') {
                    cls = 'json-null';
                } else {
                    cls = 'json-number';
                }
                return '<span class="' + cls + '">' + match + '</span>';
            }
        );
    }

    function setStatusBadge(code) {
        statusBadge.className = 'text-xs font-bold px-2 py-0.5 rounded-full';
        if (code >= 200 && code < 300) {
            statusBadge.classList.add('status-2xx');
        } else if (code >= 400 && code < 500) {
            statusBadge.classList.add('status-4xx');
        } else {
            statusBadge.classList.add('status-5xx');
        }
        statusBadge.textContent = String(code);
        statusBadge.classList.remove('hidden');
    }

    sendBtn.addEventListener('click', function () {
        var method  = methodEl.value;
        var url     = urlEl.value.trim();
        var headers = parseHeaders(headersEl.value);

        if (!url) {
            responseEl.textContent = 'Error: URL is required.';
            return;
        }

        if (method === 'POST' || method === 'PUT') {
            headers['Content-Type'] = 'application/json';
        }

        sendBtn.disabled = true;
        sendLabel.textContent = 'Sending\u2026';
        responseEl.textContent = 'Waiting for response\u2026';
        statusBadge.classList.add('hidden');
        timeBadge.classList.add('hidden');

        var t0 = performance.now();
        var opts = { method: method, headers: headers };
        if ((method === 'POST' || method === 'PUT') && bodyEl.value.trim()) {
            opts.body = bodyEl.value.trim();
        }

        fetch(url, opts)
            .then(function (resp) {
                var elapsed = Math.round(performance.now() - t0);
                setStatusBadge(resp.status);
                timeBadge.textContent = elapsed + ' ms';
                timeBadge.classList.remove('hidden');

                var contentType = resp.headers.get('content-type') || '';
                if (contentType.indexOf('application/json') !== -1) {
                    return resp.json().then(function (data) {
                        // colorizeJson escapes HTML first, then wraps class spans — safe
                        responseEl.innerHTML = colorizeJson(JSON.stringify(data, null, 2));
                    });
                }
                return resp.text().then(function (text) {
                    responseEl.textContent = text;
                });
            })
            .catch(function (err) {
                var elapsed = Math.round(performance.now() - t0);
                timeBadge.textContent = elapsed + ' ms';
                timeBadge.classList.remove('hidden');
                statusBadge.classList.add('hidden');
                // err.message is a browser-internal string; set via textContent for safety
                responseEl.textContent = 'Network error: ' + err.message;
            })
            .finally(function () {
                sendBtn.disabled = false;
                sendLabel.textContent = 'Send Request';
            });
    });

    // Quick example buttons — populate form fields using DOM properties, not innerHTML
    document.querySelectorAll('.try-example-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            methodEl.value = btn.getAttribute('data-method');
            urlEl.value    = baseUrl + btn.getAttribute('data-url');
            bodyEl.value   = btn.getAttribute('data-body') || '';
            toggleBody();
            // Smooth scroll to builder section
            document.getElementById('sb-send').closest('section').scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });
}());
</script>
@endpush
