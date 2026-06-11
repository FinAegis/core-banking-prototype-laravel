@extends('layouts.public')

@section('title', 'Developer Experience - ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'Developer Experience',
        'description' => 'Partner sandbox with 3 seed profiles, webhook testing and HMAC-verified delivery replay, API key lifecycle CLI. First API call in under 5 minutes.',
        'keywords' => 'developer experience, sandbox, webhook testing, API key management, CLI, developer tools, integration, DX, developer portal, sandbox provisioning',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => url('/features')],
        ['name' => 'Developer Experience', 'url' => url('/features/developer-experience')]
    ]" />
@endsection

@push('styles')
<style>
    .dx-card {
        transition: all 0.3s ease;
    }
    .dx-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    }
</style>
@endpush

@section('content')

    <!-- Hero Section -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                <div class="inline-flex items-center bg-indigo-500/10 border border-indigo-500/20 rounded-full px-4 py-2 mb-6">
                    <span class="text-indigo-400 text-sm font-medium">Built for Fast Integration</span>
                </div>
                <h1 class="font-display text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">Developer Experience</h1>
                <p class="text-lg text-slate-400 max-w-3xl mx-auto">
                    Partner sandbox with 3 seed profiles, webhook testing and delivery replay, API key lifecycle management, and benchmarking commands — everything you need to integrate, test, and go live quickly.
                </p>
                <div class="mt-10 flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition">
                        Get API Keys
                    </a>
                    <a href="{{ route('developers') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">
                        View Docs
                    </a>
                </div>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-indigo-500/20 to-transparent"></div>
    </section>

    <!-- Sandbox -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Partner Sandbox</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                Provision a fully seeded sandbox environment in seconds. Choose from three seed profiles depending on what you're building — all provisioned via a single artisan command.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Basic Profile -->
                <div class="dx-card card-feature !p-8">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-gray-50 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        </div>
                        <span class="bg-gray-100 text-gray-700 text-xs font-medium px-2 py-1 rounded">Basic</span>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Basic Profile</h3>
                    <p class="text-slate-500 text-sm mb-5">A minimal tenant with a single user, one account, and sample transaction history. Ideal for API exploration and quick prototyping.</p>
                    <div class="bg-gray-900 rounded-lg p-4 text-green-400 font-mono text-xs overflow-x-auto">
                        <pre><code>php artisan sandbox:provision \
  --profile=basic \
  --tenant=my-company</code></pre>
                    </div>
                </div>

                <!-- Full Profile -->
                <div class="dx-card card-feature !p-8 border-indigo-200 bg-indigo-50/30">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-indigo-50 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        </div>
                        <span class="bg-indigo-100 text-indigo-700 text-xs font-medium px-2 py-1 rounded">Recommended</span>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Full Profile</h3>
                    <p class="text-slate-500 text-sm mb-5">Multiple users, accounts, wallets, assets, complete transaction history, and sample webhook events. Best for comprehensive integration testing.</p>
                    <div class="bg-gray-900 rounded-lg p-4 text-green-400 font-mono text-xs overflow-x-auto">
                        <pre><code>php artisan sandbox:provision \
  --profile=full \
  --tenant=my-company</code></pre>
                    </div>
                </div>

                <!-- Payments Profile -->
                <div class="dx-card card-feature !p-8">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 bg-green-50 rounded-lg flex items-center justify-center">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                        </div>
                        <span class="bg-green-100 text-green-700 text-xs font-medium px-2 py-1 rounded">Payments</span>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Payments Profile</h3>
                    <p class="text-slate-500 text-sm mb-5">Pre-configured payment rails, sample x402 and MPP payment sessions, and webhook event history for payment lifecycle testing.</p>
                    <div class="bg-gray-900 rounded-lg p-4 text-green-400 font-mono text-xs overflow-x-auto">
                        <pre><code>php artisan sandbox:provision \
  --profile=payments \
  --tenant=my-company</code></pre>
                    </div>
                </div>
            </div>

            <div class="mt-8 text-center">
                <p class="text-sm text-slate-500">Reset your sandbox at any time: <code class="bg-gray-100 px-2 py-1 rounded text-xs font-mono">php artisan sandbox:reset --tenant=my-company</code></p>
            </div>
        </div>
    </section>

    <!-- Webhook Testing -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Webhook Testing &amp; Replay</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                Test your webhook handlers without triggering real events. Generate synthetic test payloads for any event type and replay failed deliveries with HMAC signature verification.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="dx-card card-feature !p-8">
                    <h3 class="text-xl font-bold mb-4">Test Event Types</h3>
                    <p class="text-slate-500 mb-5 text-sm">Generate realistic test payloads for the 5 most common event types in your integration flow.</p>
                    <div class="space-y-3">
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <span class="w-2 h-2 bg-green-500 rounded-full mr-3 flex-shrink-0"></span>
                            <span class="font-mono text-sm">payment.completed</span>
                        </div>
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <span class="w-2 h-2 bg-red-500 rounded-full mr-3 flex-shrink-0"></span>
                            <span class="font-mono text-sm">payment.failed</span>
                        </div>
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <span class="w-2 h-2 bg-blue-500 rounded-full mr-3 flex-shrink-0"></span>
                            <span class="font-mono text-sm">account.credited</span>
                        </div>
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <span class="w-2 h-2 bg-orange-500 rounded-full mr-3 flex-shrink-0"></span>
                            <span class="font-mono text-sm">kyc.status_changed</span>
                        </div>
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <span class="w-2 h-2 bg-purple-500 rounded-full mr-3 flex-shrink-0"></span>
                            <span class="font-mono text-sm">consent.revoked</span>
                        </div>
                    </div>
                </div>

                <div class="dx-card card-feature !p-8">
                    <h3 class="text-xl font-bold mb-4">Delivery &amp; Replay</h3>
                    <p class="text-slate-500 mb-5 text-sm">Every webhook delivery is logged with request headers, body, response status, and latency. Replay any failed delivery from the dashboard or CLI.</p>
                    <ul class="space-y-3 text-sm text-gray-500 mb-6">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Full delivery log with request/response</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>HMAC-SHA256 signature on every delivery</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Manual and automatic retry on 5xx</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>CLI replay: <code class="text-xs bg-gray-100 px-1 rounded">webhook:replay &lt;id&gt;</code></li>
                    </ul>
                    <div class="bg-gray-900 rounded-lg p-4 text-green-400 font-mono text-xs overflow-x-auto">
                        <pre><code># Verify your endpoint signature
