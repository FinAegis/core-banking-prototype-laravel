@extends('layouts.public')

@section('title', 'CGO Concept - ' . config('brand.name', 'Zelta') . '')

@section('seo')
    @include('partials.seo', [
        'title' => 'Continuous Growth Offering (CGO) Concept - ' . config('brand.name', 'Zelta') . '',
        'description' => 'Explore the Continuous Growth Offering concept - a theoretical model for continuous community-driven funding in open-source financial platforms.',
        'keywords' => 'CGO concept, continuous growth offering, open source funding, community funding model',
    ])
@endsection

@section('content')

    <!-- Hero Section -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="absolute top-1/3 -right-20 w-80 h-80 bg-amber-500/6 rounded-full blur-[100px]"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-amber-500/10 border border-amber-500/20 text-sm text-amber-400 mb-8">
                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                    </svg>
                    Conceptual Model Only
                </div>
                @include('partials.breadcrumb', ['items' => [['name' => 'CGO', 'url' => url('/cgo')]]])
                <h1 class="font-display text-4xl md:text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">
                    Continuous Growth <span class="text-gradient-gold">Offering</span>
                </h1>
                <p class="text-lg md:text-xl text-slate-400 max-w-2xl mx-auto mb-10">
                    A conceptual funding model for open-source financial platforms. This is <strong class="text-slate-300">not</strong> an active investment opportunity.
                </p>

                <!-- Important Notice -->
                <div class="card-dark !bg-amber-500/[0.05] !border-amber-500/20 p-6 max-w-2xl mx-auto">
                    <div class="flex items-start gap-3">
                        <svg class="w-5 h-5 text-amber-400 mt-0.5 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        <div class="text-left">
                            <h3 class="text-sm font-bold text-amber-300 mb-1">Demonstration Only</h3>
                            <p class="text-slate-500 text-sm">
                                This page demonstrates the CGO concept as part of the {{ config('brand.name', 'Zelta') }} platform.
                                <strong class="text-slate-400">No real money is being collected.</strong> This is purely a conceptual exploration
                                of alternative funding models for open-source financial platforms.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-amber-500/20 to-transparent"></div>
    </section>

    <!-- What is CGO Section -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="font-display text-4xl font-bold text-slate-900 mb-4">What is a Continuous Growth Offering?</h2>
                <p class="text-xl text-slate-500 max-w-3xl mx-auto">
                    A theoretical model that explores how open-source financial platforms could sustain development through continuous community participation.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <div class="card-feature text-center animate-on-scroll stagger-1">
                    <div class="icon-box-lg bg-indigo-50 rounded-full mx-auto mb-5">
                        <svg class="w-7 h-7 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                        </svg>
                    </div>
                    <h3 class="font-display text-xl font-bold text-slate-900 mb-3">Conceptual Model</h3>
                    <p class="text-slate-500">Unlike traditional one-time funding rounds, CGO imagines continuous, small-scale participation from the community over time.</p>
                </div>

                <div class="card-feature text-center animate-on-scroll stagger-2">
                    <div class="icon-box-lg bg-purple-50 rounded-full mx-auto mb-5">
                        <svg class="w-7 h-7 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <h3 class="font-display text-xl font-bold text-slate-900 mb-3">Democratic Participation</h3>
                    <p class="text-slate-500">The model explores how supporters could have voice in development priorities proportional to their participation.</p>
                </div>

                <div class="card-feature text-center animate-on-scroll stagger-3">
                    <div class="icon-box-lg bg-emerald-50 rounded-full mx-auto mb-5">
                        <svg class="w-7 h-7 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="font-display text-xl font-bold text-slate-900 mb-3">Sustainable Funding</h3>
                    <p class="text-slate-500">Explores alternatives to traditional VC funding that align incentives between developers and users.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Theoretical Tiers -->
    <section class="py-20 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="font-display text-4xl font-bold text-slate-900 mb-4">Conceptual Participation Tiers</h2>
                <p class="text-xl text-slate-500">How a CGO <em>might</em> structure community participation (theoretical)</p>
            </div>

            <div class="bg-amber-50 border-l-4 border-amber-400 p-4 mb-8 max-w-3xl mx-auto">
                <p class="text-amber-800">
                    <strong>Note:</strong> These tiers are conceptual demonstrations only. This is not an active offering and no payments are accepted.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <!-- Bronze Tier -->
                <div class="card-feature relative animate-on-scroll stagger-1">
                    <div class="absolute -top-4 left-1/2 transform -translate-x-1/2">
                        <span class="badge badge-warning">Conceptual</span>
                    </div>
                    <div class="text-center mb-6">
                        <h3 class="font-display text-2xl font-bold text-slate-900 mb-2">Supporter</h3>
                        <p class="text-4xl font-bold text-slate-400">Small</p>
                        <p class="text-sm text-slate-400">Contributions</p>
                    </div>
                    <ul class="space-y-3 mb-8 text-slate-400">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-slate-400 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Recognition as supporter</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-slate-400 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Development updates</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-slate-400 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Community access</span>
                        </li>
                    </ul>
                </div>

                <!-- Silver Tier -->
                <div class="card-feature animate-on-scroll stagger-2">
                    <div class="text-center mb-6">
                        <h3 class="font-display text-2xl font-bold text-slate-900 mb-2">Contributor</h3>
                        <p class="text-4xl font-bold text-slate-400">Medium</p>
                        <p class="text-sm text-slate-400">Contributions</p>
                    </div>
                    <ul class="space-y-3 mb-8 text-slate-400">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-slate-400 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Supporter benefits</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-slate-400 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Feature voting rights</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-slate-400 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Roadmap input</span>
                        </li>
                    </ul>
                </div>

                <!-- Gold Tier -->
                <div class="card-feature animate-on-scroll stagger-3">
                    <div class="text-center mb-6">
                        <h3 class="font-display text-2xl font-bold text-slate-900 mb-2">Sponsor</h3>
                        <p class="text-4xl font-bold text-slate-400">Large</p>
                        <p class="text-sm text-slate-400">Contributions</p>
                    </div>
                    <ul class="space-y-3 mb-8 text-slate-400">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-slate-400 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Contributor benefits</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-slate-400 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Direct developer access</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-slate-400 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Strategic input</span>
                        </li>
                    </ul>
                </div>
            </div>

            <div class="text-center mt-12">
                <p class="text-slate-500 mb-4">This is a demonstration of concepts only. No real funding is being collected.</p>
            </div>
        </div>
    </section>

    <!-- How to Actually Support Section -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="font-display text-3xl font-bold text-slate-900 mb-4">Want to Support {{ config('brand.name', 'Zelta') }}?</h2>
                <p class="text-xl text-slate-500">Here's how you can actually contribute to this open-source project</p>
            </div>

            <div class="grid md:grid-cols-3 gap-6 max-w-4xl mx-auto">
                <a href="{{ config('brand.github_url') }}" target="_blank" class="block card-feature text-center animate-on-scroll stagger-1">
                    <div class="icon-box-lg bg-slate-100 rounded-full mx-auto mb-4">
                        <svg class="w-7 h-7 text-slate-700" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path d="M12 .297c-6.63 0-12 5.373-12 12 0 5.303 3.438 9.8 8.205 11.385.6.113.82-.258.82-.577 0-.285-.01-1.04-.015-2.04-3.338.724-4.042-1.61-4.042-1.61C4.422 18.07 3.633 17.7 3.633 17.7c-1.087-.744.084-.729.084-.729 1.205.084 1.838 1.236 1.838 1.236 1.07 1.835 2.809 1.305 3.495.998.108-.776.417-1.305.76-1.605-2.665-.3-5.466-1.332-5.466-5.93 0-1.31.465-2.38 1.235-3.22-.135-.303-.54-1.523.105-3.176 0 0 1.005-.322 3.3 1.23.96-.267 1.98-.399 3-.405 1.02.006 2.04.138 3 .405 2.28-1.552 3.285-1.23 3.285-1.23.645 1.653.24 2.873.12 3.176.765.84 1.23 1.91 1.23 3.22 0 4.61-2.805 5.625-5.475 5.92.42.36.81 1.096.81 2.22 0 1.606-.015 2.896-.015 3.286 0 .315.21.69.825.57C20.565 22.092 24 17.592 24 12.297c0-6.627-5.373-12-12-12"/>
                        </svg>
                    </div>
                    <h3 class="font-display text-xl font-bold text-slate-900 mb-2">Contribute Code</h3>
                    <p class="text-slate-500">Submit PRs, fix bugs, or add features</p>
                </a>

                <a href="{{ config('brand.github_url') }}/issues" target="_blank" class="block card-feature text-center animate-on-scroll stagger-2">
                    <div class="icon-box-lg bg-blue-50 rounded-full mx-auto mb-4">
                        <svg class="w-7 h-7 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                        </svg>
                    </div>
                    <h3 class="font-display text-xl font-bold text-slate-900 mb-2">Report Issues</h3>
                    <p class="text-slate-500">Help improve by reporting bugs</p>
                </a>

                <a href="{{ config('brand.github_url') }}" target="_blank" class="block card-feature text-center animate-on-scroll stagger-3">
                    <div class="icon-box-lg bg-amber-50 rounded-full mx-auto mb-4">
                        <svg class="w-7 h-7 text-amber-600" fill="currentColor" viewBox="0 0 20 20" aria-hidden="true">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                        </svg>
                    </div>
                    <h3 class="font-display text-xl font-bold text-slate-900 mb-2">Star the Repo</h3>
                    <p class="text-slate-500">Show your support with a GitHub star</p>
                </a>
            </div>
        </div>
    </section>

    <!-- Learn More Section -->
    <section class="py-20 bg-slate-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="font-display text-3xl font-bold text-slate-900 mb-4">Learn More About {{ config('brand.name', 'Zelta') }}</h2>
                <p class="text-xl text-slate-500">Explore the concepts and technology behind the platform</p>
            </div>

            <div class="grid md:grid-cols-3 gap-6">
                <a href="{{ route('about') }}" class="block card-feature animate-on-scroll stagger-1">
                    <h3 class="font-display text-xl font-bold text-slate-900 mb-2">About the Project</h3>
                    <p class="text-slate-500">Learn what {{ config('brand.name', 'Zelta') }} is and why it was built</p>
                </a>

                <a href="{{ route('features.show', 'gcu') }}" class="block card-feature animate-on-scroll stagger-2">
                    <h3 class="font-display text-xl font-bold text-slate-900 mb-2">GCU Concept</h3>
                    <p class="text-slate-500">Explore the Global Currency Unit idea</p>
                </a>

                <a href="{{ route('developers') }}" class="block card-feature animate-on-scroll stagger-3">
                    <h3 class="font-display text-xl font-bold text-slate-900 mb-2">Developer Docs</h3>
                    <p class="text-slate-500">Technical documentation and API reference</p>
                </a>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-dot-pattern"></div>
        <div class="relative max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8 py-20">
            <h2 class="font-display text-4xl font-bold text-white mb-6">Explore the Demo</h2>
            <p class="text-lg mb-10 text-slate-400 max-w-3xl mx-auto">
                See the GCU, governance, and all banking features in action. Create a demo account to explore everything—all simulated, all safe.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="btn-primary btn-lg">
                    Explore the Platform
                </a>
                <a href="{{ config('brand.github_url') }}" target="_blank" class="btn-outline btn-lg">
                    View on GitHub
                </a>
            </div>
        </div>
    </section>

@endsection
