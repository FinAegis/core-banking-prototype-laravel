@extends('layouts.public')

@section('title', 'Machine Payments Protocol — Multi-Rail Agent Payments | ' . config('brand.name', 'Zelta') . '')

@section('seo')
    @include('partials.seo', [
        'title' => 'Machine Payments Protocol — Multi-Rail Agent Payments',
        'description' => 'HTTP 402 payments with Stripe, Tempo, Lightning, and Card rails. AI agent commerce with MCP transport binding and spending limits.',
        'keywords' => 'machine payments, MPP, HTTP 402, Stripe, Tempo, Lightning, AI agent payments, MCP, spending limits, multi-rail',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => url('/features')],
        ['name' => 'Machine Payments Protocol', 'url' => url('/features/machine-payments')]
    ]" />
@endsection

@section('content')

    <!-- Hero -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                <div class="flex justify-center mb-6">
                    <div class="w-20 h-20 bg-white/10 rounded-2xl flex items-center justify-center">
                        <svg class="w-10 h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="inline-flex items-center px-3 py-1 bg-white/10 backdrop-blur-sm rounded-full text-sm text-slate-300 mb-6">
                    <span class="w-2 h-2 bg-emerald-400 rounded-full mr-2"></span>
                    v6.5.0 &middot; Multi-Rail Payments
                </div>
                <h1 class="text-4xl md:text-5xl lg:text-6xl font-bold text-white mb-6">
                    Machine Payments Protocol
                </h1>
                <p class="text-lg md:text-xl text-slate-300 max-w-3xl mx-auto mb-8">
                    HTTP 402 payments with Stripe, Tempo, Lightning, and Card rails. Let AI agents pay for APIs using the best available payment method — fiat or crypto.
                </p>
                <div class="flex flex-wrap justify-center gap-4">
                    <a href="{{ url('/api/v1/mpp/status') }}" class="btn btn-outline">View Protocol Status</a>
                    <a href="{{ url('/features/x402-protocol') }}" class="btn btn-outline">Compare with x402</a>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="py-16 lg:py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-slate-900 text-center mb-12">How It Works</h2>
            <div class="grid md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div class="w-16 h-16 bg-red-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <span class="text-2xl font-bold text-red-600">402</span>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">Challenge Issued</h3>
                    <p class="text-slate-600">Server returns <code>WWW-Authenticate: Payment</code> with pricing, rails, and HMAC-bound challenge.</p>
                </div>
                <div class="text-center">
                    <div class="w-16 h-16 bg-blue-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">Payment Sent</h3>
                    <p class="text-slate-600">Client selects rail (Stripe, Tempo, Lightning, Card), pays off-band, retries with <code>Authorization: Payment</code>.</p>
                </div>
                <div class="text-center">
                    <div class="w-16 h-16 bg-emerald-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <h3 class="text-lg font-semibold text-slate-900 mb-2">Resource Delivered</h3>
                    <p class="text-slate-600">Server verifies, settles, returns resource with <code>Payment-Receipt</code> header. RFC 9457 errors for failures.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Multi-Rail Comparison -->
    <section class="py-16 lg:py-20 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-slate-900 text-center mb-12">Payment Rails</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-100">
                            <th class="p-4 font-semibold text-slate-900">Rail</th>
                            <th class="p-4 font-semibold text-slate-900">Type</th>
                            <th class="p-4 font-semibold text-slate-900">Currencies</th>
                            <th class="p-4 font-semibold text-slate-900">Settlement</th>
                            <th class="p-4 font-semibold text-slate-900">Best For</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <tr><td class="p-4 font-medium">Stripe SPT</td><td class="p-4">Fiat</td><td class="p-4">USD, EUR, GBP</td><td class="p-4">Instant (PaymentIntents)</td><td class="p-4">SaaS, API monetization</td></tr>
                        <tr><td class="p-4 font-medium">Tempo</td><td class="p-4">Stablecoin</td><td class="p-4">USDC, USDT</td><td class="p-4">~500ms on-chain</td><td class="p-4">DeFi, cross-border</td></tr>
                        <tr><td class="p-4 font-medium">Lightning</td><td class="p-4">Bitcoin</td><td class="p-4">BTC</td><td class="p-4">Instant (preimage)</td><td class="p-4">Micropayments</td></tr>
                        <tr><td class="p-4 font-medium">Card</td><td class="p-4">Fiat</td><td class="p-4">USD, EUR, GBP</td><td class="p-4">Standard card networks</td><td class="p-4">Traditional commerce</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <!-- MCP Integration -->
    <section class="py-16 lg:py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-2 gap-12 items-center">
                <div>
                    <h2 class="text-3xl font-bold text-slate-900 mb-6">Native MCP Binding</h2>
                    <p class="text-slate-600 mb-4">
                        MPP defines error code <strong>-32042</strong> as the MCP equivalent of HTTP 402. AI agents using MCP tool calls can pay for tools and resources without HTTP round-trips.
                    </p>
                    <ul class="space-y-3">
                        <li class="flex items-start"><span class="text-emerald-500 mr-2 mt-1">&#10003;</span><span><strong>mpp.payment</strong> — Handle 402 challenges and generate credentials</span></li>
                        <li class="flex items-start"><span class="text-emerald-500 mr-2 mt-1">&#10003;</span><span><strong>mpp.discovery</strong> — Discover MPP-enabled resources</span></li>
                        <li class="flex items-start"><span class="text-emerald-500 mr-2 mt-1">&#10003;</span><span><strong>Spending limits</strong> — Per-agent daily and per-transaction budgets</span></li>
                        <li class="flex items-start"><span class="text-emerald-500 mr-2 mt-1">&#10003;</span><span><strong>Auto-pay</strong> — Agents pay autonomously within budget</span></li>
                    </ul>
                </div>
                <div class="bg-slate-900 rounded-xl p-6 text-sm font-mono text-emerald-400 overflow-x-auto">
