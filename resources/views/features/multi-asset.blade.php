@extends('layouts.public')

@section('title', 'Multi-Asset Support - ' . config('brand.name', 'Zelta') . '')

@section('seo')
    @include('partials.seo', [
        'title' => 'Multi-Asset Support',
        'description' => 'Hold and transact in multiple currencies and assets from a single account. Support for fiat, crypto, and commodities with seamless conversion.',
        'keywords' => 'multi-asset, multiple currencies, crypto, fiat, commodities, ' . config('brand.name', 'Zelta') . '',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => url('/features')],
        ['name' => 'Multi-Asset Support', 'url' => url('/features/multi-asset')]
    ]" />
@endsection


@section('content')

    <!-- Hero Section -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                <h1 class="font-display text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">Multi-Asset Support</h1>
                <p class="text-lg text-slate-400 max-w-3xl mx-auto">
                    One account for all your assets. Hold, transact, and convert between fiat currencies, cryptocurrencies, and commodities seamlessly.
                </p>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
    </section>

    <!-- Overview Section -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">All Your Assets in One Place</h2>
                <p class="text-lg text-slate-500 max-w-3xl mx-auto">
                    {{ config('brand.name', 'Zelta') }}'s multi-asset platform lets you manage diverse portfolios with the same ease as traditional banking.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Fiat Currencies -->
                <div class="asset-card bg-white border-2 border-indigo-100 rounded-xl p-8">
                    <div class="w-14 h-14 bg-indigo-50 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-4">Fiat Currencies</h3>
                    <p class="text-slate-500 mb-6">
                        Major global currencies with 2 decimal precision for traditional banking operations.
                    </p>
                    <div class="space-y-2">
                        <div class="flex items-center">
                            <span class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center text-sm font-bold mr-3">$</span>
                            <span>USD - US Dollar</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center text-sm font-bold mr-3">€</span>
                            <span>EUR - Euro</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center text-sm font-bold mr-3">£</span>
                            <span>GBP - British Pound</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center text-sm font-bold mr-3">¥</span>
                            <span>JPY - Japanese Yen</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center text-sm font-bold mr-3">Fr</span>
                            <span>CHF - Swiss Franc</span>
                        </div>
                    </div>
                </div>

                <!-- Cryptocurrencies -->
                <div class="asset-card bg-white border-2 border-purple-100 rounded-xl p-8">
                    <div class="w-14 h-14 bg-purple-50 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-4">Cryptocurrencies</h3>
                    <p class="text-slate-500 mb-6">
                        Popular digital assets with high precision (8 decimals) for accurate transactions.
                    </p>
                    <div class="space-y-2">
                        <div class="flex items-center">
                            <span class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center text-sm font-bold mr-3 text-orange-600">₿</span>
                            <span>BTC - Bitcoin</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-8 h-8 bg-gray-800 rounded-full flex items-center justify-center text-sm font-bold mr-3 text-white">Ξ</span>
                            <span>ETH - Ethereum</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center text-sm font-bold mr-3 text-green-600">₮</span>
                            <span>USDT - Tether</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center text-sm font-bold mr-3 text-blue-600">$</span>
                            <span>USDC - USD Coin</span>
                        </div>
                        <div class="text-gray-500 text-sm mt-2">+ Additional assets supported</div>
                    </div>
                </div>

                <!-- Commodities -->
                <div class="asset-card bg-white border-2 border-green-100 rounded-xl p-8">
                    <div class="w-14 h-14 bg-green-50 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path>
                        </svg>
                    </div>
                    <h3 class="text-2xl font-bold mb-4">Commodities</h3>
                    <p class="text-slate-500 mb-6">
                        Precious metals and other commodities for portfolio diversification.
                    </p>
                    <div class="space-y-2">
                        <div class="flex items-center">
                            <span class="w-8 h-8 bg-yellow-100 rounded-full flex items-center justify-center text-sm font-bold mr-3 text-yellow-600">Au</span>
                            <span>XAU - Gold</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center text-sm font-bold mr-3">Ag</span>
                            <span>XAG - Silver</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center text-sm font-bold mr-3">Pt</span>
                            <span>XPT - Platinum</span>
                        </div>
                        <div class="flex items-center">
                            <span class="w-8 h-8 bg-gray-100 rounded-full flex items-center justify-center text-sm font-bold mr-3">Pd</span>
                            <span>XPD - Palladium</span>
                        </div>
                        <div class="text-gray-500 text-sm mt-2">+ Oil, Gas, Agricultural</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">Multi-Asset Platform Features</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12">
                <div class="card-feature !p-8">
                    <h3 class="text-2xl font-bold mb-6">Seamless Conversion</h3>
                    <ul class="space-y-4">
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold mb-1">Instant Cross-Asset Transfers</h4>
                                <p class="text-slate-500">Convert between any supported assets with real-time exchange rates and automatic execution.</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold mb-1">Competitive Exchange Rates</h4>
                                <p class="text-slate-500">Low spreads with transparent pricing from multiple liquidity providers.</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold mb-1">No Hidden Fees</h4>
                                <p class="text-slate-500">Clear fee structure with all costs shown upfront before conversion.</p>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="card-feature !p-8">
                    <h3 class="text-2xl font-bold mb-6">Portfolio Management</h3>
                    <ul class="space-y-4">
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold mb-1">Unified Dashboard</h4>
                                <p class="text-slate-500">View all your assets in one place with real-time valuations and performance tracking.</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold mb-1">Automatic Rebalancing</h4>
                                <p class="text-slate-500">Set target allocations and let the system automatically rebalance your portfolio.</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold mb-1">Performance Analytics</h4>
                                <p class="text-slate-500">Detailed reports showing returns, volatility, and asset performance over time.</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Technical Implementation -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">How It Works</h2>
            
            <div class="max-w-4xl mx-auto">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <h3 class="text-xl font-bold mb-4">Asset Management</h3>
                        <ul class="space-y-3 text-slate-500">
                            <li>• Separate balance tracking for each asset type</li>
                            <li>• Precision handling (2-8 decimals based on asset)</li>
                            <li>• Real-time balance updates with atomic operations</li>
                            <li>• Historical balance tracking for all assets</li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold mb-4">Exchange Rate Engine</h3>
                        <ul class="space-y-3 text-slate-500">
                            <li>• Multiple rate providers for best pricing</li>
                            <li>• Automatic rate validation and age checking</li>
                            <li>• Cached rates for instant conversions</li>
                            <li>• Fallback mechanisms for high availability</li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold mb-4">Transaction Processing</h3>
                        <ul class="space-y-3 text-slate-500">
                            <li>• Cross-asset transfers with automatic conversion</li>
                            <li>• Transaction linking for audit trails</li>
                            <li>• Event sourcing for complete history</li>
                            <li>• Reversible transactions for error correction</li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-xl font-bold mb-4">Security & Compliance</h3>
                        <ul class="space-y-3 text-slate-500">
                            <li>• Asset-specific validation rules</li>
                            <li>• Regulatory compliance for each asset class</li>
                            <li>• Segregated storage for different asset types</li>
                            <li>• Complete audit trails for all operations</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- API Integration -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">Developer-Friendly APIs</h2>
            
            <div class="max-w-4xl mx-auto bg-gray-900 rounded-lg p-8 text-gray-300 font-mono text-sm">
                <div class="mb-4">
                    <span class="text-gray-500"># Get all asset balances</span><br>
                    GET /api/accounts/{uuid}/balances
                </div>
                <div class="mb-4">
                    <span class="text-gray-500"># Get specific asset balance</span><br>
                    GET /api/balances/{uuid}/{asset}
                </div>
                <div class="mb-4">
                    <span class="text-gray-500"># Convert between assets</span><br>
                    POST /api/exchange-rates/convert<br>
                    {<br>
                    &nbsp;&nbsp;"from": "USD",<br>
                    &nbsp;&nbsp;"to": "BTC",<br>
                    &nbsp;&nbsp;"amount": 1000.00<br>
                    }
                </div>
                <div>
                    <span class="text-gray-500"># Cross-asset transfer</span><br>
                    POST /api/transfers<br>
                    {<br>
                    &nbsp;&nbsp;"from_account": "uuid-1",<br>
                    &nbsp;&nbsp;"to_account": "uuid-2",<br>
                    &nbsp;&nbsp;"from_asset": "EUR",<br>
                    &nbsp;&nbsp;"to_asset": "USD",<br>
                    &nbsp;&nbsp;"amount": 500.00<br>
                    }
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-dot-pattern"></div>
        <div class="relative max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 py-20">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Manage All Your Assets in One Place</h2>
            <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
                Experience the future of multi-asset banking with {{ config('brand.name', 'Zelta') }}
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="btn-primary px-8 py-4 text-lg">
                    Get Started
                </a>
                <a href="{{ route('features') }}" class="btn-outline px-8 py-4 text-lg">
                    Explore Features
                </a>
            </div>
        </div>
    </section>

@endsection