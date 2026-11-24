@extends('layouts.public')

@section('title', 'Cross-Chain and Decentralised Finance - ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'Cross-Chain and Decentralised Finance Integration',
        'description' => 'The cross-chain module moves assets between blockchains through bridge adapters for Wormhole, Circle\'s Cross-Chain Transfer Protocol, LayerZero and Axelar. The decentralised finance module has connectors for Uniswap, Aave, Curve and Lido. Integration adapters for third-party APIs; no partnership or endorsement implied.',
        'keywords' => 'cross-chain, DeFi, bridge protocol, Wormhole, Circle CCTP, LayerZero, Axelar, Uniswap, Aave, Curve, Lido, decentralised exchange, flash loans, multi-chain portfolio',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => url('/features')],
        ['name' => 'Cross-Chain and Decentralised Finance', 'url' => url('/features/crosschain-defi')]
    ]" />
@endsection

@push('styles')
<style>
    .protocol-card {
        transition: all 0.3s ease;
    }
    .protocol-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 24px rgba(0,0,0,0.12);
    }
    .code-block {
        background: #1a202c;
        color: #e2e8f0;
        border-radius: 0.5rem;
        padding: 1.5rem;
        overflow-x: auto;
        font-family: 'Fira Code', monospace;
    }
</style>
@endpush

