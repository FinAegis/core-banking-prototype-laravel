@extends('layouts.public')

@section('title', 'Governance Module Demo - ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'Governance Module Demo',
        'description' => 'Governance module: weighted-voting proposals and tallying, shown with simulated votes in the GCU demo.',
        'keywords' => 'governance module, weighted voting, proposals, GCU demo, ' . config('brand.name', 'Zelta'),
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Features', 'url' => url('/features')],
        ['name' => 'Governance Module', 'url' => url('/features/governance')]
    ]" />
@endsection


@section('content')

    <!-- Hero Section -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                <h1 class="font-display text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">Governance Module</h1>
                <p class="text-lg text-slate-400 max-w-3xl mx-auto">
                    Governance module demo: weighted-voting proposals with transparent tallying. All votes in the demo are simulated.
                </p>
                {{-- F4 GCU disclaimer (see docs/REGULATORY-CLAIMS.md) --}}
                <div class="mt-8 max-w-3xl mx-auto bg-amber-500/10 border border-amber-500/20 rounded-xl p-4 text-sm text-slate-300 text-left" role="note">
                    The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under MiCA (Title III); no such authorisation is held.
                </div>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
    </section>

    <!-- Overview Section -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-12 max-w-3xl mx-auto text-center">
                <div>
                    <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-6">Overview</h2>
                    <p class="text-lg text-slate-500">
                        The governance module lets a deployment run weighted-voting proposals. In the GCU demo, all votes are simulated.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- How Voting Works -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">How Voting Works</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="vote-card card-feature !p-8">
                    <div class="text-4xl font-bold text-indigo-600 mb-4">1</div>
                    <h3 class="text-xl font-bold mb-3">Proposal Creation</h3>
                    <p class="text-slate-500">
                        Proposals are created with a description, a rationale and proposed demo basket weights.
                    </p>
                </div>
                
                <div class="vote-card card-feature !p-8">
                    <div class="text-4xl font-bold text-purple-600 mb-4">2</div>
                    <h3 class="text-xl font-bold mb-3">Preview Period</h3>
                    <p class="text-slate-500">
                        Upcoming proposals can be previewed before voting opens.
                    </p>
                </div>
                
                <div class="vote-card card-feature !p-8">
                    <div class="text-4xl font-bold text-green-600 mb-4">3</div>
                    <h3 class="text-xl font-bold mb-3">Simulated Voting & Tallying</h3>
                    <p class="text-slate-500">
                        Demo accounts cast simulated votes. Results are tallied against configurable participation and approval thresholds.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- What You Vote On -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">Example Proposal Types (demo)</h2>
            
            <div class="grid grid-cols-1 gap-8 max-w-3xl mx-auto">
                <div class="card-feature !p-8">
                    <h3 class="text-2xl font-bold mb-6 text-indigo-900">Demo Basket</h3>
                    <ul class="space-y-4">
                        <li class="flex items-start">
                            <svg class="w-6 h-6 text-indigo-600 mr-3 mt-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold mb-1">GCU Demo Basket Composition</h4>
                                <p class="text-slate-600">Proposed currency weightings for the demo basket (simulated votes; no monetary effect)</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Transparency -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-center text-slate-900 mb-12">Transparency in the Demo</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div class="w-16 h-16 bg-indigo-50 rounded-lg flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Public Proposals</h3>
                    <p class="text-slate-500">All proposals and their details are publicly accessible before, during, and after voting.</p>
                </div>
                
                <div class="text-center">
                    <div class="w-16 h-16 bg-purple-50 rounded-lg flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Recorded Votes</h3>
                    <p class="text-slate-500">Demo votes are stored in the application database with a hash signature; they are not recorded on a blockchain.</p>
                </div>
                
                <div class="text-center">
                    <div class="w-16 h-16 bg-green-50 rounded-lg flex items-center justify-center mx-auto mb-6">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold mb-3">Demo Execution</h3>
                    <p class="text-slate-500">Approved demo proposals update the simulated demo basket only.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-dot-pattern"></div>
        <div class="relative max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 py-20">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Explore the governance demo</h2>
            <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
                Votes in the GCU demo are simulated and have no monetary effect.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="btn-primary px-8 py-4 text-lg">
                    Try the demo
                </a>
            </div>
        </div>
    </section>

@endsection