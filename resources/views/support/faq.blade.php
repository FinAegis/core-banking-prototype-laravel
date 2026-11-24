@extends('layouts.public')

@section('title', 'FAQ - Frequently Asked Questions | ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'FAQ - Frequently Asked Questions | ' . config('brand.name', 'Zelta'),
        'description' => 'Answers to common questions about ' . config('brand.name', 'Zelta') . ', the open-source core banking platform. They cover the current version, the sandbox, the GCU demo, the technology and how to get started.',
    ])
    
    {{-- Schema.org FAQ Markup: built from the same strings as the visible answers below --}}
    @php
    $faqBrand = config('brand.name', 'Zelta');
    $faqText = [
        'status' => [
            'question' => 'What is the current status of ' . $faqBrand . '?',
            'intro' => $faqBrand . ' is an open-source reference platform for core banking software. The current version is v7.18.1. You can try it for free in the sandbox.',
            'items' => [
                'The platform has 61 domain modules. They include decentralised finance, cross-chain transfers, privacy, rewards and a Model Context Protocol server for AI agents.',
                'It offers more than 1,000 API routes and a GraphQL API that covers 43 domains.',
                'In the sandbox you can try the identity check and anti-money laundering modules, the card issuing layer in demo mode and the mobile payment features.',
                'Every transaction in the sandbox uses test data, so no real money is involved.',
            ],
            'outro' => 'The changelog and the GitHub repository list the changes in each release.',
        ],
        'money' => [
            'question' => 'Can I use real money on the platform now?',
            'intro' => 'No. The sandbox is for evaluation and testing only.',
            'items' => [
                'All balances and transactions use test data.',
                'The bank and card issuing adapters connect to sandbox or demo endpoints.',
                'Currency conversions use reference rates, not live market prices.',
                'You can try the payment flows, but they move no real money.',
            ],
            'outro' => 'This way you can try each feature without putting real funds at risk.',
        ],
        'start' => [
            'question' => 'How do I get started with ' . $faqBrand . '?',
            'intro' => 'You can start in a few minutes.',
            'items' => [
                'Register for a free sandbox account.',
                'Try the sandbox features with test transactions.',
                'Report bugs and problems on GitHub.',
                'Send feedback by email to ' . config('brand.support_email', 'info@zelta.app') . '.',
                'Ask questions in GitHub Discussions.',
            ],
            'outro' => 'You can also install the code on your own server. The README file on GitHub explains how.',
        ],
        'gcu' => [
            'question' => 'What is the Global Currency Unit (GCU)?',
            'intro' => 'The Global Currency Unit demo is a reference implementation of a basket-referenced unit built with FinAegis. Its value is calculated from a demo basket of US dollars, euros, British pounds, Swiss francs, Japanese yen and gold. No reserves are held.',
            'items' => [],
            'outro' => 'The Global Currency Unit (GCU) is a software demonstration — a reference implementation built with FinAegis. It is not issued, offered or sold to anyone and has no monetary value. Any GCU balances, conversions or basket "votes" shown in the demo are simulated. Offering to the public in the EU a token that references a basket of currencies and/or commodities would require authorisation as an asset-referenced token issuer under MiCA (Title III); no such authorisation is held.',
        ],
        'voting' => [
            'question' => 'How does voting work in the GCU demo?',
            'intro' => 'The GCU demo includes a simulated vote on the basket composition. Proposals run in monthly cycles, and everyone can see the tally. All votes and balances are simulated and have no monetary effect.',
            'items' => [],
            'outro' => '',
        ],
    ];
    $faqData = array_values(array_map(fn (array $f): array => [
        'question' => $f['question'],
        'answer' => trim(implode(' ', array_filter([
            $f['intro'],
            $f['items'] === [] ? '' : implode(' ', $f['items']),
            $f['outro'],
        ], fn ($part) => $part !== ''))),
    ], $faqText));
    @endphp
    <x-schema type="faq" :data="$faqData" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Support', 'url' => url('/support')],
        ['name' => 'FAQ', 'url' => url('/support/faq')]
    ]" />
@endsection

@push('styles')
<style>
    .faq-item {
        transition: all 0.3s ease;
    }
    .faq-answer {
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
    }
    .faq-answer.active {
        max-height: 800px;
    }
</style>
@endpush

