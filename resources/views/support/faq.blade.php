<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Frequently asked questions about FinAegis alpha platform, Global Currency Unit (GCU), and upcoming features.">
    
    <title>FAQ - Frequently Asked Questions | FinAegis</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
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
</head>
<body class="antialiased bg-gray-50">
    <x-alpha-banner />
    
    <!-- Spacer for fixed banner -->
    <div class="h-12"></div>
    
    <x-main-navigation />

    <!-- Hero Section -->
    <section class="pt-16 pb-20 gradient-bg text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h1 class="text-5xl font-bold mb-6">Frequently Asked Questions</h1>
                <p class="text-xl text-purple-100 max-w-3xl mx-auto">
                    Find answers to common questions about FinAegis alpha platform and the Global Currency Unit concept.
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
                    Alpha Testing
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
                
                <!-- Alpha Testing Questions -->
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
                            FinAegis is currently in alpha testing phase. This means:
                        </p>
                        <ul class="list-disc list-inside mt-2 text-gray-600 space-y-1">
                            <li>No real money transactions are processed yet</li>
                            <li>No actual bank integrations are active</li>
                            <li>The platform is for demonstration and testing purposes only</li>
                            <li>Features are being actively developed and may change</li>
                            <li>We're gathering feedback to improve the platform</li>
                        </ul>
                        <p class="text-gray-600 mt-3">
                            We expect to launch beta testing in Q2 2025 with limited real transactions.
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
                            No, the platform currently does not support real money transactions. During the alpha phase:
                        </p>
                        <ul class="list-disc list-inside mt-2 text-gray-600 space-y-1">
                            <li>All balances and transactions are simulated</li>
                            <li>No real bank accounts are connected</li>
                            <li>No actual currency conversions occur</li>
                            <li>Payment integrations are disabled</li>
                        </ul>
                        <p class="text-gray-600 mt-3">
                            This allows us to test and refine features safely before handling real funds.
                        </p>
                    </div>
                </div>

                <!-- Getting Started Questions -->
                <div class="faq-item" data-category="getting-started">
                    <button class="faq-question w-full text-left px-6 py-4 bg-white rounded-lg hover:bg-gray-50 transition shadow-sm">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900">How do I participate in alpha testing?</h3>
                            <svg class="w-5 h-5 text-gray-500 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4 bg-white rounded-b-lg">
                        <p class="text-gray-600">
                            To participate in alpha testing:
                        </p>
                        <ol class="list-decimal list-inside mt-2 text-gray-600 space-y-1">
                            <li>Register for a free account on the platform</li>
                            <li>Explore the demo features and simulated transactions</li>
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
                            <strong>Note:</strong> GCU is currently a demonstration concept. Real implementation will begin in beta phase.
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
                            <strong>Status:</strong> Voting functionality is planned for Q3 2025 after beta launch.
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
                            <li>Transaction endpoints (simulated)</li>
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
                            <li><strong>Q2 2025:</strong> Beta launch with limited real transactions</li>
                            <li><strong>Q3 2025:</strong> GCU voting system implementation</li>
                            <li><strong>Q4 2025:</strong> API key management and SDKs</li>
                            <li><strong>2026:</strong> Exchange module, lending module, stablecoins</li>
                            <li><strong>Future:</strong> Mobile apps, advanced trading features</li>
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
                            Yes, mobile apps are on our roadmap:
                        </p>
                        <ul class="list-disc list-inside mt-2 text-gray-600 space-y-1">
                            <li>iOS and Android native apps planned</li>
                            <li>Expected release after platform stabilization</li>
                            <li>Will include all core platform features</li>
                            <li>Biometric authentication support</li>
                            <li>Push notifications for transactions</li>
                        </ul>
                        <p class="text-gray-600 mt-3">
                            In the meantime, our web platform is fully responsive and works well on mobile browsers.
                        </p>
                    </div>
                </div>

                <!-- Contact Support -->
                <div class="mt-12 bg-indigo-50 rounded-xl p-8 text-center">
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Can't find what you're looking for?</h3>
                    <p class="text-gray-600 mb-6">
                        Our team is here to help during the alpha testing phase.
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
</body>
</html>