X-Webhook-Signature: sha256=abc123...
X-Webhook-Event: payment.completed
X-Webhook-Delivery: uuid-delivery-id</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- API Key Management -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">API Key Management</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                Full API key lifecycle from the CLI. Create scoped keys, rotate credentials, revoke compromised keys, and audit all active tokens without touching the dashboard.
            </p>

            <div class="max-w-3xl mx-auto">
                <div class="bg-gray-900 rounded-xl p-6 text-green-400 font-mono text-sm overflow-x-auto">
                    <div class="space-y-4">
                        <div>
                            <span class="text-gray-500"># Create a new key with payment scope</span>
                            <div class="mt-1">$ php artisan api-key:create --name="Production" --scopes=read,write</div>
                            <div class="text-yellow-400 mt-1">Created: fak_abc123... (stored once, keep it safe)</div>
                        </div>
                        <div>
                            <span class="text-gray-500"># Rotate a key (old key immediately invalidated)</span>
                            <div class="mt-1">$ php artisan api-key:rotate --id=42</div>
                            <div class="text-yellow-400 mt-1">New key: fak_xyz789...</div>
                        </div>
                        <div>
                            <span class="text-gray-500"># List all active keys for this tenant</span>
                            <div class="mt-1">$ php artisan api-key:list</div>
                        </div>
                        <div>
                            <span class="text-gray-500"># Revoke a compromised key</span>
                            <div class="mt-1">$ php artisan api-key:revoke --id=42</div>
                            <div class="text-red-400 mt-1">Key revoked. All requests with this token return 401.</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Benchmarks -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Benchmark Commands</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                Verify platform performance in your own environment. Built-in benchmark commands measure ledger throughput and payment rail latency with configurable concurrency.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                <div class="dx-card card-feature !p-8">
                    <div class="w-12 h-12 bg-indigo-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Ledger Throughput</h3>
                    <p class="text-slate-500 text-sm mb-4">Measure double-entry journal posting performance with configurable transaction count and concurrency level.</p>
                    <div class="bg-gray-900 rounded-lg p-4 text-green-400 font-mono text-xs overflow-x-auto">
                        <pre><code>php artisan benchmark:ledger \
  --transactions=10000 \
  --concurrency=10

Result: 8,420 TPS (avg 1.19ms)</code></pre>
                    </div>
                </div>

                <div class="dx-card card-feature !p-8">
                    <div class="w-12 h-12 bg-green-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Payment Rail Speed</h3>
                    <p class="text-slate-500 text-sm mb-4">Measure end-to-end payment initiation latency for each configured rail, including routing decision time.</p>
                    <div class="bg-gray-900 rounded-lg p-4 text-green-400 font-mono text-xs overflow-x-auto">
                        <pre><code>php artisan benchmark:rails \
  --rails=ach,rtp,fednow \
  --payments=100

ACH:    12ms avg (routing + post)
RTP:     8ms avg
FedNow:  9ms avg</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- DX Quick Wins -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">Everything Developers Need</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="text-center">
                    <div class="w-14 h-14 bg-indigo-50 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                    </div>
                    <h3 class="font-semibold mb-2">Full API Docs</h3>
                    <p class="text-sm text-slate-500">OpenAPI 3.0 spec, Swagger UI, and Postman collection — generated from live code, always current.</p>
                </div>
                <div class="text-center">
                    <div class="w-14 h-14 bg-green-50 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                    </div>
                    <h3 class="font-semibold mb-2">GraphQL Playground</h3>
                    <p class="text-sm text-slate-500">Interactive GraphQL explorer with full schema introspection and authenticated query execution.</p>
                </div>
                <div class="text-center">
                    <div class="w-14 h-14 bg-blue-50 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    <h3 class="font-semibold mb-2">Zelta CLI</h3>
                    <p class="text-sm text-slate-500">24-command CLI for payments, SMS, wallets, and API monetization — available as PHAR and npm.</p>
                </div>
                <div class="text-center">
                    <div class="w-14 h-14 bg-purple-50 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <h3 class="font-semibold mb-2">Zelta SDK</h3>
                    <p class="text-sm text-slate-500">Composer-installable PHP SDK with transparent x402 and MPP auto-handling. Zero payment protocol boilerplate.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-20 bg-fa-navy">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">First API Call in Under 5 Minutes</h2>
            <p class="text-lg text-slate-400 mb-8">Provision a sandbox, generate an API key, and make your first authenticated request in minutes. No credit card required.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition">Create Free Account</a>
                <a href="{{ route('developers') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">Browse Developer Docs</a>
            </div>
        </div>
    </section>

@endsection