@section('content')

    <!-- Hero Section -->
    <section class="bg-fa-navy relative overflow-hidden">
        <div class="absolute inset-0 bg-grid-pattern"></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
            <div class="text-center">
                <h1 class="font-display text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">Frequently Asked Questions</h1>
                <p class="text-xl text-slate-400 max-w-3xl mx-auto">
                    This page answers common questions about the {{ config('brand.name', 'Zelta') }} platform and the GCU demo.
                </p>

                <!-- Search Bar -->
                <div class="mt-8 max-w-xl mx-auto">
                    <div class="relative">
                        <input type="text" id="faq-search" placeholder="Search for answers..."
                            class="w-full px-6 py-3 rounded-lg text-slate-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-white">
                        <svg class="absolute right-4 top-3.5 w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
        <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
    </section>

    <!-- Category Filter -->
    <section class="py-8 bg-white border-b">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap gap-2 justify-center">
                <button class="category-filter active px-4 py-2 rounded-full bg-indigo-600 text-white transition" data-category="all">
                    All Questions
                </button>
                <button class="category-filter px-4 py-2 rounded-full bg-slate-200 text-slate-600 hover:bg-slate-300 transition" data-category="getting-started">
                    Getting Started
                </button>
                <button class="category-filter px-4 py-2 rounded-full bg-slate-200 text-slate-600 hover:bg-slate-300 transition" data-category="gcu">
                    GCU
                </button>
                <button class="category-filter px-4 py-2 rounded-full bg-slate-200 text-slate-600 hover:bg-slate-300 transition" data-category="platform">
                    Platform Status
                </button>
                <button class="category-filter px-4 py-2 rounded-full bg-slate-200 text-slate-600 hover:bg-slate-300 transition" data-category="technical">
                    Technical
                </button>
                <button class="category-filter px-4 py-2 rounded-full bg-slate-200 text-slate-600 hover:bg-slate-300 transition" data-category="future">
                    Future Features
                </button>
            </div>
        </div>
    </section>

    <!-- FAQ Items -->
    <section class="py-20 bg-slate-50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="space-y-4" id="faq-container">
                
                <!-- Platform Status Questions -->
                <div class="faq-item" data-category="platform">
                    <button class="faq-question w-full text-left px-6 py-4 bg-white rounded-lg hover:bg-slate-50 transition shadow-sm">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-slate-900">{{ $faqText['status']['question'] }}</h3>
                            <svg class="w-5 h-5 text-slate-400 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4 bg-white rounded-b-lg">
                        <p class="text-slate-500">
                            {{ $faqText['status']['intro'] }}
                        </p>
                        <ul class="list-disc list-inside mt-2 text-slate-500 space-y-1">
                            @foreach($faqText['status']['items'] as $faqItem)
                            <li>{{ $faqItem }}</li>
                            @endforeach
                        </ul>
                        <p class="text-slate-500 mt-3">
                            {{ $faqText['status']['outro'] }}
                        </p>
                    </div>
                </div>

                <div class="faq-item" data-category="platform">
                    <button class="faq-question w-full text-left px-6 py-4 bg-white rounded-lg hover:bg-slate-50 transition shadow-sm">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-slate-900">{{ $faqText['money']['question'] }}</h3>
                            <svg class="w-5 h-5 text-slate-400 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4 bg-white rounded-b-lg">
                        <p class="text-slate-500">
                            {{ $faqText['money']['intro'] }}
                        </p>
                        <ul class="list-disc list-inside mt-2 text-slate-500 space-y-1">
                            @foreach($faqText['money']['items'] as $faqItem)
                            <li>{{ $faqItem }}</li>
                            @endforeach
                        </ul>
                        <p class="text-slate-500 mt-3">
                            {{ $faqText['money']['outro'] }}
                        </p>
                    </div>
                </div>

                <!-- Getting Started Questions -->
                <div class="faq-item" data-category="getting-started">
                    <button class="faq-question w-full text-left px-6 py-4 bg-white rounded-lg hover:bg-slate-50 transition shadow-sm">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-slate-900">{{ $faqText['start']['question'] }}</h3>
                            <svg class="w-5 h-5 text-slate-400 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4 bg-white rounded-b-lg">
                        <p class="text-slate-500">
                            {{ $faqText['start']['intro'] }}
                        </p>
                        <ol class="list-decimal list-inside mt-2 text-slate-500 space-y-1">
                            @foreach($faqText['start']['items'] as $faqItem)
                            <li>{{ $faqItem }}</li>
                            @endforeach
                        </ol>
                        <p class="text-slate-500 mt-3">
                            {{ $faqText['start']['outro'] }}
                        </p>
                    </div>
                </div>

                <div class="faq-item" data-category="getting-started">
                    <button class="faq-question w-full text-left px-6 py-4 bg-white rounded-lg hover:bg-slate-50 transition shadow-sm">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-slate-900">Is {{ config('brand.name', 'Zelta') }} open source?</h3>
                            <svg class="w-5 h-5 text-slate-400 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4 bg-white rounded-b-lg">
                        <p class="text-slate-500">
                            Yes. {{ config('brand.name', 'Zelta') }} is open source under the Apache 2.0 license.
                        </p>
                        <ul class="list-disc list-inside mt-2 text-slate-500 space-y-1">
                            <li>You can read all of the source code on GitHub.</li>
                            <li>You can send improvements and new features as pull requests.</li>
                            <li>You can fork the code and change it for your own needs.</li>
                            <li>You can use it in commercial products under the terms of the license.</li>
                        </ul>
                        <p class="text-slate-500 mt-3">
                            The repository is at {{ config('brand.github_url') }}.
                        </p>
                    </div>
                </div>

                <!-- GCU Questions -->
                <div class="faq-item" data-category="gcu">
                    <button class="faq-question w-full text-left px-6 py-4 bg-white rounded-lg hover:bg-slate-50 transition shadow-sm">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-slate-900">{{ $faqText['gcu']['question'] }}</h3>
                            <svg class="w-5 h-5 text-slate-400 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4 bg-white rounded-b-lg">
                        <p class="text-slate-500">
                            {{ $faqText['gcu']['intro'] }}
                        </p>
                        <p class="text-slate-500 mt-3">
                            <strong>Note:</strong> {{ $faqText['gcu']['outro'] }}
                        </p>
                    </div>
                </div>

                <div class="faq-item" data-category="gcu">
                    <button class="faq-question w-full text-left px-6 py-4 bg-white rounded-lg hover:bg-slate-50 transition shadow-sm">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-slate-900">{{ $faqText['voting']['question'] }}</h3>
                            <svg class="w-5 h-5 text-slate-400 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4 bg-white rounded-b-lg">
                        <p class="text-slate-500">
                            {{ $faqText['voting']['intro'] }}
                        </p>
                    </div>
                </div>

                <!-- Technical Questions -->
                <div class="faq-item" data-category="technical">
                    <button class="faq-question w-full text-left px-6 py-4 bg-white rounded-lg hover:bg-slate-50 transition shadow-sm">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-slate-900">What technology stack does {{ config('brand.name', 'Zelta') }} use?</h3>
                            <svg class="w-5 h-5 text-slate-400 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4 bg-white rounded-b-lg">
                        <p class="text-slate-500">
                            {{ config('brand.name', 'Zelta') }} is a Laravel 12 application written in PHP 8.4.
                        </p>
                        <ul class="list-disc list-inside mt-2 text-slate-500 space-y-1">
                            <li>The web pages use Blade templates, Livewire, Alpine.js and Tailwind CSS.</li>
                            <li>The data is stored in MySQL, MariaDB or PostgreSQL.</li>
                            <li>Redis runs the cache, the queues and the event streams.</li>
                            <li>The platform offers a REST API with an OpenAPI description and a GraphQL API.</li>
                            <li>The tests are written with Pest.</li>
                            <li>The admin panel is built with Filament.</li>
                        </ul>
                        <p class="text-slate-500 mt-3">
                            The README file on GitHub lists the full technology stack.
                        </p>
                    </div>
                </div>

                <div class="faq-item" data-category="technical">
                    <button class="faq-question w-full text-left px-6 py-4 bg-white rounded-lg hover:bg-slate-50 transition shadow-sm">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-slate-900">How many API endpoints are available?</h3>
                            <svg class="w-5 h-5 text-slate-400 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4 bg-white rounded-b-lg">
                        <p class="text-slate-500">
                            The platform registers more than 1,000 API routes.
                        </p>
                        <ul class="list-disc list-inside mt-2 text-slate-500 space-y-1">
                            <li>They cover sign-in, accounts, transactions, wallets and currency conversion.</li>
                            <li>They also cover compliance checks, exchange, cards, AI agents and the other domain modules.</li>
                            <li>The GraphQL API covers 43 domains.</li>
                        </ul>
                        <p class="text-slate-500 mt-3">
                            The API reference on the developer pages describes the main routes and their parameters.
                        </p>
                    </div>
                </div>

                <!-- Future Features -->
                <div class="faq-item" data-category="future">
                    <button class="faq-question w-full text-left px-6 py-4 bg-white rounded-lg hover:bg-slate-50 transition shadow-sm">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-slate-900">What features are coming next?</h3>
                            <svg class="w-5 h-5 text-slate-400 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4 bg-white rounded-b-lg">
                        <p class="text-slate-500">
                            Version 7.18.1 is the latest release.
                        </p>
                        <ul class="list-disc list-inside mt-2 text-slate-500 space-y-1">
                            <li>Version 7.18.1 made asset transfers, wallet currency conversions and Global Currency Unit demo trades book the right amounts. It also lets the identity check in the card pilot start, and it saves the spending limit a person gives an AI assistant.</li>
                            <li>Version 7.18.0 added virtual cards for a closed pilot. People in the pilot will be able to top up a card from their wallet, and the app shows approval codes for online card payments. Cards stay switched off until the pilot starts. Top-ups also stay switched off for now.</li>
                            <li>Version 7.18.0 also removed two features that never worked. These are the liquidity pool add, remove and rebalance flow and saved bank accounts on the withdrawal page.</li>
                            <li>Versions 7.17.0 and 7.17.1 were security releases.</li>
                            <li>Earlier releases added the mobile wallet backend, passkey sign-in, wallet sends where the device holds the keys, the public Model Context Protocol server and x402 payments for APIs.</li>
                            <li>Next, we plan to extend the mobile features.</li>
                        </ul>
                        <p class="text-slate-500 mt-3">
                            The changelog and the GitHub repository show what each release changed.
                        </p>
                    </div>
                </div>

                <div class="faq-item" data-category="future">
                    <button class="faq-question w-full text-left px-6 py-4 bg-white rounded-lg hover:bg-slate-50 transition shadow-sm">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-slate-900">Will there be mobile apps?</h3>
                            <svg class="w-5 h-5 text-slate-400 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4 bg-white rounded-b-lg">
                        <p class="text-slate-500">
                            Yes. The Zelta mobile wallet is built on this platform with Expo and React Native.
                        </p>
                        <ul class="list-disc list-inside mt-2 text-slate-500 space-y-1">
                            <li>One codebase serves both iOS and Android.</li>
                            <li>The Android version is in open testing on Google Play, and the iOS version is planned.</li>
                            <li>People sign in with a passkey or with biometrics.</li>
                            <li>Push notifications are sent through Firebase Cloud Messaging.</li>
                            <li>The app shows payment requests, an activity feed and receipts.</li>
                            <li>Sends on Ethereum-compatible networks use ERC-4337 smart accounts.</li>
                        </ul>
                        <p class="text-slate-500 mt-3">
                            The web platform also works in mobile browsers.
                        </p>
                    </div>
                </div>

                <!-- Contact Support -->
                <div class="mt-12 bg-indigo-50 rounded-xl p-8 text-center">
                    <h3 class="text-xl font-semibold text-slate-900 mb-4">Can't find what you're looking for?</h3>
                    <p class="text-slate-500 mb-6">
                        You can send us a message or ask the community on GitHub.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="{{ route('support.contact') }}" class="btn-primary">
                            Contact Support
                        </a>
                        <a href="{{ config('brand.github_url') }}/discussions" class="btn-outline-dark">
                            GitHub Discussions
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <script>
        // FAQ Toggle
        document.querySelectorAll('.faq-question').forEach(button => {
            button.addEventListener('click', () => {
                const answer = button.nextElementSibling;
                const icon = button.querySelector('svg');
                
                answer.classList.toggle('active');
                icon.classList.toggle('rotate-180');
            });
        });

        // Category Filter
        document.querySelectorAll('.category-filter').forEach(filter => {
            filter.addEventListener('click', () => {
                const category = filter.dataset.category;
                
                // Update active filter
                document.querySelectorAll('.category-filter').forEach(f => {
                    f.classList.remove('bg-indigo-600', 'text-white');
                    f.classList.add('bg-slate-200', 'text-slate-600');
                });
                filter.classList.remove('bg-slate-200', 'text-slate-600');
                filter.classList.add('bg-indigo-600', 'text-white');
                
                // Filter FAQ items
                document.querySelectorAll('.faq-item').forEach(item => {
                    if (category === 'all' || item.dataset.category === category) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        });

        // Search Functionality
        const searchInput = document.getElementById('faq-search');
        searchInput.addEventListener('input', (e) => {
            const searchTerm = e.target.value.toLowerCase();
            
            document.querySelectorAll('.faq-item').forEach(item => {
                const question = item.querySelector('h3').textContent.toLowerCase();
                const answer = item.querySelector('.faq-answer').textContent.toLowerCase();
                
                if (question.includes(searchTerm) || answer.includes(searchTerm)) {
                    item.style.display = 'block';
                } else {
                    item.style.display = 'none';
                }
            });
        });
    </script>
@endsection