<pre>// MCP error -32042 (Payment Required)
{
  "error": {
    "code": -32042,
    "message": "Payment Required",
    "data": {
      "challenge": "Payment eyJ...",
      "rails": ["stripe", "tempo"],
      "amount_cents": 50,
      "currency": "USD"
    }
  }
}</pre>
                </div>
            </div>
        </div>
    </section>

    <!-- Protocol Comparison -->
    <section class="py-16 lg:py-20 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-slate-900 text-center mb-12">MPP vs x402 vs AP2</h2>
            <div class="grid md:grid-cols-3 gap-8">
                <div class="bg-white rounded-xl p-6 shadow-sm border border-slate-200">
                    <h3 class="text-xl font-bold text-slate-900 mb-3">x402</h3>
                    <p class="text-sm text-slate-500 mb-4">Coinbase</p>
                    <ul class="text-slate-600 space-y-2 text-sm">
                        <li>USDC on EVM chains + Solana</li>
                        <li>Custom payment headers</li>
                        <li>Facilitator-based settlement</li>
                        <li>Crypto-native</li>
                    </ul>
                </div>
                <div class="bg-white rounded-xl p-6 shadow-sm border-2 border-blue-500">
                    <h3 class="text-xl font-bold text-blue-600 mb-3">MPP</h3>
                    <p class="text-sm text-slate-500 mb-4">Stripe + Tempo Labs</p>
                    <ul class="text-slate-600 space-y-2 text-sm">
                        <li>Stripe, Tempo, Lightning, Card</li>
                        <li>Standard HTTP auth headers</li>
                        <li>HMAC-SHA256 challenge binding</li>
                        <li>Fiat + crypto multi-rail</li>
                    </ul>
                </div>
                <div class="bg-white rounded-xl p-6 shadow-sm border border-slate-200">
                    <h3 class="text-xl font-bold text-slate-900 mb-3">AP2</h3>
                    <p class="text-sm text-slate-500 mb-4">Google</p>
                    <ul class="text-slate-600 space-y-2 text-sm">
                        <li>Cart, Intent, Payment Mandates</li>
                        <li>Verifiable Digital Credentials</li>
                        <li>Wraps x402 + MPP as methods</li>
                        <li>Authorization + accountability</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Quick Start -->
    <section class="py-20 bg-white">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="font-display text-3xl font-bold text-slate-900">Try It Now</h2>
                <p class="text-slate-500 mt-4">Three steps from discovery to paid API response.</p>
            </div>
            <div class="space-y-8">
                <div class="flex gap-6">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">1</div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-lg text-slate-900 mb-2">Discover MPP capabilities</h3>
                        <pre class="bg-slate-900 text-slate-300 rounded-lg p-4 text-sm overflow-x-auto"><code><span class="text-emerald-400">$</span> curl https://api.zelta.app/.well-known/mpp-configuration | jq

{
  <span class="text-emerald-400">"mpp_version"</span>: 1,
  <span class="text-emerald-400">"supported_rails"</span>: [<span class="text-amber-300">"stripe"</span>, <span class="text-amber-300">"tempo"</span>, <span class="text-amber-300">"lightning"</span>, <span class="text-amber-300">"card"</span>, <span class="text-amber-300">"x402"</span>],
  <span class="text-emerald-400">"endpoints"</span>: { ... }
}</code></pre>
                    </div>
                </div>
                <div class="flex gap-6">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">2</div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-lg text-slate-900 mb-2">Make a request to a monetized endpoint</h3>
                        <pre class="bg-slate-900 text-slate-300 rounded-lg p-4 text-sm overflow-x-auto"><code><span class="text-emerald-400">$</span> curl -i https://api.zelta.app/v1/premium/data \
  -H <span class="text-amber-300">"Authorization: Bearer YOUR_TOKEN"</span>

