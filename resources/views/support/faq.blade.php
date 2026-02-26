@extends('layouts.public')

@section('title', 'FAQ - Frequently Asked Questions | FinAegis')

@section('seo')
    @include('partials.seo', [
        'title' => 'FAQ - Frequently Asked Questions | FinAegis',
        'description' => 'Frequently asked questions about FinAegis core banking platform, Global Currency Unit (GCU), architecture, and getting started.',
    ])
    
    {{-- Schema.org FAQ Markup --}}
    @php
    $faqData = [
        [
            'question' => 'What is the current status of FinAegis?',
            'answer' => 'FinAegis v5.4.0 is a fully-featured open-source core banking platform with 42 domain modules. The public instance runs in sandbox mode with test data so you can explore every feature freely. No real money is processed in the sandbox environment.'
        ],
        [
            'question' => 'Can I use real money on the platform?',
            'answer' => 'The public sandbox environment uses test data only. All balances and transactions are sandboxed, no real bank accounts are connected, and no actual currency conversions occur. This allows you to explore every feature safely. For production deployments, you can self-host or contact us about enterprise options.'
        ],
        [
            'question' => 'How do I get started?',
            'answer' => 'Register for a free account to explore the sandbox, or clone the repository from GitHub to self-host. You can explore all 42 domain modules, test the APIs, and provide feedback through our support channels.'
        ],
        [
            'question' => 'What is the Global Currency Unit (GCU)?',
            'answer' => 'The GCU is a basket currency concept where holders democratically vote on the composition of currencies backing the unit. Your actual funds remain in FDIC/government-insured bank accounts while you hold GCU tokens representing your share.'
        ],
        [
            'question' => 'How does democratic voting work for GCU?',
            'answer' => 'GCU holders can vote on the currency composition of the basket. The weight of your vote is proportional to your GCU holdings. Voting occurs periodically, and the basket rebalances based on community decisions.'
        ]
    ];
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
    .gradient-bg {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    }
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
    <section class="pb-20 gradient-bg text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h1 class="text-5xl font-bold mb-6">Frequently Asked Questions</h1>
                <p class="text-xl text-purple-100 max-w-3xl mx-auto">
                    Find answers to common questions about the FinAegis platform and the Global Currency Unit concept.
                </p>
                
                <!-- Search Bar -->
                <div class="mt-8 max-w-xl mx-auto">
                    <div class="relative">
                        <input type="text" id="faq-search" placeholder="Search for answers..." 
                            class="w-full px-6 py-3 rounded-lg text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-white">
                        <svg class="absolute right-4 top-3.5 w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Category Filter -->
    <section class="py-8 bg-white border-b">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex flex-wrap gap-2 justify-center">
                <button class="category-filter active px-4 py-2 rounded-full bg-indigo-600 text-white transition" data-category="all">
                    All Questions
                </button>
                <button class="category-filter px-4 py-2 rounded-full bg-gray-200 text-gray-700 hover:bg-gray-300 transition" data-category="getting-started">
                    Getting Started
                </button>
                <button class="category-filter px-4 py-2 rounded-full bg-gray-200 text-gray-700 hover:bg-gray-300 transition" data-category="gcu">
                    GCU
                </button>
                <button class="category-filter px-4 py-2 rounded-full bg-gray-200 text-gray-700 hover:bg-gray-300 transition" data-category="alpha">
                    Platform Status
                </button>
                <button class="category-filter px-4 py-2 rounded-full bg-gray-200 text-gray-700 hover:bg-gray-300 transition" data-category="technical">
                    Technical
                </button>
                <button class="category-filter px-4 py-2 rounded-full bg-gray-200 text-gray-700 hover:bg-gray-300 transition" data-category="future">
                    Future Features
                </button>
            </div>
        </div>
    </section>

    <!-- FAQ Items -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="space-y-4" id="faq-container">
                
                <!-- Platform Status Questions -->
                <div class="faq-item" data-category="alpha">
                    <button class="faq-question w-full text-left px-6 py-4 bg-white rounded-lg hover:bg-gray-50 transition shadow-sm">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900">What is the current status of FinAegis?</h3>
                            <svg class="w-5 h-5 text-gray-500 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4 bg-white rounded-b-lg">
                        <p class="text-gray-600">
                            FinAegis is a fully-featured open-source core banking prototype at v5.4.0. The sandbox environment lets you:
                        </p>
                        <ul class="list-disc list-inside mt-2 text-gray-600 space-y-1">
                            <li>Explore 42 domain modules including DeFi, cross-chain, and privacy</li>
                            <li>Test 143+ REST API endpoints and a 34-domain GraphQL API</li>
                            <li>Try KYC/AML compliance flows, card issuing, and mobile payments</li>
                            <li>All transactions use test data &mdash; no real funds are involved</li>
                            <li>Community feedback drives the roadmap forward</li>
                        </ul>
                        <p class="text-gray-600 mt-3">
                            Follow our GitHub repository for release notes and upcoming milestones.
                        </p>
                    </div>
                </div>

                <div class="faq-item" data-category="alpha">
                    <button class="faq-question w-full text-left px-6 py-4 bg-white rounded-lg hover:bg-gray-50 transition shadow-sm">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900">Can I use real money on the platform now?</h3>
                            <svg class="w-5 h-5 text-gray-500 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4 bg-white rounded-b-lg">
                        <p class="text-gray-600">
                            No. The sandbox environment is designed for evaluation and testing only:
                        </p>
                        <ul class="list-disc list-inside mt-2 text-gray-600 space-y-1">
                            <li>All balances and transactions use test data</li>
                            <li>Bank and card integrations connect to provider sandboxes</li>
                            <li>Currency conversions use reference rates, not live markets</li>
                            <li>Payment flows are fully functional but process no real funds</li>
                        </ul>
                        <p class="text-gray-600 mt-3">
                            This lets you explore every feature safely while we refine the platform.
                        </p>
                    </div>
                </div>

                <!-- Getting Started Questions -->
                <div class="faq-item" data-category="getting-started">
                    <button class="faq-question w-full text-left px-6 py-4 bg-white rounded-lg hover:bg-gray-50 transition shadow-sm">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900">How do I get started with FinAegis?</h3>
                            <svg class="w-5 h-5 text-gray-500 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4 bg-white rounded-b-lg">
                        <p class="text-gray-600">
                            Getting started takes just a few minutes:
                        </p>
                        <ol class="list-decimal list-inside mt-2 text-gray-600 space-y-1">
                            <li>Register for a free account on the platform</li>
                            <li>Explore the sandbox features and test transactions</li>
                            <li>Report bugs and issues on our GitHub repository</li>
                            <li>Provide feedback via email at info@finaegis.org</li>
                            <li>Join discussions on our GitHub community forum</li>
                        </ol>
                        <p class="text-gray-600 mt-3">
                            Your feedback helps us build a better platform for everyone.
                        </p>
                    </div>
                </div>

                <div class="faq-item" data-category="getting-started">
                    <button class="faq-question w-full text-left px-6 py-4 bg-white rounded-lg hover:bg-gray-50 transition shadow-sm">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900">Is FinAegis open source?</h3>
                            <svg class="w-5 h-5 text-gray-500 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4 bg-white rounded-b-lg">
                        <p class="text-gray-600">
                            Yes! FinAegis is fully open source under the MIT license. This means:
                        </p>
                        <ul class="list-disc list-inside mt-2 text-gray-600 space-y-1">
                            <li>You can view all source code on GitHub</li>
                            <li>You can contribute improvements and features</li>
                            <li>You can fork and modify the code for your needs</li>
                            <li>You can use it for commercial purposes (with commercial license when available)</li>
                            <li>The community helps drive development</li>
                        </ul>
                        <p class="text-gray-600 mt-3">
                            Visit our GitHub repository at: github.com/FinAegis/core-banking-prototype-laravel
                        </p>
                    </div>
                </div>

                <!-- GCU Questions -->
                <div class="faq-item" data-category="gcu">
                    <button class="faq-question w-full text-left px-6 py-4 bg-white rounded-lg hover:bg-gray-50 transition shadow-sm">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900">What is the Global Currency Unit (GCU)?</h3>
                            <svg class="w-5 h-5 text-gray-500 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4 bg-white rounded-b-lg">
                        <p class="text-gray-600">
                            The Global Currency Unit (GCU) is a concept for a basket currency that will combine multiple fiat currencies into a single, stable unit. The planned features include:
                        </p>
                        <ul class="list-disc list-inside mt-2 text-gray-600 space-y-1">
                            <li>Backing by USD, EUR, GBP, CHF, JPY, and XAU (gold)</li>
                            <li>Democratic governance through community voting (coming soon)</li>
                            <li>Reduced volatility compared to single currencies</li>
                            <li>Transparent composition and valuation</li>
                            <li>Real bank backing with government insurance (when launched)</li>
                        </ul>
                        <p class="text-gray-600 mt-3">
                            <strong>Note:</strong> GCU is currently available in sandbox mode. Production launch with live currency backing is planned for a future release.
                        </p>
                    </div>
                </div>

                <div class="faq-item" data-category="gcu">
                    <button class="faq-question w-full text-left px-6 py-4 bg-white rounded-lg hover:bg-gray-50 transition shadow-sm">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900">How will GCU voting work?</h3>
                            <svg class="w-5 h-5 text-gray-500 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4 bg-white rounded-b-lg">
                        <p class="text-gray-600">
                            When implemented, GCU voting will allow currency holders to participate in governance:
                        </p>
                        <ul class="list-disc list-inside mt-2 text-gray-600 space-y-1">
                            <li>Monthly voting cycles to adjust currency composition</li>
                            <li>Voting power proportional to GCU holdings</li>
                            <li>Community proposals for basket changes</li>
                            <li>Transparent vote counting and results</li>
                            <li>Automatic rebalancing based on vote outcomes</li>
                        </ul>
                        <p class="text-gray-600 mt-3">
                            <strong>Status:</strong> Voting functionality is on the roadmap for a future release.
                        </p>
                    </div>
                </div>

                <!-- Technical Questions -->
                <div class="faq-item" data-category="technical">
                    <button class="faq-question w-full text-left px-6 py-4 bg-white rounded-lg hover:bg-gray-50 transition shadow-sm">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900">What technology stack does FinAegis use?</h3>
                            <svg class="w-5 h-5 text-gray-500 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4 bg-white rounded-b-lg">
                        <p class="text-gray-600">
                            FinAegis is built with modern, scalable technologies:
                        </p>
                        <ul class="list-disc list-inside mt-2 text-gray-600 space-y-1">
                            <li><strong>Backend:</strong> Laravel (PHP framework)</li>
                            <li><strong>Frontend:</strong> Blade templates, Alpine.js, Tailwind CSS</li>
                            <li><strong>Database:</strong> MySQL/PostgreSQL compatible</li>
                            <li><strong>Queue:</strong> Laravel Queue with Redis</li>
                            <li><strong>API:</strong> RESTful JSON API</li>
                            <li><strong>Testing:</strong> PHPUnit and Pest</li>
                            <li><strong>Admin:</strong> Laravel Filament</li>
                        </ul>
                        <p class="text-gray-600 mt-3">
                            View the full tech stack on our GitHub repository.
                        </p>
                    </div>
                </div>

                <div class="faq-item" data-category="technical">
                    <button class="faq-question w-full text-left px-6 py-4 bg-white rounded-lg hover:bg-gray-50 transition shadow-sm">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900">How many API endpoints are available?</h3>
                            <svg class="w-5 h-5 text-gray-500 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4 bg-white rounded-b-lg">
                        <p class="text-gray-600">
                            Currently, we have {{ config('platform.statistics.api_endpoints') }} core API endpoints available:
                        </p>
                        <ul class="list-disc list-inside mt-2 text-gray-600 space-y-1">
                            <li>Authentication endpoints (login, register, logout)</li>
                            <li>Account management endpoints</li>
                            <li>Transaction endpoints (sandbox)</li>
                            <li>Currency conversion endpoints</li>
                            <li>User profile endpoints</li>
                        </ul>
                        <p class="text-gray-600 mt-3">
                            More endpoints will be added as we develop additional features. Check our API documentation for the latest information.
                        </p>
                    </div>
                </div>

                <!-- Future Features -->
                <div class="faq-item" data-category="future">
                    <button class="faq-question w-full text-left px-6 py-4 bg-white rounded-lg hover:bg-gray-50 transition shadow-sm">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900">What features are coming next?</h3>
                            <svg class="w-5 h-5 text-gray-500 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4 bg-white rounded-b-lg">
                        <p class="text-gray-600">
                            We have an exciting roadmap ahead:
                        </p>
                        <ul class="list-disc list-inside mt-2 text-gray-600 space-y-1">
                            <li><strong>Delivered:</strong> 42 domain modules, 143+ API endpoints, GraphQL, event sourcing</li>
                            <li><strong>Delivered:</strong> Mobile app backend, passkey auth, card issuing, KYC/AML</li>
                            <li><strong>Delivered:</strong> Cross-chain bridges, DeFi connectors, X402 micropayments</li>
                            <li><strong>Upcoming:</strong> GCU voting system, production bank integrations</li>
                            <li><strong>Upcoming:</strong> Live transaction processing, expanded mobile features</li>
                        </ul>
                        <p class="text-gray-600 mt-3">
                            Follow our GitHub repository for detailed progress updates.
                        </p>
                    </div>
                </div>

                <div class="faq-item" data-category="future">
                    <button class="faq-question w-full text-left px-6 py-4 bg-white rounded-lg hover:bg-gray-50 transition shadow-sm">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900">Will there be mobile apps?</h3>
                            <svg class="w-5 h-5 text-gray-500 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4 bg-white rounded-b-lg">
                        <p class="text-gray-600">
                            Yes! A cross-platform mobile app (Expo/React Native) is already available:
                        </p>
                        <ul class="list-disc list-inside mt-2 text-gray-600 space-y-1">
                            <li>iOS and Android via a single Expo codebase</li>
                            <li>Passkey and biometric authentication</li>
                            <li>Push notifications via Firebase Cloud Messaging</li>
                            <li>Payment intents, activity feed, and receipt management</li>
                            <li>Privacy relayer and ERC-4337 smart account integration</li>
                        </ul>
                        <p class="text-gray-600 mt-3">
                            The web platform is also fully responsive and works well on mobile browsers.
                        </p>
                    </div>
                </div>

                <!-- Contact Support -->
                <div class="mt-12 bg-indigo-50 rounded-xl p-8 text-center">
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Can't find what you're looking for?</h3>
                    <p class="text-gray-600 mb-6">
                        Our team is here to help. Reach out any time.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="{{ route('support.contact') }}" class="bg-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-700 transition">
                            Contact Support
                        </a>
                        <a href="https://github.com/FinAegis/core-banking-prototype-laravel/discussions" class="bg-white text-indigo-600 px-6 py-3 rounded-lg font-semibold border-2 border-indigo-600 hover:bg-indigo-50 transition">
                            Community Forum
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-gray-400 py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid md:grid-cols-4 gap-8">
                <div>
                    <h4 class="text-white font-semibold mb-4">Platform</h4>
                    <ul class="space-y-2">
                        <li><a href="/platform" class="hover:text-white transition">Overview</a></li>
                        <li><a href="/gcu" class="hover:text-white transition">GCU</a></li>
                        <li><a href="/sub-products" class="hover:text-white transition">Modules</a></li>
                        <li><a href="/pricing" class="hover:text-white transition">Pricing</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-4">Developers</h4>
                    <ul class="space-y-2">
                        <li><a href="/developers" class="hover:text-white transition">Documentation</a></li>
                        <li><a href="/developers/api-docs" class="hover:text-white transition">API Reference</a></li>
                        <li><a href="/developers/sdks" class="hover:text-white transition">SDKs</a></li>
                        <li><a href="/status" class="hover:text-white transition">System Status</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-4">Resources</h4>
                    <ul class="space-y-2">
                        <li><a href="/support" class="hover:text-white transition">Support</a></li>
                        <li><a href="/blog" class="hover:text-white transition">Blog</a></li>
                        <li><a href="/partners" class="hover:text-white transition">Partners</a></li>
                        <li><a href="/about" class="hover:text-white transition">About</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-4">Legal</h4>
                    <ul class="space-y-2">
                        <li><a href="/legal/terms" class="hover:text-white transition">Terms</a></li>
                        <li><a href="/legal/privacy" class="hover:text-white transition">Privacy</a></li>
                        <li><a href="/legal/cookies" class="hover:text-white transition">Cookies</a></li>
                        <li><a href="/support/faq" class="hover:text-white transition">FAQ</a></li>
                    </ul>
                </div>
            </div>
            <div class="mt-8 pt-8 border-t border-gray-800 text-center">
                <p>&copy; {{ date('Y') }} FinAegis. All rights reserved. Open Source Project.</p>
            </div>
        </div>
    </footer>

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
                    f.classList.add('bg-gray-200', 'text-gray-700');
                });
                filter.classList.remove('bg-gray-200', 'text-gray-700');
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