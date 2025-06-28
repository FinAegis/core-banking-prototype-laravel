<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="FinAegis Alpha Platform Guides - Learn how to use and contribute to our open source banking platform.">
        <meta name="keywords" content="FinAegis guides, alpha testing, documentation, tutorials, open source banking">
        
        <title>Support Guides - FinAegis Alpha</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <style>
            .gradient-bg {
                background: linear-gradient(135deg, #22c55e 0%, #3b82f6 100%);
            }
            .guide-card {
                transition: all 0.3s ease;
            }
            .guide-card:hover {
                transform: translateY(-4px);
                box-shadow: 0 12px 24px rgba(0,0,0,0.1);
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
                    <h1 class="text-5xl md:text-6xl font-bold mb-6">
                        Alpha Platform Guides
                    </h1>
                    <p class="text-xl md:text-2xl mb-8 text-green-100 max-w-4xl mx-auto">
                        Learn how to explore the FinAegis alpha platform and contribute to our open source project.
                    </p>
                    <div class="max-w-2xl mx-auto">
                        <div class="relative">
                            <input type="text" id="guide-search" placeholder="Search guides..." 
                                class="w-full px-6 py-3 rounded-lg text-gray-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-white pl-12">
                            <svg class="absolute left-4 top-3.5 w-5 h-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Quick Links -->
        <section class="py-16 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-3xl font-bold text-gray-900">Popular Guides</h2>
                    <p class="mt-4 text-xl text-gray-600">Start with these essential guides for alpha testing</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <a href="#getting-started" class="guide-card bg-white rounded-lg shadow-md border border-gray-200 p-6">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-gray-900 mb-2">Getting Started</h3>
                        <p class="text-gray-600 text-sm">Alpha platform overview</p>
                    </a>

                    <a href="#development" class="guide-card bg-white rounded-lg shadow-md border border-gray-200 p-6">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-gray-900 mb-2">Development Setup</h3>
                        <p class="text-gray-600 text-sm">Contributing to the project</p>
                    </a>

                    <a href="#testing" class="guide-card bg-white rounded-lg shadow-md border border-gray-200 p-6">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-gray-900 mb-2">Testing Features</h3>
                        <p class="text-gray-600 text-sm">Exploring demo functionality</p>
                    </a>

                    <a href="#feedback" class="guide-card bg-white rounded-lg shadow-md border border-gray-200 p-6">
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-gray-900 mb-2">Providing Feedback</h3>
                        <p class="text-gray-600 text-sm">Reporting issues & suggestions</p>
                    </a>
                </div>
            </div>
        </section>

        <!-- Guides Content -->
        <section class="py-16 bg-gray-50">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Getting Started -->
                <section id="getting-started" class="mb-16">
                    <div class="border-b border-gray-200 pb-6 mb-8">
                        <h2 class="text-2xl font-bold text-gray-900">Getting Started with Alpha Testing</h2>
                        <p class="mt-2 text-gray-600">Understanding the FinAegis alpha platform and its current state</p>
                    </div>
                    
                    <div class="space-y-6">
                        <div class="bg-white border border-gray-200 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">What is Alpha Testing?</h3>
                            <p class="text-gray-600 mb-4">The FinAegis platform is currently in alpha testing phase, which means:</p>
                            <ul class="list-disc list-inside text-gray-600 space-y-2">
                                <li>All transactions and balances are simulated (no real money)</li>
                                <li>Features are being actively developed and may change</li>
                                <li>Some functionality may be incomplete or unstable</li>
                                <li>Your feedback helps shape the platform</li>
                            </ul>
                            <div class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-lg">
                                <p class="text-amber-800 text-sm">
                                    <strong>Important:</strong> Do not attempt to use real financial information or expect real transactions during alpha testing.
                                </p>
                            </div>
                        </div>

                        <div class="bg-white border border-gray-200 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">Creating Your Test Account</h3>
                            <p class="text-gray-600 mb-4">Follow these steps to create an alpha testing account:</p>
                            <ol class="list-decimal list-inside text-gray-600 space-y-2">
                                <li>Click "Register" on the homepage</li>
                                <li>Use a test email address (can be your real email)</li>
                                <li>Create a password (this is for testing only)</li>
                                <li>Explore the demo dashboard with simulated data</li>
                            </ol>
                            <p class="text-gray-600 mt-4">
                                <strong>Note:</strong> No real identity verification is required during alpha testing.
                            </p>
                        </div>

                        <div class="bg-white border border-gray-200 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">Available Features in Alpha</h3>
                            <p class="text-gray-600 mb-4">Currently available for testing:</p>
                            <ul class="list-disc list-inside text-gray-600 space-y-2">
                                <li>User registration and authentication</li>
                                <li>Dashboard with simulated account data</li>
                                <li>GCU concept demonstration</li>
                                <li>Basic API endpoints ({{ config('platform.statistics.api_endpoints') }} endpoints)</li>
                                <li>Admin panel (for authorized testers)</li>
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- Development Setup -->
                <section id="development" class="mb-16">
                    <div class="border-b border-gray-200 pb-6 mb-8">
                        <h2 class="text-2xl font-bold text-gray-900">Development Setup</h2>
                        <p class="mt-2 text-gray-600">Contributing to the FinAegis open source project</p>
                    </div>
                    
                    <div class="space-y-6">
                        <div class="bg-white border border-gray-200 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">Local Development Environment</h3>
                            <p class="text-gray-600 mb-4">Set up FinAegis locally for development:</p>
                            <div class="bg-gray-50 rounded-lg p-4 font-mono text-sm">
                                <p class="text-gray-700"># Clone the repository</p>
                                <p class="text-green-600">git clone https://github.com/FinAegis/core-banking-prototype-laravel.git</p>
                                <p class="text-green-600">cd core-banking-prototype-laravel</p>
                                <br>
                                <p class="text-gray-700"># Install dependencies</p>
                                <p class="text-green-600">composer install</p>
                                <p class="text-green-600">npm install</p>
                                <br>
                                <p class="text-gray-700"># Configure environment</p>
                                <p class="text-green-600">cp .env.example .env</p>
                                <p class="text-green-600">php artisan key:generate</p>
                                <br>
                                <p class="text-gray-700"># Run migrations</p>
                                <p class="text-green-600">php artisan migrate</p>
                                <br>
                                <p class="text-gray-700"># Start development server</p>
                                <p class="text-green-600">php artisan serve</p>
                            </div>
                        </div>

                        <div class="bg-white border border-gray-200 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">Tech Stack Overview</h3>
                            <p class="text-gray-600 mb-4">FinAegis is built with:</p>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <h4 class="font-semibold text-gray-800 mb-2">Backend</h4>
                                    <ul class="text-sm text-gray-600 space-y-1">
                                        <li>• Laravel (PHP framework)</li>
                                        <li>• MySQL/PostgreSQL</li>
                                        <li>• Redis for queues</li>
                                        <li>• Laravel Sanctum for API auth</li>
                                    </ul>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-800 mb-2">Frontend</h4>
                                    <ul class="text-sm text-gray-600 space-y-1">
                                        <li>• Blade templates</li>
                                        <li>• Tailwind CSS</li>
                                        <li>• Alpine.js</li>
                                        <li>• Laravel Livewire</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="bg-white border border-gray-200 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">Contributing Guidelines</h3>
                            <p class="text-gray-600 mb-4">How to contribute to FinAegis:</p>
                            <ol class="list-decimal list-inside text-gray-600 space-y-2">
                                <li>Fork the repository on GitHub</li>
                                <li>Create a feature branch (<code class="bg-gray-100 px-2 py-1 rounded">git checkout -b feature/your-feature</code>)</li>
                                <li>Make your changes and test thoroughly</li>
                                <li>Run tests (<code class="bg-gray-100 px-2 py-1 rounded">./vendor/bin/pest</code>)</li>
                                <li>Commit with clear messages</li>
                                <li>Push to your fork and create a pull request</li>
                            </ol>
                        </div>
                    </div>
                </section>

                <!-- Testing Features -->
                <section id="testing" class="mb-16">
                    <div class="border-b border-gray-200 pb-6 mb-8">
                        <h2 class="text-2xl font-bold text-gray-900">Testing Platform Features</h2>
                        <p class="mt-2 text-gray-600">How to test and explore the demo functionality</p>
                    </div>
                    
                    <div class="space-y-6">
                        <div class="bg-white border border-gray-200 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">Simulated Transactions</h3>
                            <p class="text-gray-600 mb-4">Testing transaction flows:</p>
                            <ul class="list-disc list-inside text-gray-600 space-y-2">
                                <li>All balances are simulated (starting with demo funds)</li>
                                <li>Transactions don't involve real money or banks</li>
                                <li>Currency conversions use demo exchange rates</li>
                                <li>Transaction history is for demonstration only</li>
                            </ul>
                            <p class="text-gray-600 mt-4">
                                Feel free to test all transaction types without worry - nothing is real!
                            </p>
                        </div>

                        <div class="bg-white border border-gray-200 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">API Testing</h3>
                            <p class="text-gray-600 mb-4">Testing the REST API:</p>
                            <ol class="list-decimal list-inside text-gray-600 space-y-2">
                                <li>Register for an account to get API access</li>
                                <li>Use the API documentation at <code class="bg-gray-100 px-2 py-1 rounded">/developers/api-docs</code></li>
                                <li>Test with tools like Postman or curl</li>
                                <li>All API responses use simulated data</li>
                            </ol>
                            <div class="mt-4 bg-gray-50 rounded-lg p-4 font-mono text-sm">
                                <p class="text-gray-700"># Example API call</p>
                                <p class="text-green-600">curl -X GET http://localhost:8000/api/v1/accounts \</p>
                                <p class="text-green-600">  -H "Authorization: Bearer YOUR_TOKEN"</p>
                            </div>
                        </div>

                        <div class="bg-white border border-gray-200 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">GCU Demo Features</h3>
                            <p class="text-gray-600 mb-4">Exploring the Global Currency Unit concept:</p>
                            <ul class="list-disc list-inside text-gray-600 space-y-2">
                                <li>View the current GCU composition (demonstration values)</li>
                                <li>See how democratic voting will work (UI preview only)</li>
                                <li>Test currency conversion with GCU</li>
                                <li>Understand the multi-currency basket concept</li>
                            </ul>
                            <p class="text-gray-600 mt-4">
                                <strong>Note:</strong> Actual voting functionality will be implemented in beta phase.
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Providing Feedback -->
                <section id="feedback" class="mb-16">
                    <div class="border-b border-gray-200 pb-6 mb-8">
                        <h2 class="text-2xl font-bold text-gray-900">Providing Feedback</h2>
                        <p class="mt-2 text-gray-600">Help us improve FinAegis with your feedback</p>
                    </div>
                    
                    <div class="space-y-6">
                        <div class="bg-white border border-gray-200 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">Reporting Bugs</h3>
                            <p class="text-gray-600 mb-4">Found a bug? Here's how to report it:</p>
                            <ol class="list-decimal list-inside text-gray-600 space-y-2">
                                <li>Check if the issue already exists on GitHub</li>
                                <li>Create a new issue with a clear title</li>
                                <li>Provide steps to reproduce the bug</li>
                                <li>Include error messages or screenshots</li>
                                <li>Mention your environment (OS, browser, etc.)</li>
                            </ol>
                            <div class="mt-4">
                                <a href="https://github.com/FinAegis/core-banking-prototype-laravel/issues/new" class="text-indigo-600 font-medium hover:text-indigo-700">
                                    Create Bug Report →
                                </a>
                            </div>
                        </div>

                        <div class="bg-white border border-gray-200 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">Feature Requests</h3>
                            <p class="text-gray-600 mb-4">Have an idea for a new feature?</p>
                            <ul class="list-disc list-inside text-gray-600 space-y-2">
                                <li>Check our roadmap to see if it's planned</li>
                                <li>Create a GitHub issue with [Feature Request] tag</li>
                                <li>Describe the feature and its benefits</li>
                                <li>Provide use cases and examples</li>
                                <li>Join the discussion on existing requests</li>
                            </ul>
                        </div>

                        <div class="bg-white border border-gray-200 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">Community Discussion</h3>
                            <p class="text-gray-600 mb-4">Join our community:</p>
                            <ul class="list-disc list-inside text-gray-600 space-y-2">
                                <li>GitHub Discussions for general topics</li>
                                <li>Email info@finaegis.org for direct feedback</li>
                                <li>Star the repository to show support</li>
                                <li>Share the project with other developers</li>
                            </ul>
                            <div class="mt-4 flex gap-4">
                                <a href="https://github.com/FinAegis/core-banking-prototype-laravel/discussions" class="text-indigo-600 font-medium hover:text-indigo-700">
                                    Join Discussions →
                                </a>
                                <a href="mailto:info@finaegis.org" class="text-indigo-600 font-medium hover:text-indigo-700">
                                    Email Feedback →
                                </a>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Additional Resources -->
                <div class="bg-indigo-50 rounded-xl p-8 text-center">
                    <h3 class="text-xl font-semibold text-gray-900 mb-4">Need More Help?</h3>
                    <p class="text-gray-600 mb-6">
                        Can't find what you're looking for? Check out these additional resources.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="{{ route('support.faq') }}" class="bg-white text-indigo-600 px-6 py-3 rounded-lg font-semibold border-2 border-indigo-600 hover:bg-indigo-50 transition">
                            Browse FAQ
                        </a>
                        <a href="{{ route('support.contact') }}" class="bg-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-700 transition">
                            Contact Support
                        </a>
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
            // Smooth scrolling for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });

            // Search functionality
            const searchInput = document.getElementById('guide-search');
            searchInput.addEventListener('input', (e) => {
                const searchTerm = e.target.value.toLowerCase();
                
                document.querySelectorAll('section[id]').forEach(section => {
                    const content = section.textContent.toLowerCase();
                    
                    if (searchTerm === '' || content.includes(searchTerm)) {
                        section.style.display = 'block';
                    } else {
                        section.style.display = 'none';
                    }
                });
            });
        </script>
    </body>
</html>