<span class="text-red-400">HTTP/1.1 402 Payment Required</span>
WWW-Authenticate: Payment eyJpZC...
Content-Type: application/json

{<span class="text-emerald-400">"error"</span>:<span class="text-amber-300">"PAYMENT_REQUIRED"</span>,<span class="text-emerald-400">"pricing"</span>:{<span class="text-emerald-400">"amount_cents"</span>:50,<span class="text-emerald-400">"currency"</span>:<span class="text-amber-300">"USD"</span>,<span class="text-emerald-400">"rails"</span>:[<span class="text-amber-300">"stripe"</span>,<span class="text-amber-300">"x402"</span>]}}</code></pre>
                    </div>
                </div>
                <div class="flex gap-6">
                    <div class="flex-shrink-0 w-10 h-10 bg-blue-600 rounded-full flex items-center justify-center text-white font-bold">3</div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-lg text-slate-900 mb-2">Pay and receive data</h3>
                        <pre class="bg-slate-900 text-slate-300 rounded-lg p-4 text-sm overflow-x-auto"><code><span class="text-emerald-400">$</span> curl https://api.zelta.app/v1/premium/data \
  -H <span class="text-amber-300">"Authorization: Payment eyJjaGFsbG..."</span> \
  -H <span class="text-amber-300">"Accept: application/json"</span>

<span class="text-emerald-400">HTTP/1.1 200 OK</span>
Payment-Receipt: eyJzdGF0dXMi...

{<span class="text-emerald-400">"data"</span>: {<span class="text-emerald-400">"market_cap"</span>: <span class="text-amber-300">"2.1T"</span>, <span class="text-emerald-400">"volume_24h"</span>: <span class="text-amber-300">"89.4B"</span>}}</code></pre>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Error Handling -->
    <section class="py-16 lg:py-20 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-slate-900">Error Handling</h2>
                <p class="text-slate-500 mt-4">RFC 9457 problem details for every failure mode. Your agents always know what went wrong.</p>
            </div>
            <div class="grid md:grid-cols-2 gap-8">
                <div class="card-feature !p-8">
                    <h3 class="text-xl font-bold text-slate-900 mb-3">Spending Limit Exceeded</h3>
                    <p class="text-slate-600 mb-4">Returned when an agent's daily or per-transaction budget has been exhausted.</p>
                    <pre class="bg-slate-900 text-slate-300 rounded-lg p-4 text-sm overflow-x-auto"><code>{
  <span class="text-emerald-400">"type"</span>: <span class="text-amber-300">"spending-limit-exceeded"</span>,
  <span class="text-emerald-400">"title"</span>: <span class="text-amber-300">"Daily budget exceeded"</span>,
  <span class="text-emerald-400">"detail"</span>: <span class="text-amber-300">"Agent agent-123 has spent $48.50 of $50.00 daily limit"</span>,
  <span class="text-emerald-400">"status"</span>: 402
}</code></pre>
                </div>
                <div class="card-feature !p-8">
                    <h3 class="text-xl font-bold text-slate-900 mb-3">Settlement Failed</h3>
                    <p class="text-slate-600 mb-4">Returned when the selected payment rail rejects the transaction.</p>
                    <pre class="bg-slate-900 text-slate-300 rounded-lg p-4 text-sm overflow-x-auto"><code>{
  <span class="text-emerald-400">"type"</span>: <span class="text-amber-300">"settlement-failed"</span>,
  <span class="text-emerald-400">"title"</span>: <span class="text-amber-300">"Payment settlement failed"</span>,
  <span class="text-emerald-400">"detail"</span>: <span class="text-amber-300">"Rail 'stripe' returned error: card_declined"</span>,
  <span class="text-emerald-400">"status"</span>: 402
}</code></pre>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="py-16 lg:py-20 bg-fa-navy">
        <div class="max-w-3xl mx-auto px-4 text-center">
            <h2 class="text-3xl font-bold text-white mb-6">Three Protocols, One Platform</h2>
            <p class="text-slate-300 mb-8">{{ config('brand.name', 'Zelta') }} supports x402, MPP, and AP2 mandates. Your AI agents choose the best payment method for each transaction.</p>
            <div class="flex flex-wrap justify-center gap-4">
                <a href="{{ url('/features/zelta-cli') }}" class="btn btn-outline">Zelta CLI</a>
                <a href="{{ url('/features/x402-protocol') }}" class="btn btn-outline">x402 Protocol</a>
                <a href="{{ url('/api/documentation') }}" class="btn btn-outline">Developer Docs</a>
            </div>
        </div>
    </section>

@endsection
