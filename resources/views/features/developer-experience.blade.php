@extends('layouts.public')

@section('title', 'Developer Experience - ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'Developer Experience',
        'description' => 'Developer tools for partner integrations. A sandbox can be created with one of three seed profiles. Test webhooks are signed with HMAC-SHA256, and console commands create, rotate and revoke API keys.',
        'keywords' => 'developer experience, sandbox, webhook testing, API key management, command line, developer tools, integration, developer portal, sandbox provisioning',
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
                    <span class="text-indigo-400 text-sm font-medium">Developer tools</span>
                </div>
                <h1 class="font-display text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">Developer Experience</h1>
                <p class="text-lg text-slate-400 max-w-3xl mx-auto">
                    These tools help partners build and test an integration. A sandbox comes with sample data from one of three seed profiles. You can send test webhooks, manage API keys from the command line and run benchmarks in your own environment.
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
                One Artisan command creates a sandbox for a partner and fills it with sample data. You choose one of three seed profiles to match what you are building.
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
                    <p class="text-slate-500 text-sm mb-5">This profile creates 5 users, 10 accounts and 20 transactions. It suits a first look at the API and quick prototypes.</p>
                    <div class="bg-gray-900 rounded-lg p-4 text-green-400 font-mono text-xs overflow-x-auto">
                        <pre><code>php artisan partner:sandbox:create \
  my-company \
  --profile=basic</code></pre>
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
                    <p class="text-slate-500 text-sm mb-5">This profile creates 20 users, 40 accounts, 200 transactions, 10 loans, 5 cards and 10 wallets. It suits full integration testing.</p>
                    <div class="bg-gray-900 rounded-lg p-4 text-green-400 font-mono text-xs overflow-x-auto">
                        <pre><code>php artisan partner:sandbox:create \
  my-company \
  --profile=full</code></pre>
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
                    <p class="text-slate-500 text-sm mb-5">This profile creates 10 users, 20 accounts, 100 transactions and 50 payment intents. It suits tests of the payment lifecycle.</p>
                    <div class="bg-gray-900 rounded-lg p-4 text-green-400 font-mono text-xs overflow-x-auto">
                        <pre><code>php artisan partner:sandbox:create \
  my-company \
  --profile=payments</code></pre>
                    </div>
                </div>
            </div>

            <div class="mt-8 text-center">
                <p class="text-sm text-slate-500">You can reset a sandbox at any time with <code class="bg-gray-100 px-2 py-1 rounded text-xs font-mono">php artisan partner:sandbox:reset &lt;sandbox&gt;</code>.</p>
            </div>
        </div>
    </section>

    <!-- Webhook Testing -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Webhook Testing and Replay</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                You can test your webhook handler without a real event. The API generates a sample payload for an event type. A replay service can send a past delivery again with an HMAC signature.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div class="dx-card card-feature !p-8">
                    <h3 class="text-xl font-bold mb-4">Test Event Types</h3>
                    <p class="text-slate-500 mb-5 text-sm">The test endpoint has sample payloads for five common event types. Any other event type gets a simple placeholder payload.</p>
                    <div class="space-y-3">
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <span class="w-2 h-2 bg-green-500 rounded-full mr-3 flex-shrink-0"></span>
                            <span class="font-mono text-sm">payment.completed</span>
                        </div>
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <span class="w-2 h-2 bg-red-500 rounded-full mr-3 flex-shrink-0"></span>
                            <span class="font-mono text-sm">transfer.initiated</span>
                        </div>
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <span class="w-2 h-2 bg-blue-500 rounded-full mr-3 flex-shrink-0"></span>
                            <span class="font-mono text-sm">account.created</span>
                        </div>
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <span class="w-2 h-2 bg-orange-500 rounded-full mr-3 flex-shrink-0"></span>
                            <span class="font-mono text-sm">consent.authorized</span>
                        </div>
                        <div class="flex items-center p-3 bg-gray-50 rounded-lg">
                            <span class="w-2 h-2 bg-purple-500 rounded-full mr-3 flex-shrink-0"></span>
                            <span class="font-mono text-sm">card.authorization</span>
                        </div>
                    </div>
                </div>

                <div class="dx-card card-feature !p-8">
                    <h3 class="text-xl font-bold mb-4">Delivery and Replay</h3>
                    <p class="text-slate-500 mb-5 text-sm">Each webhook is sent by a queued job. The job signs the body and retries a failed delivery for up to 24 hours. The delivery log endpoint exists, but it does not list deliveries yet.</p>
                    <ul class="space-y-3 text-sm text-gray-500 mb-6">
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Event type and delivery identifier in the headers</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>HMAC-SHA256 signature on every delivery</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>Automatic retries with back-off for up to 24 hours</li>
                        <li class="flex items-start"><svg class="w-5 h-5 text-green-500 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>A replay service sends a past delivery again</li>
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
                Partner API keys can be managed entirely from the command line. You can create a key with scopes, rotate it, list a partner's keys and revoke a key that was exposed.
            </p>

            <div class="max-w-3xl mx-auto">
                <div class="bg-gray-900 rounded-xl p-6 text-green-400 font-mono text-sm overflow-x-auto">
                    <div class="space-y-4">
                        <div>
                            <span class="text-gray-500"># Create a new key with read and write scopes</span>
                            <div class="mt-1">$ php artisan partner:api-key:create my-company --scopes=read,write</div>
                            <div class="text-yellow-400 mt-1">Created: fak_abc123... (stored once, keep it safe)</div>
                        </div>
                        <div>
                            <span class="text-gray-500"># Rotate a key (old key immediately invalidated)</span>
                            <div class="mt-1">$ php artisan partner:api-key:rotate my-company</div>
                            <div class="text-yellow-400 mt-1">New key: fak_xyz789...</div>
                        </div>
                        <div>
                            <span class="text-gray-500"># List all keys for this partner</span>
                            <div class="mt-1">$ php artisan partner:api-key:list my-company</div>
                        </div>
                        <div>
                            <span class="text-gray-500"># Revoke a compromised key</span>
                            <div class="mt-1">$ php artisan partner:api-key:revoke 42</div>
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
                You can measure performance in your own environment. Built-in benchmark commands measure how fast the ledger posts entries and how fast the payment rails process payments.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                <div class="dx-card card-feature !p-8">
                    <div class="w-12 h-12 bg-indigo-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Ledger Throughput</h3>
                    <p class="text-slate-500 text-sm mb-4">This command posts double-entry journal entries and measures the speed. You choose how many entries it posts.</p>
                    <div class="bg-gray-900 rounded-lg p-4 text-green-400 font-mono text-xs overflow-x-auto">
                        <pre><code>php artisan benchmark:ledger \
  --count=10000

# Prints the measured throughput and average time</code></pre>
                    </div>
                </div>

                <div class="dx-card card-feature !p-8">
                    <div class="w-12 h-12 bg-green-50 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Payment Rail Speed</h3>
                    <p class="text-slate-500 text-sm mb-4">This command measures how long payment processing takes on the payment rails, including the routing decision.</p>
                    <div class="bg-gray-900 rounded-lg p-4 text-green-400 font-mono text-xs overflow-x-auto">
                        <pre><code>php artisan benchmark:payment-rails \
  --count=100

# Prints the average time for each rail</code></pre>
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
                    <p class="text-sm text-slate-500">There is an OpenAPI 3.0 specification, Swagger UI and a Postman collection. The specification is generated from annotations in the code.</p>
                </div>
                <div class="text-center">
                    <div class="w-14 h-14 bg-green-50 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                    </div>
                    <h3 class="font-semibold mb-2">GraphQL Endpoint</h3>
                    <p class="text-sm text-slate-500">One GraphQL endpoint at /graphql serves the whole schema. You can read the schema with an introspection query and run queries with your own token.</p>
                </div>
                <div class="text-center">
                    <div class="w-14 h-14 bg-blue-50 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                    </div>
                    <h3 class="font-semibold mb-2">Zelta CLI</h3>
                    <p class="text-sm text-slate-500">The Zelta CLI has 27 commands for payments, SMS, wallets and paid APIs. You can install it as a PHAR file or from npm.</p>
                </div>
                <div class="text-center">
                    <div class="w-14 h-14 bg-purple-50 rounded-lg flex items-center justify-center mx-auto mb-4">
                        <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <h3 class="font-semibold mb-2">Zelta SDK</h3>
                    <p class="text-sm text-slate-500">The PHP SDK installs with Composer. It answers x402 and Machine Payments Protocol payment challenges for you. For x402 you give it a signer for your key.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-20 bg-fa-navy">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Make Your First API Call</h2>
            <p class="text-lg text-slate-400 mb-8">Create a sandbox and an API key. You can then make your first signed-in request.</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition">Create Free Account</a>
                <a href="{{ route('developers') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">Browse Developer Docs</a>
            </div>
        </div>
    </section>

@endsection