@section('content')

    <!-- Hero Section -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                <h1 class="font-display text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">Cross-Chain and Decentralised Finance Integration</h1>
                <p class="text-lg text-slate-400 max-w-3xl mx-auto">
                    The cross-chain module moves assets from one blockchain to another through bridge adapters. The decentralised finance module connects to decentralised exchanges, lending protocols and staking protocols. Integration adapters for third-party APIs; no partnership or endorsement implied.
                </p>
                <div class="mt-10 flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition">
                        Get Started
                    </a>
                    <a href="#api-endpoints" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">
                        Explore API
                    </a>
                </div>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
    </section>

    <!-- Bridge Protocols Overview -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Bridge Protocols</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                The module has bridge adapters for Wormhole, Circle's Cross-Chain Transfer Protocol, LayerZero and Axelar. Each adapter builds the contract calls and sends them through a blockchain node. The module compares the fees of all bridges before a transfer.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Wormhole -->
                <div class="protocol-card card-feature !p-8">
                    <div class="w-14 h-14 bg-indigo-50 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-3">Wormhole</h3>
                    <p class="text-slate-500 mb-4">
                        Wormhole is a message-passing protocol. A network of guardians confirms each message. The adapter encodes the contract calls and sends them through the platform's blockchain client.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Message passing between chains
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Messages confirmed by guardians
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Token transfers between chains
                        </li>
                    </ul>
                </div>

                <!-- LayerZero -->
                <div class="protocol-card card-feature !p-8">
                    <div class="w-14 h-14 bg-purple-50 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-3">LayerZero</h3>
                    <p class="text-slate-500 mb-4">
                        LayerZero is a messaging protocol that links many blockchains. Its security is set through oracles and relayers. The adapter uses LayerZero's token standard to move tokens.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Messages across many chains
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Security settings you can choose
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Token transfers between chains
                        </li>
                    </ul>
                </div>

                <!-- Axelar -->
                <div class="protocol-card card-feature !p-8">
                    <div class="w-14 h-14 bg-green-50 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-3">Axelar</h3>
                    <p class="text-slate-500 mb-4">
                        Axelar is a network that connects blockchains. Validators secure it through proof of stake. Its General Message Passing can carry contract calls from one chain to another.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Secured by proof of stake
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            General Message Passing
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Contract calls across chains
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Decentralised Finance Protocols -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">Decentralised Finance Protocol Connectors</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                One API connects to several third-party decentralised finance protocols. You can swap, lend and stake through it without building a separate integration for each protocol.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- Uniswap V3 -->
                <div class="protocol-card card-feature !p-8">
                    <div class="w-14 h-14 bg-pink-50 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Uniswap V3</h3>
                    <span class="inline-block bg-pink-100 text-pink-800 text-xs font-medium px-2 py-1 rounded mb-3">Exchange</span>
                    <p class="text-slate-500 text-sm">
                        Uniswap is a decentralised exchange. The adapter gets price quotes and builds swap transactions. It can route a swap through several pools and stops when the price moves past your slippage limit.
                    </p>
                </div>

                <!-- Aave V3 -->
                <div class="protocol-card card-feature !p-8">
                    <div class="w-14 h-14 bg-cyan-50 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Aave V3</h3>
                    <span class="inline-block bg-cyan-100 text-cyan-800 text-xs font-medium px-2 py-1 rounded mb-3">Lending</span>
                    <p class="text-slate-500 text-sm">
                        Aave is a lending protocol. The adapter can supply, borrow and repay assets. It also supports flash loans.
                    </p>
                </div>

                <!-- Curve -->
                <div class="protocol-card card-feature !p-8">
                    <div class="w-14 h-14 bg-yellow-50 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Curve Finance</h3>
                    <span class="inline-block bg-yellow-100 text-yellow-800 text-xs font-medium px-2 py-1 rounded mb-3">Stableswap</span>
                    <p class="text-slate-500 text-sm">
                        Curve is an exchange built for stablecoins and other pegged assets. The adapter swaps between them.
                    </p>
                </div>

                <!-- Lido -->
                <div class="protocol-card card-feature !p-8">
                    <div class="w-14 h-14 bg-blue-50 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-2">Lido</h3>
                    <span class="inline-block bg-blue-100 text-blue-800 text-xs font-medium px-2 py-1 rounded mb-3">Liquid Staking</span>
                    <p class="text-slate-500 text-sm">
                        Lido is a liquid staking protocol. The adapter stakes ether and receives stETH in return.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- API Endpoints -->
    <section id="api-endpoints" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">API Endpoints</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                One REST API covers bridging and decentralised finance. Every endpoint needs a bearer token.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Cross-Chain Endpoints -->
                <div class="protocol-card card-feature !p-8">
                    <h3 class="text-2xl font-bold mb-4">Cross-Chain Bridging</h3>
                    <p class="text-slate-500 mb-6">These endpoints list the chains, return quotes and start bridge transfers.</p>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2 py-1 rounded">GET</span>
                                <span class="ml-3 font-mono text-sm">/api/v1/crosschain/chains</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <span class="bg-green-100 text-green-800 text-xs font-medium px-2 py-1 rounded">POST</span>
                                <span class="ml-3 font-mono text-sm">/api/v1/crosschain/bridge/quote</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <span class="bg-green-100 text-green-800 text-xs font-medium px-2 py-1 rounded">POST</span>
                                <span class="ml-3 font-mono text-sm">/api/v1/crosschain/bridge/initiate</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Decentralised Finance Endpoints -->
                <div class="protocol-card card-feature !p-8">
                    <h3 class="text-2xl font-bold mb-4">Decentralised Finance Operations</h3>
                    <p class="text-slate-500 mb-6">These endpoints quote swaps and show portfolios.</p>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <span class="bg-green-100 text-green-800 text-xs font-medium px-2 py-1 rounded">POST</span>
                                <span class="ml-3 font-mono text-sm">/api/v1/defi/swap/quote</span>
                            </div>
                        </div>
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2 py-1 rounded">GET</span>
                                <span class="ml-3 font-mono text-sm">/api/v1/defi/portfolio</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Code Example -->
            <div class="mt-12 max-w-4xl mx-auto">
                <h3 class="text-xl font-bold mb-4">Example: Get Bridge Quote</h3>
                <div class="bg-gray-900 rounded-lg p-6 text-green-400 font-mono text-sm overflow-x-auto">
                    <pre><code>// Request a bridge quote across providers
