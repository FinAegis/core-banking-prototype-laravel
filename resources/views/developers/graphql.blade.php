@extends('layouts.public')

@php $brand = config('brand.name', 'Zelta'); @endphp

@section('title', 'GraphQL API - ' . $brand . ' Developer Documentation')

@section('seo')
    @include('partials.seo', [
        'title' => 'GraphQL API - ' . $brand . ' Developer Documentation',
        'description' => $brand . ' GraphQL API — 39 domain schemas, real-time subscriptions, Lighthouse PHP powered.',
        'keywords' => $brand . ', GraphQL, API, Lighthouse, subscriptions, queries, mutations',
    ])
@endsection

@push('styles')
<link href="https://fonts.bunny.net/css?family=fira-code:400,500&display=swap" rel="stylesheet" />
<style>
    .code-font { font-family: 'Fira Code', monospace; }
    .gql-gradient { background: linear-gradient(135deg, #e535ab 0%, #7c3aed 100%); }
    .code-container { position: relative; background: #0f1419; border-radius: 0.75rem; overflow: hidden; }
    .code-header { background: #0f172a; padding: 0.5rem 1rem; font-size: 0.75rem; font-family: 'Figtree', sans-serif; color: #94a3b8; display: flex; justify-content: space-between; align-items: center; }
    .code-block { font-family: 'Fira Code', monospace; font-size: 0.875rem; line-height: 1.5; overflow-x: auto; white-space: pre; }
</style>
@endpush

@section('content')

<!-- Hero -->
<section class="gql-gradient text-white relative overflow-hidden">
    <div class="absolute inset-0" aria-hidden="true">
        <div class="absolute top-20 left-10 w-72 h-72 bg-pink-400 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-pulse"></div>
        <div class="absolute top-40 right-10 w-72 h-72 bg-violet-400 rounded-full mix-blend-multiply filter blur-xl opacity-20 animate-pulse" style="animation-delay: 1s;"></div>
    </div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
        <div class="text-center">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-white/10 text-white/80 border border-white/20 mb-4">39 Domain Schemas</span>
            <h1 class="text-4xl md:text-5xl font-extrabold tracking-tight mb-4">GraphQL API</h1>
            <p class="text-xl text-white/80 max-w-2xl mx-auto">
                Query 39 domains with a single endpoint. Real-time subscriptions, Sanctum auth, and Lighthouse-powered schema composition.
            </p>
        </div>
    </div>
</section>

<!-- Quick Start -->
<section class="bg-white py-16">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold mb-8">Quick Start</h2>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="border border-slate-200 rounded-xl p-5">
                <div class="text-2xl mb-2">1</div>
                <h3 class="font-semibold mb-1">Endpoint</h3>
                <code class="code-font text-sm bg-pink-50 text-pink-700 px-2 py-1 rounded">POST /graphql</code>
            </div>
            <div class="border border-slate-200 rounded-xl p-5">
                <div class="text-2xl mb-2">2</div>
                <h3 class="font-semibold mb-1">Authentication</h3>
                <code class="code-font text-sm bg-pink-50 text-pink-700 px-2 py-1 rounded">Authorization: Bearer {token}</code>
            </div>
            <div class="border border-slate-200 rounded-xl p-5">
                <div class="text-2xl mb-2">3</div>
                <h3 class="font-semibold mb-1">Introspection</h3>
                <code class="code-font text-sm bg-pink-50 text-pink-700 px-2 py-1 rounded">{ __schema { types { name } } }</code>
            </div>
        </div>

        <!-- Example queries -->
        <div class="space-y-6">
            <div class="code-container">
                <div class="code-header"><span>Query — Fetch account with balance</span></div>
                <pre class="code-block p-4 text-slate-300"><span class="text-pink-400">query</span> {
  <span class="text-blue-300">account</span>(<span class="text-orange-300">id</span>: <span class="text-green-400">"abc-123"</span>) {
    id
    name
    balance
    currency
    status
    created_at
  }
}</pre>
            </div>

            <div class="code-container">
                <div class="code-header"><span>Query — Paginated accounts list</span></div>
                <pre class="code-block p-4 text-slate-300"><span class="text-pink-400">query</span> {
  <span class="text-blue-300">accounts</span>(<span class="text-orange-300">first</span>: <span class="text-green-400">10</span>, <span class="text-orange-300">page</span>: <span class="text-green-400">1</span>) {
    data {
      id
      name
      balance
    }
    paginatorInfo {
      total
      currentPage
      lastPage
    }
  }
}</pre>
            </div>

            <div class="code-container">
                <div class="code-header"><span>Mutation — Place exchange order</span></div>
                <pre class="code-block p-4 text-slate-300"><span class="text-pink-400">mutation</span> {
  <span class="text-blue-300">placeOrder</span>(<span class="text-orange-300">input</span>: {
    pair: <span class="text-green-400">"USDC/EUR"</span>
    side: <span class="text-green-400">BUY</span>
    type: <span class="text-green-400">LIMIT</span>
    amount: <span class="text-green-400">"100.00"</span>
    price: <span class="text-green-400">"0.92"</span>
  }) {
    id
    status
    filled_amount
    created_at
  }
}</pre>
            </div>

            <div class="code-container">
                <div class="code-header"><span>cURL example</span></div>
                <pre class="code-block p-4 text-green-400">curl -X POST https://api.zelta.app/graphql \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{"query": "{ accounts(first: 5) { data { id name balance } } }"}'</pre>
            </div>
        </div>
    </div>
</section>

<!-- Domain Schemas -->
<section class="bg-slate-50 py-16">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold mb-4">Domain Schemas</h2>
        <p class="text-slate-600 mb-8">39 domain-specific schemas composed into one unified GraphQL endpoint. Each domain provides queries, mutations, and types scoped to its bounded context.</p>

        @php
        $schemaGroups = [
            'Core Banking' => [
                ['name' => 'Account', 'file' => 'account', 'ops' => 'Queries + Mutations', 'desc' => 'Account CRUD, balances, freeze/unfreeze'],
                ['name' => 'Payment', 'file' => 'payment', 'ops' => 'Mutations', 'desc' => 'Initiate deposits, withdrawals'],
                ['name' => 'Banking', 'file' => 'banking', 'ops' => 'Queries + Mutations', 'desc' => 'Bank connections, transfers, sync'],
                ['name' => 'Asset', 'file' => 'asset', 'ops' => 'Queries', 'desc' => 'Exchange rates, asset definitions'],
                ['name' => 'Product', 'file' => 'product', 'ops' => 'Queries + Mutations', 'desc' => 'Product activation, catalog'],
            ],
            'Trading & Exchange' => [
                ['name' => 'Exchange', 'file' => 'exchange', 'ops' => 'Queries + Mutations + Subs', 'desc' => 'Orders, order book, trades'],
                ['name' => 'Basket', 'file' => 'basket', 'ops' => 'Queries + Mutations', 'desc' => 'GCU basket composition, rebalance'],
                ['name' => 'Stablecoin', 'file' => 'stablecoin', 'ops' => 'Queries + Mutations', 'desc' => 'Mint, redeem, collateral'],
            ],
            'Wallet & Payments' => [
                ['name' => 'Wallet', 'file' => 'wallet', 'ops' => 'Queries + Mutations + Subs', 'desc' => 'Multi-sig wallets, transfers, balances'],
                ['name' => 'Mobile Payment', 'file' => 'mobile-payment', 'ops' => 'Queries + Mutations', 'desc' => 'Payment intents, receipts'],
                ['name' => 'Card Issuance', 'file' => 'card-issuance', 'ops' => 'Queries + Mutations', 'desc' => 'Virtual/physical card provisioning'],
                ['name' => 'Relayer', 'file' => 'relayer', 'ops' => 'Mutations', 'desc' => 'Smart accounts, gas sponsorship'],
                ['name' => 'X402', 'file' => 'x402', 'ops' => 'Queries + Mutations', 'desc' => 'Monetized endpoints, spending limits'],
            ],
            'Compliance & Security' => [
                ['name' => 'Compliance', 'file' => 'compliance', 'ops' => 'Queries + Mutations', 'desc' => 'KYC, AML checks, alerts'],
                ['name' => 'Fraud', 'file' => 'fraud', 'ops' => 'Queries + Mutations', 'desc' => 'Fraud cases, detection, escalation'],
                ['name' => 'RegTech', 'file' => 'regtech', 'ops' => 'Queries', 'desc' => 'Regulatory reporting, health checks'],
                ['name' => 'Regulatory', 'file' => 'regulatory', 'ops' => 'Queries + Mutations', 'desc' => 'Filing submissions, reports'],
            ],
            'DeFi & Cross-Chain' => [
                ['name' => 'DeFi', 'file' => 'defi', 'ops' => 'Queries + Mutations', 'desc' => 'Positions, lending, staking'],
                ['name' => 'CrossChain', 'file' => 'crosschain', 'ops' => 'Queries + Mutations + Subs', 'desc' => 'Bridge transfers across chains'],
                ['name' => 'Lending', 'file' => 'lending', 'ops' => 'Queries + Mutations', 'desc' => 'Loan applications, approval'],
                ['name' => 'Treasury', 'file' => 'treasury', 'ops' => 'Queries + Mutations + Subs', 'desc' => 'Portfolios, rebalancing, NAV'],
            ],
            'Identity & Privacy' => [
                ['name' => 'Privacy', 'file' => 'privacy', 'ops' => 'Queries + Mutations', 'desc' => 'ZK proofs, Merkle trees, delegated proofs'],
                ['name' => 'Key Management', 'file' => 'key-management', 'ops' => 'Queries + Mutations', 'desc' => 'Key shards, Shamir splitting, recovery'],
                ['name' => 'Trust Cert', 'file' => 'trust-cert', 'ops' => 'Queries + Mutations', 'desc' => 'Verifiable credentials, certificates'],
                ['name' => 'Commerce', 'file' => 'commerce', 'ops' => 'Queries + Mutations', 'desc' => 'Soulbound tokens, merchant onboarding'],
            ],
            'AI & Agents' => [
                ['name' => 'AI', 'file' => 'ai', 'ops' => 'Queries + Mutations', 'desc' => 'Conversations, prompt templates, LLM'],
                ['name' => 'Agent Protocol', 'file' => 'agent-protocol', 'ops' => 'Queries + Mutations', 'desc' => 'DID, A2A messaging, reputation'],
            ],
            'Platform' => [
                ['name' => 'User', 'file' => 'user', 'ops' => 'Queries + Mutations', 'desc' => 'Profiles, preferences, analytics'],
                ['name' => 'Mobile', 'file' => 'mobile', 'ops' => 'Queries + Mutations', 'desc' => 'Device management, sessions'],
                ['name' => 'Governance', 'file' => 'governance', 'ops' => 'Queries + Mutations', 'desc' => 'Polls, voting, proposals'],
                ['name' => 'Rewards', 'file' => 'rewards', 'ops' => 'Queries + Mutations', 'desc' => 'Quests, XP, shop, streaks'],
                ['name' => 'Plugin', 'file' => 'plugin', 'ops' => 'Queries + Mutations', 'desc' => 'Plugin management, marketplace'],
                ['name' => 'Batch', 'file' => 'batch', 'ops' => 'Queries + Mutations', 'desc' => 'Batch job operations'],
                ['name' => 'CGO', 'file' => 'cgo', 'ops' => 'Queries + Mutations', 'desc' => 'Investment operations'],
                ['name' => 'Custodian', 'file' => 'custodian', 'ops' => 'Queries', 'desc' => 'Custodian account info'],
                ['name' => 'Financial Institution', 'file' => 'financial-institution', 'ops' => 'Queries + Mutations', 'desc' => 'Partner onboarding, BaaS'],
            ],
        ];
        @endphp

        <div class="space-y-6">
            @foreach($schemaGroups as $group => $schemas)
            <div>
                <h3 class="font-semibold text-sm text-pink-700 uppercase tracking-wider mb-3">{{ $group }}</h3>
                <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                    <table class="w-full text-sm">
                        <tbody class="divide-y divide-slate-100">
                            @foreach($schemas as $s)
                            <tr>
                                <td class="px-4 py-2.5 font-medium w-40">{{ $s['name'] }}</td>
                                <td class="px-4 py-2.5"><code class="code-font text-xs bg-slate-100 px-1.5 py-0.5 rounded">{{ $s['file'] }}.graphql</code></td>
                                <td class="px-4 py-2.5 text-slate-500 text-xs">{{ $s['ops'] }}</td>
                                <td class="px-4 py-2.5 text-slate-600">{{ $s['desc'] }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            @endforeach
        </div>
    </div>
</section>

<!-- Subscriptions -->
<section class="bg-white py-16">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold mb-4">Real-Time Subscriptions</h2>
        <p class="text-slate-600 mb-8">Subscribe to live data streams via WebSocket. Powered by Pusher/Soketi protocol.</p>

        @php
        $subs = [
            ['name' => 'orderBookUpdated', 'args' => 'pair: String!', 'desc' => 'Live order book changes for a trading pair'],
            ['name' => 'tradeExecuted', 'args' => 'pair: String!', 'desc' => 'Trade executions for a pair'],
            ['name' => 'orderMatched', 'args' => 'pair: String', 'desc' => 'Order matching events'],
            ['name' => 'walletBalanceUpdated', 'args' => 'wallet_id: ID!', 'desc' => 'Wallet balance changes'],
            ['name' => 'portfolioRebalanced', 'args' => 'portfolio_id: String', 'desc' => 'Portfolio rebalancing events'],
            ['name' => 'paymentStatusChanged', 'args' => 'payment_id: ID', 'desc' => 'Payment status updates'],
            ['name' => 'bridgeTransferCompleted', 'args' => 'transfer_id: ID', 'desc' => 'Cross-chain bridge completions'],
        ];
        @endphp

        <div class="space-y-2 mb-8">
            @foreach($subs as $sub)
            <div class="flex items-center gap-3 border border-slate-200 rounded-lg px-4 py-3">
                <span class="code-font text-sm font-semibold text-pink-600 w-56">{{ $sub['name'] }}</span>
                <code class="code-font text-xs bg-slate-100 px-2 py-0.5 rounded text-slate-600">{{ $sub['args'] }}</code>
                <span class="text-sm text-slate-500 flex-1 text-right">{{ $sub['desc'] }}</span>
            </div>
            @endforeach
        </div>

        <div class="code-container">
            <div class="code-header"><span>Subscription example</span></div>
            <pre class="code-block p-4 text-slate-300"><span class="text-pink-400">subscription</span> {
  <span class="text-blue-300">orderBookUpdated</span>(<span class="text-orange-300">pair</span>: <span class="text-green-400">"USDC/EUR"</span>) {
    bids { price amount }
    asks { price amount }
    updated_at
  }
}</pre>
        </div>
    </div>
</section>

<!-- Rate Limits & Security -->
<section class="bg-slate-50 py-16">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
        <h2 class="text-3xl font-bold mb-8">Rate Limits & Security</h2>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-white rounded-xl border border-slate-200 p-6">
                <h3 class="font-semibold text-lg mb-4">Rate Limits</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between"><span class="text-slate-600">Guest requests</span><code class="code-font bg-slate-100 px-2 py-0.5 rounded">30 / min</code></div>
                    <div class="flex justify-between"><span class="text-slate-600">Authenticated requests</span><code class="code-font bg-slate-100 px-2 py-0.5 rounded">120 / min</code></div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-6">
                <h3 class="font-semibold text-lg mb-4">Query Limits</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between"><span class="text-slate-600">Max complexity</span><code class="code-font bg-slate-100 px-2 py-0.5 rounded">200</code></div>
                    <div class="flex justify-between"><span class="text-slate-600">Max depth</span><code class="code-font bg-slate-100 px-2 py-0.5 rounded">10 levels</code></div>
                    <div class="flex justify-between"><span class="text-slate-600">Max query cost</span><code class="code-font bg-slate-100 px-2 py-0.5 rounded">500</code></div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-6">
                <h3 class="font-semibold text-lg mb-4">Scalars</h3>
                <div class="space-y-3 text-sm">
                    <div class="flex justify-between"><span class="text-slate-600"><code class="code-font">Date</code></span><code class="code-font text-xs bg-slate-100 px-2 py-0.5 rounded">Y-m-d (2026-03-15)</code></div>
                    <div class="flex justify-between"><span class="text-slate-600"><code class="code-font">DateTime</code></span><code class="code-font text-xs bg-slate-100 px-2 py-0.5 rounded">Y-m-d H:i:s</code></div>
                </div>
            </div>
            <div class="bg-white rounded-xl border border-slate-200 p-6">
                <h3 class="font-semibold text-lg mb-4">Multi-Tenancy</h3>
                <p class="text-sm text-slate-600">Operations are scoped to the authenticated user's team via the <code class="code-font bg-slate-100 px-1 rounded">@tenant</code> directive. No cross-tenant data leakage.</p>
            </div>
        </div>
    </div>
</section>

<!-- Back to Developers -->
<section class="bg-white py-12">
    <div class="max-w-5xl mx-auto px-4 text-center">
        <a href="{{ route('developers') }}" class="inline-flex items-center gap-2 text-pink-600 hover:text-pink-800 font-medium">
            <svg class="w-4 h-4 rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            Back to Developer Hub
        </a>
    </div>
</section>

@endsection
