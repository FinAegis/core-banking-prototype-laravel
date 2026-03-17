@extends('layouts.public')

@section('title', config('brand.name', 'Zelta') . ' Treasury - Enterprise Cash Management')

@section('seo')
    @include('partials.seo', [
        'title' => config('brand.name', 'Zelta') . ' Treasury - Enterprise Cash Management',
        'description' => config('brand.name', 'Zelta') . ' Treasury - Multi-bank cash management, FX optimization, and yield strategies for enterprises. Maximize returns while minimizing risk.',
        'keywords' => config('brand.name', 'Zelta') . ' Treasury, cash management, FX optimization, corporate treasury, yield optimization',
    ])
@endsection


@section('content')

        <!-- Hero Section -->
        <section class="pt-16 bg-fa-navy text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
                <div class="text-center">
                    <div class="inline-flex items-center px-4 py-2 bg-white/10 backdrop-blur-sm rounded-full text-sm mb-6">
                        <span class="w-2 h-2 bg-green-400 rounded-full mr-2 animate-pulse"></span>
                        <span>Available in Sandbox</span>
                    </div>
                    <h1 class="text-5xl md:text-6xl font-bold mb-6">
                        {{ config('brand.name', 'Zelta') }} Treasury
                    </h1>
                    <p class="text-xl md:text-2xl text-slate-400 max-w-3xl mx-auto mb-8">
                        Enterprise-grade treasury management across multiple banks and currencies
                    </p>
                </div>
            </div>
            
            <!-- Wave SVG -->
            <div class="relative">
                <svg class="absolute bottom-0 w-full h-24 -mb-1 text-white" preserveAspectRatio="none" viewBox="0 0 1440 74">
                    <path fill="currentColor" d="M0,32L48,37.3C96,43,192,53,288,58.7C384,64,480,64,576,58.7C672,53,768,43,864,42.7C960,43,1056,53,1152,58.7C1248,64,1344,64,1392,64L1440,64L1440,74L1392,74C1344,74,1248,74,1152,74C1056,74,960,74,864,74C768,74,672,74,576,74C480,74,384,74,288,74C192,74,96,74,48,74L0,74Z"></path>
                </svg>
            </div>
        </section>

        <!-- Features Preview -->
        <section class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">Optimize Cash, Minimize Risk</h2>
                    <p class="text-xl text-slate-500 max-w-3xl mx-auto">
                        Intelligent treasury management that works across all your banking relationships
                    </p>
                </div>

                <div class="grid md:grid-cols-3 gap-8">
                    <div class="bg-gradient-to-br from-red-50 to-orange-50 rounded-2xl p-8 border border-red-200">
                        <div class="w-14 h-14 bg-red-600 rounded-xl flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-slate-900 mb-4">Multi-Bank Management</h3>
                        <ul class="space-y-2 text-slate-600">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Unified dashboard
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Automated sweeping
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Risk diversification
                            </li>
                        </ul>
                    </div>

                    <div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-2xl p-8 border border-blue-200">
                        <div class="w-14 h-14 bg-blue-600 rounded-xl flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-slate-900 mb-4">FX Optimization</h3>
                        <ul class="space-y-2 text-slate-600">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Best rate execution
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Hedging strategies
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Multi-currency netting
                            </li>
                        </ul>
                    </div>

                    <div class="bg-gradient-to-br from-green-50 to-emerald-50 rounded-2xl p-8 border border-green-200">
                        <div class="w-14 h-14 bg-green-600 rounded-xl flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-bold text-slate-900 mb-4">Analytics & Reporting</h3>
                        <ul class="space-y-2 text-slate-600">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Cash flow forecasting
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Yield optimization
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-2 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                Custom dashboards
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </section>

        <!-- Cross-Chain Yield Callout -->
        <section class="py-12 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="bg-gradient-to-r from-orange-50 to-teal-50 rounded-2xl p-8 flex flex-col md:flex-row items-center justify-between">
                    <div>
                        <h3 class="text-2xl font-bold text-slate-900 mb-2">Cross-Chain Yield & Multi-Chain Portfolio</h3>
                        <p class="text-slate-500 max-w-xl">
                            Optimize treasury yields across multiple blockchains. Access DeFi lending, staking, and yield farming with automated cross-chain portfolio rebalancing.
                        </p>
                    </div>
                    <a href="{{ route('features.show', 'crosschain-defi') }}" class="mt-4 md:mt-0 inline-flex items-center px-6 py-3 bg-orange-600 text-white rounded-lg font-semibold hover:bg-orange-700 transition-colors flex-shrink-0">
                        Explore Cross-Chain & DeFi
                        <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                        </svg>
                    </a>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="bg-fa-navy relative overflow-hidden">
            <div class="absolute inset-0 bg-dot-pattern"></div>
            <div class="relative max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 py-20">
                <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Transform Your Treasury Operations</h2>
                <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
                    Explore multi-bank cash management, yield optimization, and FX strategies in the sandbox
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('dashboard') }}" class="btn-primary px-8 py-4 text-lg">
                        Explore Treasury
                    </a>
                    <a href="{{ route('gcu') }}" class="btn-outline px-8 py-4 text-lg">
                        Global Currency Unit
                    </a>
                </div>
            </div>
        </section>

@endsection