const response = await fetch('{{ config('app.url') }}/api/v1/crosschain/bridge/quote', {
  method: 'POST',
  headers: {
    'Authorization': 'Bearer YOUR_API_TOKEN',
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    source_chain: 'ethereum',
    destination_chain: 'polygon',
    token: 'USDC',
    amount: '1000.00'
  })
});

const { quotes } = await response.json();
// quotes array sorted by best fee, includes Wormhole, LayerZero, Axelar options
console.log('Best route:', quotes[0].provider);
console.log('Fee:', quotes[0].fee);
console.log('ETA:', quotes[0].estimated_time);</code></pre>
                </div>
            </div>
        </div>
    </section>

    <!-- Technical Features -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">Technical Features</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Multi-Chain Portfolio -->
                <div class="card-feature !p-8">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-indigo-50 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold">Multi-Chain Portfolio</h3>
                    </div>
                    <p class="text-slate-500 mb-4">
                        The portfolio view shows assets from all supported blockchains in one place. It adds up the balances and tracks how they change over time.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Balances from Ethereum, Polygon, Arbitrum, Optimism, Base, BNB Smart Chain, Solana and Tron
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Values in US dollars from price feeds
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            How much is held on each chain
                        </li>
                    </ul>
                </div>

                <!-- Cross-Chain Swaps -->
                <div class="card-feature !p-8">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-purple-50 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"></path>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold">Cross-Chain Swaps</h3>
                    </div>
                    <p class="text-slate-500 mb-4">
                        A cross-chain swap moves a token to another blockchain and swaps it there. The module runs the bridge step and the swap step as one saga. If a step fails, the saga undoes the steps before it.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Bridge and swap in one API call
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Slippage limits on every swap
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Fee comparison across bridges
                        </li>
                    </ul>
                </div>

                <!-- Flash Loans -->
                <div class="card-feature !p-8">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-yellow-50 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold">Flash Loans</h3>
                    </div>
                    <p class="text-slate-500 mb-4">
                        A flash loan is borrowed and repaid within one transaction, so it needs no collateral. Developers use it for collateral swaps and similar strategies.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Flash loans through Aave V3
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Fee estimate before each loan
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Availability check for each chain
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Architecture Overview -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">How It Works</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                An orchestration layer chooses the bridge or protocol for each operation. It compares cost, speed and security.
            </p>

            <div class="max-w-4xl mx-auto">
                <div class="bg-gray-900 rounded-lg p-6 text-green-400 font-mono text-sm overflow-x-auto">
                    <pre><code>                    +------------------+
                    |   {{ config('brand.name', 'Zelta') }} API   |
                    +--------+---------+
                             |
              +--------------+--------------+
              |                             |
    +---------v----------+       +----------v---------+
    | CrossChain Domain  |       |    DeFi Domain     |
    +----+------+--------+       +---+------+---------+
         |      |      |             |      |      |
    +----v+ +---v--+ +-v----+   +---v--+ +-v---+ +-v---+
    |Worm | |Layer | |Axe   |   |Uni   | |Aave | |Curve|
    |hole | |Zero  | |lar   |   |swap  | |V3   | |     |
    +-----+ +------+ +------+   +------+ +-----+ +-----+
         \      |      /             \      |      /
          \     |     /               \     |     /
    +------v----v----v----+     +------v----v----v----+
    |   Bridge Routing    |     |   Swap Aggregation  |
    |  (Fee Comparison)   |     |  (Best Execution)   |
    +---------------------+     +---------------------+</code></pre>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-dot-pattern"></div>
        <div class="relative max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 py-20">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Try Cross-Chain Operations</h2>
            <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
                The sandbox runs these operations with demo adapters and test data. One API integration covers bridging and decentralised finance.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="btn-primary px-8 py-4 text-lg">
                    Start Building
                </a>
                <a href="{{ route('developers') }}" class="btn-outline px-8 py-4 text-lg">
                    Read the Docs
                </a>
            </div>
        </div>
    </section>

@endsection
