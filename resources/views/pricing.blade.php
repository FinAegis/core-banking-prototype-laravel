@extends('layouts.public')

@section('title', 'Pricing - Flexible Plans for Every Scale | ' . config('brand.name', 'Zelta') . '')

@section('seo')
    @include('partials.seo', [
        'title' => 'Pricing - Flexible Plans for Every Scale',
        'description' => config('brand.name', 'Zelta') . ' Pricing - Start with our free open-source community edition. Scale with enterprise support, custom features, and dedicated infrastructure when ready.',
        'keywords' => config('brand.name', 'Zelta') . ' pricing, open source banking, enterprise support, core banking pricing, fintech platform cost, free banking software',
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
                        Open Source & <span class="text-gradient">Enterprise Ready</span>
                    </h1>
                    <p class="text-lg md:text-xl text-slate-400 max-w-2xl mx-auto">
                        Start with our community edition, scale with enterprise support when you're ready.
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
                            <p class="text-sm text-slate-500">For developers and small teams</p>
                        </div>
                        <div class="mb-8">
                            <span class="font-display text-5xl font-extrabold text-slate-900">Free</span>
                            <span class="text-slate-500 ml-2">Open Source</span>
                        </div>
                        <ul class="space-y-3 mb-8 text-sm text-slate-600">
                            <li class="list-check">Full source code access</li>
                            <li class="list-check">MIT License</li>
                            <li class="list-check">All 46 domain modules</li>
                            <li class="list-check">Community support</li>
                            <li class="list-check">Self-hosted deployment</li>
                        </ul>
                        <a href="{{ config('brand.github_url') }}" target="_blank" class="btn-secondary w-full text-center">
                            Get Started on GitHub
                        </a>
                    </div>

                    <!-- Cloud Platform (Featured) -->
                    <div class="card-pricing is-featured animate-on-scroll stagger-1 relative">
                        <div class="absolute -top-3 left-1/2 -translate-x-1/2">
                            <span class="badge badge-accent">Most Popular</span>
                        </div>
                        <div class="mb-8">
                            <div class="icon-box bg-blue-50 mb-4">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z"/></svg>
                            </div>
                            <h3 class="font-display text-xl font-bold text-slate-900 mb-1">Cloud Platform</h3>
                            <p class="text-sm text-slate-500">Managed infrastructure for growing businesses</p>
                        </div>
                        <div class="mb-8">
                            <span class="font-display text-5xl font-extrabold text-slate-900">Custom</span>
                            <p class="text-sm text-slate-500 mt-1">Based on usage and scale</p>
                        </div>
                        <ul class="space-y-3 mb-8 text-sm text-slate-600">
                            <li class="list-check">Everything in Community</li>
                            <li class="list-check">Managed hosting</li>
                            <li class="list-check">99.9% uptime SLA</li>
                            <li class="list-check">Priority support</li>
                            <li class="list-check">Automatic updates</li>
                            <li class="list-check">Daily backups</li>
                        </ul>
                        <a href="{{ route('support.contact') }}" class="btn-primary w-full text-center">
                            Get a Quote
                        </a>
                    </div>

                    <!-- Enterprise -->
                    <div class="card-pricing animate-on-scroll stagger-2">
                        <div class="mb-8">
                            <div class="icon-box bg-slate-100 mb-4">
                                <svg class="w-5 h-5 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
                            </div>
                            <h3 class="font-display text-xl font-bold text-slate-900 mb-1">Enterprise</h3>
                            <p class="text-sm text-slate-500">Custom solutions for large organizations</p>
                        </div>
                        <div class="mb-8">
                            <span class="font-display text-5xl font-extrabold text-slate-900">Custom</span>
                            <p class="text-sm text-slate-500 mt-1">Tailored to your requirements</p>
                        </div>
                        <ul class="space-y-3 mb-8 text-sm text-slate-600">
                            <li class="list-check">Everything in Cloud</li>
                            <li class="list-check">On-premise deployment</li>
                            <li class="list-check">Custom integrations</li>
                            <li class="list-check">Dedicated support team</li>
                            <li class="list-check">Service level agreements</li>
                            <li class="list-check">Compliance assistance</li>
                        </ul>
                        <a href="{{ route('support.contact') }}" class="btn-secondary w-full text-center">
                            Contact Enterprise Sales
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section class="py-24 bg-slate-50/50">
            <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-14">
                    <h2 class="font-display text-3xl md:text-4xl font-bold text-slate-900 mb-4">Frequently Asked Questions</h2>
                    <p class="text-slate-500 text-lg">Everything you need to know about our pricing</p>
                </div>

                <div class="space-y-4">
                    @php
                        $faqs = [
                            ['q' => 'Is the community edition really free?', 'a' => 'Yes! Our community edition is completely free and open source under the MIT license. You can use it for any purpose, including commercial projects.'],
                            ['q' => "What's included in the Cloud Platform?", 'a' => 'The Cloud Platform includes managed hosting, automatic updates, daily backups, 99.9% uptime SLA, and priority support. We handle all the infrastructure so you can focus on your business.'],
                            ['q' => 'Can I switch between plans?', 'a' => "Absolutely! You can start with the community edition and upgrade to Cloud or Enterprise plans at any time. We'll help you migrate your data seamlessly."],
                            ['q' => 'Do you offer discounts for non-profits?', 'a' => 'Yes, we offer special pricing for non-profit organizations and educational institutions. Contact our sales team for more information.'],
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
                <h2 class="font-display text-3xl md:text-4xl font-bold text-white mb-4">Ready to Get Started?</h2>
                <p class="text-lg text-slate-400 mb-10 max-w-2xl mx-auto">
                    Start free with the Community Edition, or talk to us about managed and enterprise options.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ config('brand.github_url') }}" target="_blank" class="btn-primary px-8 py-4 text-lg">
                        Start with Community Edition
                    </a>
                    <a href="{{ route('support.contact') }}" class="btn-outline px-8 py-4 text-lg">
                        Talk to Sales
                    </a>
                </div>
            </div>
        </section>

@endsection
