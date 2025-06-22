<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Frequently asked questions about FinAegis, Global Currency Unit (GCU), banking services, and platform features.">
    
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
            max-height: 500px;
        }
    </style>
</head>
<body class="antialiased">
    <!-- Navigation -->
    @include('partials.public-nav')

    <!-- Hero Section -->
    <section class="pt-16 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="text-center">
                <h1 class="text-5xl font-bold text-gray-900 mb-6">Frequently Asked Questions</h1>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    Find answers to common questions about FinAegis and the Global Currency Unit.
                </p>
                
                <!-- Search Bar -->
                <div class="mt-8 max-w-xl mx-auto">
                    <div class="relative">
                        <input type="text" id="faq-search" placeholder="Search for answers..."
                            class="w-full px-6 py-4 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <svg class="absolute right-4 top-4 w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Categories -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <!-- Category Navigation -->
            <div class="flex flex-wrap justify-center gap-4 mb-12">
                <button class="category-btn active px-6 py-3 bg-indigo-600 text-white rounded-lg font-medium" data-category="all">
                    All Questions
                </button>
                <button class="category-btn px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300" data-category="getting-started">
                    Getting Started
                </button>
                <button class="category-btn px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300" data-category="gcu">
                    GCU & Voting
                </button>
                <button class="category-btn px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300" data-category="security">
                    Security
                </button>
                <button class="category-btn px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300" data-category="fees">
                    Fees & Pricing
                </button>
                <button class="category-btn px-6 py-3 bg-gray-200 text-gray-700 rounded-lg font-medium hover:bg-gray-300" data-category="api">
                    API & Integration
                </button>
            </div>

            <!-- FAQ Items -->
            <div class="max-w-4xl mx-auto space-y-4">
                <!-- Getting Started Questions -->
                <div class="faq-item" data-category="getting-started">
                    <button class="faq-question w-full text-left px-6 py-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900">How do I create a FinAegis account?</h3>
                            <svg class="w-5 h-5 text-gray-500 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4">
                        <p class="text-gray-600">
                            Creating an account is simple:
                        </p>
                        <ol class="list-decimal list-inside mt-2 text-gray-600 space-y-1">
                            <li>Click "Get Started" on our homepage</li>
                            <li>Enter your email and create a password</li>
                            <li>Complete the KYC verification process</li>
                            <li>Fund your account via bank transfer or card</li>
                            <li>Start using FinAegis services immediately</li>
                        </ol>
                    </div>
                </div>

                <div class="faq-item" data-category="getting-started">
                    <button class="faq-question w-full text-left px-6 py-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900">What documents do I need for KYC verification?</h3>
                            <svg class="w-5 h-5 text-gray-500 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4">
                        <p class="text-gray-600">
                            For individual accounts, you'll need:
                        </p>
                        <ul class="list-disc list-inside mt-2 text-gray-600 space-y-1">
                            <li>Valid government-issued ID (passport, driver's license, or national ID)</li>
                            <li>Proof of address (utility bill, bank statement, or rental agreement)</li>
                            <li>Selfie for identity verification</li>
                        </ul>
                        <p class="text-gray-600 mt-3">
                            Business accounts require additional documentation including business registration and ownership proof.
                        </p>
                    </div>
                </div>

                <!-- GCU Questions -->
                <div class="faq-item" data-category="gcu">
                    <button class="faq-question w-full text-left px-6 py-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900">What is the Global Currency Unit (GCU)?</h3>
                            <svg class="w-5 h-5 text-gray-500 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4">
                        <p class="text-gray-600">
                            The Global Currency Unit (GCU) is a revolutionary basket currency that combines multiple fiat currencies into a single, stable unit of value. Key features include:
                        </p>
                        <ul class="list-disc list-inside mt-2 text-gray-600 space-y-1">
                            <li>Backed by USD, EUR, GBP, CHF, JPY, and XAU (gold)</li>
                            <li>Democratic governance through monthly voting</li>
                            <li>Reduced volatility compared to single currencies</li>
                            <li>Real-time rebalancing based on community decisions</li>
                            <li>Transparent composition and valuation</li>
                        </ul>
                    </div>
                </div>

                <div class="faq-item" data-category="gcu">
                    <button class="faq-question w-full text-left px-6 py-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900">How does GCU voting work?</h3>
                            <svg class="w-5 h-5 text-gray-500 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4">
                        <p class="text-gray-600">
                            GCU holders participate in monthly governance votes to determine the basket composition:
                        </p>
                        <ul class="list-disc list-inside mt-2 text-gray-600 space-y-1">
                            <li>Voting occurs on the first week of each month</li>
                            <li>Your voting power equals your GCU balance</li>
                            <li>Vote on percentage allocations for each currency</li>
                            <li>Results are weighted by voting power</li>
                            <li>New composition takes effect immediately after voting closes</li>
                        </ul>
                    </div>
                </div>

                <!-- Security Questions -->
                <div class="faq-item" data-category="security">
                    <button class="faq-question w-full text-left px-6 py-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900">How secure is FinAegis?</h3>
                            <svg class="w-5 h-5 text-gray-500 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4">
                        <p class="text-gray-600">
                            FinAegis employs bank-grade security measures:
                        </p>
                        <ul class="list-disc list-inside mt-2 text-gray-600 space-y-1">
                            <li>Quantum-resistant encryption (SHA3-512)</li>
                            <li>Multi-factor authentication (MFA)</li>
                            <li>Cold storage for majority of funds</li>
                            <li>Real-time fraud detection</li>
                            <li>Regular third-party security audits</li>
                            <li>GDPR compliant data protection</li>
                            <li>24/7 security monitoring</li>
                        </ul>
                    </div>
                </div>

                <div class="faq-item" data-category="security">
                    <button class="faq-question w-full text-left px-6 py-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900">Is my money insured?</h3>
                            <svg class="w-5 h-5 text-gray-500 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4">
                        <p class="text-gray-600">
                            Yes, your funds are protected through multiple layers:
                        </p>
                        <ul class="list-disc list-inside mt-2 text-gray-600 space-y-1">
                            <li>Funds held at partner banks are covered by deposit insurance</li>
                            <li>Additional insurance coverage up to $250,000 per account</li>
                            <li>Segregated customer accounts</li>
                            <li>Regular audits and reconciliation</li>
                        </ul>
                    </div>
                </div>

                <!-- Fees Questions -->
                <div class="faq-item" data-category="fees">
                    <button class="faq-question w-full text-left px-6 py-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900">What are the fees for using FinAegis?</h3>
                            <svg class="w-5 h-5 text-gray-500 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4">
                        <p class="text-gray-600">
                            Our fee structure is transparent and competitive:
                        </p>
                        <ul class="list-disc list-inside mt-2 text-gray-600 space-y-1">
                            <li>Account opening: Free</li>
                            <li>Monthly maintenance: Free for balances over Ǥ1,000</li>
                            <li>Deposits: Free via bank transfer, 2.5% for cards</li>
                            <li>Withdrawals: $5 flat fee</li>
                            <li>Currency conversion: 0.5% spread</li>
                            <li>International transfers: $15 + 0.25%</li>
                        </ul>
                    </div>
                </div>

                <div class="faq-item" data-category="fees">
                    <button class="faq-question w-full text-left px-6 py-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900">Are there any hidden fees?</h3>
                            <svg class="w-5 h-5 text-gray-500 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4">
                        <p class="text-gray-600">
                            No, we believe in complete transparency. All fees are clearly displayed before you confirm any transaction. There are no hidden charges, and you can view our complete fee schedule in your account settings.
                        </p>
                    </div>
                </div>

                <!-- API Questions -->
                <div class="faq-item" data-category="api">
                    <button class="faq-question w-full text-left px-6 py-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900">How do I integrate FinAegis API?</h3>
                            <svg class="w-5 h-5 text-gray-500 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4">
                        <p class="text-gray-600">
                            Getting started with our API is straightforward:
                        </p>
                        <ol class="list-decimal list-inside mt-2 text-gray-600 space-y-1">
                            <li>Create an API key in your dashboard</li>
                            <li>Review our API documentation</li>
                            <li>Choose an SDK (Node.js, Python, PHP, Java)</li>
                            <li>Test in our sandbox environment</li>
                            <li>Go live with production credentials</li>
                        </ol>
                        <p class="text-gray-600 mt-3">
                            Our developer support team is available to help with integration.
                        </p>
                    </div>
                </div>

                <div class="faq-item" data-category="api">
                    <button class="faq-question w-full text-left px-6 py-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-semibold text-gray-900">What are the API rate limits?</h3>
                            <svg class="w-5 h-5 text-gray-500 transform transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </div>
                    </button>
                    <div class="faq-answer px-6 py-4">
                        <p class="text-gray-600">
                            API rate limits vary by plan:
                        </p>
                        <ul class="list-disc list-inside mt-2 text-gray-600 space-y-1">
                            <li>Free tier: 60 requests/minute, 1,000/hour</li>
                            <li>Standard: 300 requests/minute, 10,000/hour</li>
                            <li>Premium: 1,000 requests/minute, 100,000/hour</li>
                            <li>Enterprise: Custom limits available</li>
                        </ul>
                        <p class="text-gray-600 mt-3">
                            Rate limit headers are included in all API responses.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Still Have Questions -->
            <div class="mt-16 text-center">
                <div class="bg-gradient-to-r from-indigo-500 to-purple-600 rounded-2xl p-8 text-white">
                    <h2 class="text-2xl font-bold mb-4">Still have questions?</h2>
                    <p class="mb-6">Our support team is here to help you 24/7</p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="{{ route('support.contact') }}" class="bg-white text-indigo-600 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 transition">
                            Contact Support
                        </a>
                        <button onclick="alert('Live chat coming soon!')" class="border-2 border-white text-white px-6 py-3 rounded-lg font-semibold hover:bg-white hover:text-indigo-600 transition">
                            Start Live Chat
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    @include('partials.footer')

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
        document.querySelectorAll('.category-btn').forEach(button => {
            button.addEventListener('click', () => {
                const category = button.dataset.category;
                
                // Update active button
                document.querySelectorAll('.category-btn').forEach(btn => {
                    btn.classList.remove('bg-indigo-600', 'text-white');
                    btn.classList.add('bg-gray-200', 'text-gray-700');
                });
                button.classList.remove('bg-gray-200', 'text-gray-700');
                button.classList.add('bg-indigo-600', 'text-white');
                
                // Filter FAQs
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
        document.getElementById('faq-search').addEventListener('input', (e) => {
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