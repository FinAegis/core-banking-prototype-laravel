@extends('layouts.public')

@section('title', 'Pricing - Free and Open Source | ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'Pricing - Free and Open Source | ' . config('brand.name', 'Zelta'),
        'description' => config('brand.name', 'Zelta') . ' is free and open source under the Apache 2.0 license. You run it on your own servers, and help with your own deployment is available on request.',
        'keywords' => config('brand.name', 'Zelta') . ' pricing, open source banking, free core banking software, Apache 2.0, self-hosted banking platform, deployment help',
    ])

    {{-- Schema.org Markup --}}
    <x-schema type="software" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Pricing', 'url' => url('/pricing')]
    ]" />
@endsection

@section('content')

        <!-- Hero Section -->
        <section class="bg-fa-navy relative overflow-hidden">
            <div class="absolute inset-0 bg-grid-pattern"></div>
            <div class="absolute top-1/3 left-1/4 w-80 h-80 bg-blue-500/8 rounded-full blur-[100px]"></div>
            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
                <div class="text-center">
                    @include('partials.breadcrumb', ['items' => [['name' => 'Pricing', 'url' => url('/pricing')]]])
                    <h1 class="font-display text-4xl md:text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">
                        Free and <span class="text-gradient">Open Source</span>
                    </h1>
                    <p class="text-lg md:text-xl text-slate-400 max-w-2xl mx-auto">
                        The software is free to use under the Apache 2.0 license. You run it on your own servers, and help with a deployment is available on request.
                    </p>
                </div>
            </div>
            <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
        </section>

        <!-- Pricing Tiers -->
        <section class="py-24 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 max-w-5xl mx-auto">

                    <!-- Community Edition -->
                    <div class="card-pricing animate-on-scroll">
                        <div class="mb-8">
                            <div class="icon-box bg-slate-100 mb-4">
                                <svg class="w-5 h-5 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                            </div>
                            <h3 class="font-display text-xl font-bold text-slate-900 mb-1">Community</h3>
                            <p class="text-sm text-slate-500">Free to use, change and share.</p>
                        </div>
                        <div class="mb-8">
                            <span class="font-display text-5xl font-extrabold text-slate-900">Free</span>
                            <span class="text-slate-500 ml-2">Open Source</span>
                        </div>
                        <ul class="space-y-3 mb-8 text-sm text-slate-600">
                            <li class="list-check">Full source code</li>
                            <li class="list-check">Apache 2.0 license</li>
                            <li class="list-check">All 61 domain modules</li>
                            <li class="list-check">ISO 20022, open banking and payment rail modules</li>
                            <li class="list-check">Ledger, microfinance and Interledger modules</li>
                            <li class="list-check">Community support on GitHub</li>
                            <li class="list-check">Runs on your own servers</li>
                            <li class="list-check">Model Context Protocol server for Claude Desktop, Cursor and other agents</li>
                        </ul>
                        <a href="{{ config('brand.github_url') }}" target="_blank" class="btn-secondary w-full text-center">
                            Get Started on GitHub
                        </a>
                    </div>

                    <!-- Cloud Platform (Featured) -->
                    <div class="card-pricing is-featured animate-on-scroll stagger-1 relative">
                        <div class="mb-8">
                            <div class="icon-box bg-blue-50 mb-4">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/></svg>
                            </div>
                            <h3 class="font-display text-xl font-bold text-slate-900 mb-1">Sandbox</h3>
                            <p class="text-sm text-slate-500">Try the platform online with test data.</p>
                        </div>
                        <div class="mb-8">
                            <span class="font-display text-5xl font-extrabold text-slate-900">Free</span>
                            <p class="text-sm text-slate-500 mt-1">Hosted demo on this site</p>
                        </div>
                        <ul class="space-y-3 mb-8 text-sm text-slate-600">
                            <li class="list-check">No installation needed</li>
                            <li class="list-check">Test data only, no real money</li>
                            <li class="list-check">Banking screens and the API</li>
                            <li class="list-check">The Global Currency Unit demo with simulated votes</li>
                            <li class="list-check">Not for production use</li>
                        </ul>
                        <a href="{{ route('register') }}" class="btn-primary w-full text-center">
                            Create a Sandbox Account
                        </a>
                    </div>

                    <!-- Enterprise -->
                    <div class="card-pricing animate-on-scroll stagger-2">
                        <div class="mb-8">
                            <div class="icon-box bg-slate-100 mb-4">
                                <svg class="w-5 h-5 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            </div>
                            <h3 class="font-display text-xl font-bold text-slate-900 mb-1">Deployment Help</h3>
                            <p class="text-sm text-slate-500">Help with your own installation.</p>
                        </div>
                        <div class="mb-8">
                            <span class="font-display text-5xl font-extrabold text-slate-900">On request</span>
                            <p class="text-sm text-slate-500 mt-1">Scope agreed with you</p>
                        </div>
                        <ul class="space-y-3 mb-8 text-sm text-slate-600">
                            <li class="list-check">Installing and configuring the platform</li>
                            <li class="list-check">Open banking module setup</li>
                            <li class="list-check">Custom ISO 20022 message types</li>
                            <li class="list-check">Compliance-tooling configuration</li>
                            <li class="list-check">You run and operate the deployment</li>
                        </ul>
                        <a href="{{ route('support.contact') }}" class="btn-secondary w-full text-center">
                            Contact Us
                        </a>
                    </div>
                </div>
                <p class="mt-10 text-xs text-slate-500 text-center max-w-3xl mx-auto leading-relaxed">
                    FinAegis is open-source software. Using it does not make a deployment compliant. Licensing and regulatory compliance are the responsibility of the operator of each deployment. Nothing here is legal advice.
                </p>
            </div>
        </section>

        <!-- FAQ Section -->
        <section class="py-24 bg-slate-50/50">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-14">
                    <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">Frequently Asked Questions</h2>
                    <p class="text-slate-500 text-lg">These answers explain what is free and what is not offered.</p>
                </div>

                <div class="space-y-4">
                    @php
                        $faqs = [
                            ['q' => 'Is the software really free?', 'a' => 'Yes. The code is open source under the Apache 2.0 license. You can use it for any purpose, including commercial projects.'],
                            ['q' => 'Is there a managed cloud version?', 'a' => 'No. There is no managed hosting and no uptime guarantee. You run the platform on your own servers. This site is only a demo with test data.'],
                            ['q' => 'Can I get help with my own deployment?', 'a' => 'You can contact us and describe what you need. We will tell you whether we can help and agree the scope with you before any work starts.'],
                            ['q' => 'Who is responsible for compliance?', 'a' => 'The operator of each deployment is responsible for licensing and regulatory compliance. Using the software does not make a deployment compliant, and nothing on this site is legal advice.'],
                        ];
                    @endphp

                    @foreach($faqs as $i => $faq)
                    <details class="card-feature accordion group animate-on-scroll stagger-{{ $i + 1 }}" {{ $i === 0 ? 'open' : '' }}>
                        <summary class="flex items-center justify-between font-display font-semibold text-slate-900">
                            {{ $faq['q'] }}
                            <svg class="w-5 h-5 text-slate-400 group-open:rotate-180 transition-transform flex-shrink-0 ml-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                        </summary>
                        <div class="accordion-content">
                            <p class="text-slate-600 text-sm leading-relaxed">{{ $faq['a'] }}</p>
                        </div>
                    </details>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- CTA -->
        <section class="bg-fa-navy relative overflow-hidden">
            <div class="absolute inset-0 bg-dot-pattern"></div>
            <div class="relative max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 py-20">
                <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Start with the Source Code</h2>
                <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
                    The code is free on GitHub. If you need help with your own deployment, contact us.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ config('brand.github_url') }}" target="_blank" class="btn-primary px-8 py-4 text-lg">
                        View on GitHub
                    </a>
                    <a href="{{ route('support.contact') }}" class="btn-outline px-8 py-4 text-lg">
                        Contact Us
                    </a>
                </div>
            </div>
        </section>

@endsection
