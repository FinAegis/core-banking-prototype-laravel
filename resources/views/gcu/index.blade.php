@extends('layouts.public')

@section('title', 'Global Currency Unit (GCU) Demo | ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'Global Currency Unit (GCU) Demo',
        'description' => 'GCU demo — a reference implementation of a basket-referenced unit built with FinAegis. Explore the illustrative basket composition and how the demo works.',
        'keywords' => 'GCU demo, global currency unit, basket-referenced unit, reference implementation, FinAegis',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="gcu" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'GCU demo', 'url' => url('/gcu')]
    ]" />
@endsection

@push('styles')
<style>
    .composition-bar {
        transition: width 1.5s ease-out;
    }
    .currency-card {
        transition: all 0.3s ease;
    }
    .currency-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 24px rgba(0,0,0,0.1);
    }
</style>
@endpush

@section('content')

    <!-- Hero Section -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="absolute top-1/4 left-1/4 w-96 h-96 bg-indigo-500/8 rounded-full blur-[120px]"></div>
        <div class="absolute bottom-1/4 right-1/4 w-80 h-80 bg-teal-500/6 rounded-full blur-[100px]"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-28">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div>
                    <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-amber-500/10 border border-amber-500/20 text-sm text-amber-400 mb-8">
                        <span class="w-1.5 h-1.5 bg-amber-400 rounded-full"></span>
                        Software demo
                    </div>
                    <h1 class="font-display text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">
                        Global Currency <span class="text-gradient">Unit (GCU)</span>
                    </h1>
                    <p class="text-lg text-slate-400 mb-6 max-w-xl">
                        GCU demo — a reference implementation of a basket-referenced unit built with FinAegis.
                    </p>
                    {{-- F4 GCU disclaimer (see docs/REGULATORY-CLAIMS.md) --}}
                    <div class="bg-amber-500/10 border border-amber-500/20 rounded-xl p-4 mb-8 max-w-xl text-sm text-slate-300" role="note">
                        The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under MiCA (Title III); no such authorisation is held.
                    </div>
                    <div class="flex flex-col sm:flex-row gap-4 mb-12">
                        <a href="#how-it-works" class="btn-outline px-8 py-4 text-base font-semibold">
                            How the demo works
                        </a>
                    </div>

                    <!-- Stats Row -->
                    <div class="grid grid-cols-1 gap-6 max-w-xs">
                        <div class="bg-white/5 border border-white/[0.06] rounded-xl p-4">
                            <div class="text-3xl font-bold text-white">{{ count($compositionData['composition'] ?? config('platform.gcu.composition')) }}</div>
                            <div class="text-slate-400 text-sm mt-1">Currencies</div>
                        </div>
                    </div>
                </div>

                <!-- GCU Symbol Card -->
                <div class="flex justify-center lg:justify-end">
                    <div class="bg-white rounded-2xl p-8 shadow-2xl w-full max-w-sm">
                        <div class="text-center mb-6">
                            <div class="text-7xl font-bold bg-clip-text text-transparent bg-gradient-to-br from-indigo-500 to-purple-600 mb-4">Ǥ</div>
                            <h3 class="text-2xl font-bold text-slate-900 mb-1">Global Currency Unit</h3>
                            <p class="text-slate-500 text-sm">Software demonstration</p>
                        </div>
                        <div class="bg-slate-50 rounded-xl p-4">
                            <p class="text-xs text-slate-500 mb-1 text-center">Illustrative demo value (simulated)</p>
                            @php
                                $gcuValueUSD = 1.0975;
                                $usdToEur = 0.92;
                                $gcuValueEUR = $gcuValueUSD * $usdToEur;
                            @endphp
                            <p class="text-2xl font-bold text-indigo-600 text-center">1 Ǥ = €{{ number_format($gcuValueEUR, 4) }}</p>
                            <p class="text-xs text-slate-400 mt-1 text-center">No monetary value — demo only</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
    </section>

    <!-- Basket Composition Section -->
    <section id="composition" class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">Demo Basket Composition</h2>
                <p class="text-lg text-slate-500 max-w-2xl mx-auto">Demo composition — weights and values are illustrative</p>
            </div>

            <!-- Performance Metrics -->
            @if(isset($compositionData['performance']))
            <div class="max-w-4xl mx-auto mb-12">
                <div class="bg-slate-50 border border-slate-100 rounded-2xl p-6">
                    <div class="grid grid-cols-1 gap-6 text-center">
                        <div>
                            <div class="text-xs text-slate-500 mb-1 uppercase tracking-wider">Simulated Value</div>
                            <div class="text-2xl font-bold text-slate-900">Ǥ{{ number_format($compositionData['performance']['value'], 4) }}</div>
                        </div>
                    </div>
                    @if(isset($compositionData['last_updated']))
                    <div class="text-center mt-4 text-xs text-slate-400">
                        Last updated: {{ \Carbon\Carbon::parse($compositionData['last_updated'])->diffForHumans() }}
                    </div>
                    @endif
                </div>
            </div>
            @endif

            <!-- Composition Visual -->
            <div class="max-w-5xl mx-auto">
                <div class="grid lg:grid-cols-2 gap-12 items-center mb-12">
                    <!-- Pie Chart -->
                    <div class="card-feature !p-8">
                        <svg viewBox="0 0 200 200" class="w-full h-72">
                            @php
                                $composition = $compositionData['composition'] ?? config('platform.gcu.composition');
                                $colors = [
                                    'USD' => '#4f46e5',
                                    'EUR' => '#7c3aed',
                                    'GBP' => '#9333ea',
                                    'CHF' => '#0d9488',
                                    'JPY' => '#0891b2',
                                    'XAU' => '#d97706'
                                ];
                                $startAngle = 0;
                                $cx = 100;
                                $cy = 100;
                                $r = 80;
                            @endphp
                            @foreach($composition as $currency => $percentage)
                                @php
                                    $angle = ($percentage / 100) * 360;
                                    $endAngle = $startAngle + $angle;
                                    $largeArcFlag = $angle > 180 ? 1 : 0;
                                    $x1 = $cx + $r * cos(deg2rad($startAngle));
                                    $y1 = $cy + $r * sin(deg2rad($startAngle));
                                    $x2 = $cx + $r * cos(deg2rad($endAngle));
                                    $y2 = $cy + $r * sin(deg2rad($endAngle));
                                @endphp
                                <path d="M {{ $cx }} {{ $cy }} L {{ $x1 }} {{ $y1 }} A {{ $r }} {{ $r }} 0 {{ $largeArcFlag }} 1 {{ $x2 }} {{ $y2 }} Z"
                                      fill="{{ $colors[$currency] ?? '#6366f1' }}"
                                      class="hover:opacity-80 transition-opacity"
                                      stroke="white"
                                      stroke-width="2">
                                    <title>{{ $currency }}: {{ $percentage }}%</title>
                                </path>
                                @php $startAngle = $endAngle; @endphp
                            @endforeach
                            <circle cx="100" cy="100" r="50" fill="white" />
                            <text x="100" y="105" text-anchor="middle" dominant-baseline="middle" font-size="28" font-weight="bold" fill="#1e293b">Ǥ</text>
                        </svg>
                        <p class="text-center text-slate-500 text-sm mt-2">Illustrative demo basket</p>
                    </div>

                    <!-- Composition List -->
                    <div class="space-y-4">
                        @php
                            $flags = ['USD' => '🇺🇸', 'EUR' => '🇪🇺', 'GBP' => '🇬🇧', 'CHF' => '🇨🇭', 'JPY' => '🇯🇵', 'XAU' => '🏆'];
                            $names = ['USD' => 'US Dollar', 'EUR' => 'Euro', 'GBP' => 'British Pound', 'CHF' => 'Swiss Franc', 'JPY' => 'Japanese Yen', 'XAU' => 'Gold (Troy Oz)'];
                        @endphp

                        @foreach($composition as $currency => $percentage)
                        <div class="currency-card bg-slate-50 rounded-xl p-4">
                            <div class="flex items-center justify-between mb-3">
                                <div class="flex items-center space-x-3">
                                    <span class="text-2xl">{{ $flags[$currency] ?? '' }}</span>
                                    <div>
                                        <h4 class="font-semibold text-slate-900 text-sm">{{ $names[$currency] ?? $currency }}</h4>
                                        <p class="text-xs text-slate-500">{{ $currency }}</p>
                                    </div>
                                </div>
                                <div class="text-right">
                                    <span class="text-xl font-bold text-slate-900">{{ $percentage }}%</span>
                                </div>
                            </div>
                            <div class="relative h-2 bg-slate-200 rounded-full overflow-hidden">
                                <div class="composition-bar absolute inset-0 rounded-full"
                                     style="width: {{ $percentage }}%; background-color: {{ $colors[$currency] ?? '#6366f1' }}"></div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>

                <!-- Voting Information -->
                <div class="bg-slate-50 border border-slate-100 rounded-2xl p-8 text-center">
                    @if(config('platform.gcu.voting_enabled'))
                        @php
                            $nextVoting = \Carbon\Carbon::parse(config('platform.gcu.next_voting_date'));
                            $daysUntil = now()->diffInDays($nextVoting);
                        @endphp
                        <h3 class="text-2xl font-bold text-slate-900 mb-4">Next Demo Basket Poll (simulated)</h3>
                        <div class="flex justify-center space-x-8 mb-6">
                            <div>
                                <div class="text-4xl font-bold text-indigo-600">{{ $daysUntil }}</div>
                                <div class="text-slate-500 text-sm">Days</div>
                            </div>
                            <div>
                                <div class="text-4xl font-bold text-indigo-600">{{ now()->diffInHours($nextVoting) % 24 }}</div>
                                <div class="text-slate-500 text-sm">Hours</div>
                            </div>
                            <div>
                                <div class="text-4xl font-bold text-indigo-600">{{ now()->diffInMinutes($nextVoting) % 60 }}</div>
                                <div class="text-slate-500 text-sm">Minutes</div>
                            </div>
                        </div>
                        <a href="{{ route('gcu.voting.index') }}" class="btn-primary px-6 py-3">
                            View demo proposals
                        </a>
                    @else
                        <h3 class="text-2xl font-bold text-slate-900 mb-6">Governance demo</h3>
                        <p class="text-slate-500 text-sm max-w-2xl mx-auto">
                            Any GCU balances, conversions or basket "votes" shown in the demo are simulated.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="py-20 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">How the GCU Demo Works</h2>
                <p class="text-lg text-slate-500 max-w-2xl mx-auto">
                    In the demo, GCU balances, conversions and basket "votes" are simulated.
                </p>
            </div>

            <!-- Detail Cards -->
            <div class="grid grid-cols-1 gap-8 max-w-3xl mx-auto">
                <div class="card-feature !p-8">
                    <h3 class="text-2xl font-bold text-slate-900 mb-6">The Governance Demo</h3>
                    <div class="space-y-5">
                        <div class="flex items-start gap-4">
                            <div class="flex-shrink-0 w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center">
                                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                                </svg>
                            </div>
                            <div>
                                <h4 class="font-semibold text-slate-900 mb-1">Simulated Basket Polls</h4>
                                <p class="text-slate-500 text-sm">Basket "votes" in the demo are simulated and have no monetary effect.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-dot-pattern"></div>
        <div class="relative max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 py-20">
            <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Explore the GCU demo</h2>
            <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
                GCU demo — a reference implementation of a basket-referenced unit built with FinAegis.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('platform') }}" class="btn-outline px-8 py-4 text-base">
                    Learn About Platform
                </a>
            </div>
            <div class="mt-10 text-sm text-slate-500">
                Questions? Contact our team at <a href="mailto:{{ config('brand.support_email', 'info@zelta.app') }}" class="underline hover:text-slate-300 transition-colors">{{ config('brand.support_email', 'info@zelta.app') }}</a>
            </div>
        </div>
    </section>

@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const match = entry.target.getAttribute('style').match(/width: ([\d.]+%)/);
                    if (match) {
                        entry.target.style.width = match[1];
                    }
                }
            });
        });

        document.querySelectorAll('.composition-bar').forEach(bar => {
            observer.observe(bar);
        });
    });
</script>
@endpush
