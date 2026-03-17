@extends('layouts.public')

@section('title', 'Cross-Chain & DeFi - ' . config('brand.name', 'Zelta') . '')

@section('seo')
    @include('partials.seo', [
        'title' => 'Cross-Chain & DeFi Integration',
        'description' => 'Bridge assets across blockchains and access DeFi protocols with ' . config('brand.name', 'Zelta') . '. Wormhole, LayerZero, Axelar bridges with Uniswap, Aave, Curve, and Lido connectors.',
        'keywords' => 'cross-chain, DeFi, bridge protocol, Wormhole, LayerZero, Axelar, Uniswap, Aave, Curve, Lido, DEX aggregation, yield optimization, flash loans, multi-chain portfolio',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => url('/features')],
        ['name' => 'Cross-Chain & DeFi', 'url' => url('/features/crosschain-defi')]
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
                <h1 class="font-display text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">Cross-Chain & DeFi Integration</h1>
                <p class="text-lg text-slate-400 max-w-3xl mx-auto">
                    Bridge assets seamlessly across blockchains and access leading DeFi protocols. Powered by Wormhole, LayerZero, and Axelar bridge providers with DEX aggregation, lending, staking, and yield optimization.
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
                Transfer assets between chains with confidence using battle-tested bridge providers. Automatic fee comparison ensures the best route for every transfer.
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
                        Generic message-passing protocol supporting 30+ chains. Ideal for high-value transfers with guardian-validated security.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            30+ supported chains
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Guardian network validation
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Token and NFT bridging
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
                        Ultra-light node protocol for omnichain interoperability. Low-cost messaging with configurable security via oracles and relayers.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Omnichain messaging
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Configurable security model
                        </li>
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Low-latency transfers
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
                        Universal overlay network connecting all blockchains. Proof-of-stake security with General Message Passing for complex cross-chain logic.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-center">
                            <svg class="w-4 h-4 text-green-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            PoS-validated security
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
                            EVM and Cosmos support
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- DeFi Protocols -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-4">DeFi Protocol Connectors</h2>
            <p class="text-lg text-slate-500 text-center max-w-2xl mx-auto mb-12">
                Access the most trusted DeFi protocols through a unified API. Swap, lend, stake, and optimize yield without managing multiple integrations.
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
                    <span class="inline-block bg-pink-100 text-pink-800 text-xs font-medium px-2 py-1 rounded mb-3">DEX</span>
                    <p class="text-slate-500 text-sm">
                        Concentrated liquidity DEX with optimal price execution. Multi-hop routing across token pairs with slippage protection.
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
                        Decentralized lending and borrowing protocol. Supply assets to earn yield or borrow against collateral with flash loan support.
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
                        Optimized AMM for stablecoin and pegged asset swaps. Minimal slippage with deep liquidity pools and gauge-boosted yields.
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
                        Liquid staking for ETH and other PoS assets. Earn staking rewards while maintaining liquidity through stETH derivative tokens.
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
                A unified REST API for cross-chain bridging and DeFi operations. All endpoints require Bearer token authentication.
            </p>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <!-- Cross-Chain Endpoints -->
                <div class="protocol-card card-feature !p-8">
                    <h3 class="text-2xl font-bold mb-4">Cross-Chain Bridging</h3>
                    <p class="text-slate-500 mb-6">Discover chains, get quotes, and initiate bridge transfers across networks.</p>
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

                <!-- DeFi Endpoints -->
                <div class="protocol-card card-feature !p-8">
                    <h3 class="text-2xl font-bold mb-4">DeFi Operations</h3>
                    <p class="text-slate-500 mb-6">Swap tokens, track portfolios, and discover the best yield opportunities.</p>
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
                        <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg">
                            <div class="flex items-center">
                                <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2 py-1 rounded">GET</span>
                                <span class="ml-3 font-mono text-sm">/api/v1/defi/yield/best</span>
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
const response = await fetch('https://api.finaegis.com/api/v1/crosschain/bridge/quote', {
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
                        Unified view of assets across all supported blockchains. Real-time balance aggregation, historical performance tracking, and chain-specific analytics.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Aggregate balances from Ethereum, Polygon, Arbitrum, BSC, and more
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Real-time USD valuation with price feeds
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Chain distribution and exposure analysis
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
                        Swap tokens across different blockchains in a single transaction. Bridge and DEX operations are orchestrated automatically for the best execution price.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Atomic bridge-and-swap in one API call
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Automatic slippage protection and MEV resistance
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Multi-provider routing for optimal fees
                        </li>
                    </ul>
                </div>

                <!-- Yield Optimization -->
                <div class="card-feature !p-8">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-green-50 rounded-lg flex items-center justify-center mr-4">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-2xl font-bold">Yield Optimization</h3>
                    </div>
                    <p class="text-slate-500 mb-4">
                        Discover the best yield opportunities across chains and protocols. Risk-adjusted scoring compares APY, TVL, and protocol security to surface optimal strategies.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Cross-chain yield comparison engine
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Risk-adjusted APY scoring with protocol audits
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Automated position rebalancing suggestions
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
                        Execute uncollateralized loans within a single transaction. Ideal for arbitrage, collateral swaps, and liquidation strategies with zero upfront capital.
                    </p>
                    <ul class="space-y-2 text-sm text-gray-500">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Aave V3 flash loan integration
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Multi-asset flash loan batching
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Built-in arbitrage opportunity detection
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
                A unified orchestration layer routes operations through the optimal bridge and DeFi protocols based on cost, speed, and security requirements.
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
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Go Multi-Chain Today</h2>
            <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
                Bridge assets, access DeFi protocols, and optimize yield across blockchains with a single API integration.
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
