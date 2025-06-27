<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="FinAegis Pricing - Open source financial platform. Free for development, paid for commercial use.">
        <meta name="keywords" content="FinAegis pricing, open source banking, financial platform pricing">
        
        <title>Pricing - FinAegis</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <style>
            .gradient-bg {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }
            .gradient-text {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
            }
            .pricing-card {
                transition: all 0.3s ease;
                position: relative;
            }
            .pricing-card:hover {
                transform: translateY(-8px);
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            }
            .popular-badge {
                position: absolute;
                top: -12px;
                right: 24px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 4px 16px;
                border-radius: 20px;
                font-size: 14px;
                font-weight: 600;
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
                        Open Source, Enterprise Ready
                    </h1>
                    <p class="text-xl md:text-2xl mb-8 text-purple-100 max-w-4xl mx-auto">
                        FinAegis is open source and free for development. Commercial licenses coming soon for production deployments.
                    </p>
                </div>
            </div>
        </section>

        <!-- Pricing Section -->
        <section class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">Choose Your Path</h2>
                    <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                        Start building today with our open source platform. Scale with confidence using our commercial licenses.
                    </p>
                </div>

                <!-- Pricing Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8 max-w-5xl mx-auto">
                    <!-- Open Source -->
                    <div class="pricing-card bg-white rounded-2xl shadow-lg border border-gray-200 p-8">
                        <div class="text-center mb-8">
                            <h3 class="text-2xl font-bold text-gray-900 mb-2">Open Source</h3>
                            <p class="text-gray-600">For developers and non-commercial use</p>
                            <div class="mt-6">
                                <span class="text-5xl font-bold gradient-text">Free</span>
                            </div>
                            <p class="text-sm text-gray-500 mt-2">Forever</p>
                        </div>
                        
                        <ul class="space-y-4 mb-8">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span class="text-gray-700">Full source code access</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span class="text-gray-700">Community support</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span class="text-gray-700">Development & testing</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span class="text-gray-700">MIT License</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-gray-400 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                <span class="text-gray-500">No production use</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-gray-400 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                </svg>
                                <span class="text-gray-500">No commercial support</span>
                            </li>
                        </ul>
                        
                        <a href="https://github.com/FinAegis/core-banking-prototype-laravel" class="block w-full text-center px-6 py-3 bg-gray-100 text-gray-700 rounded-lg font-semibold hover:bg-gray-200 transition">
                            View on GitHub
                        </a>
                    </div>

                    <!-- Professional -->
                    <div class="pricing-card bg-white rounded-2xl shadow-xl border-2 border-indigo-600 p-8 relative">
                        <span class="popular-badge">Most Popular</span>
                        <div class="text-center mb-8">
                            <h3 class="text-2xl font-bold text-gray-900 mb-2">Professional</h3>
                            <p class="text-gray-600">For startups and small businesses</p>
                            <div class="mt-6">
                                <span class="text-5xl font-bold text-gray-900">€499</span>
                                <span class="text-gray-600">/month</span>
                            </div>
                            <p class="text-sm text-gray-500 mt-2">Coming Q2 2025</p>
                        </div>
                        
                        <ul class="space-y-4 mb-8">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span class="text-gray-700">Everything in Open Source</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span class="text-gray-700">Commercial license</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span class="text-gray-700">Production deployment</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span class="text-gray-700">Email support</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span class="text-gray-700">99.9% uptime SLA</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span class="text-gray-700">Security updates</span>
                            </li>
                        </ul>
                        
                        <button disabled class="block w-full text-center px-6 py-3 bg-indigo-600 text-white rounded-lg font-semibold opacity-50 cursor-not-allowed">
                            Coming Soon
                        </button>
                    </div>

                    <!-- Enterprise -->
                    <div class="pricing-card bg-white rounded-2xl shadow-lg border border-gray-200 p-8">
                        <div class="text-center mb-8">
                            <h3 class="text-2xl font-bold text-gray-900 mb-2">Enterprise</h3>
                            <p class="text-gray-600">For large organizations</p>
                            <div class="mt-6">
                                <span class="text-5xl font-bold text-gray-900">Custom</span>
                            </div>
                            <p class="text-sm text-gray-500 mt-2">Contact sales</p>
                        </div>
                        
                        <ul class="space-y-4 mb-8">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span class="text-gray-700">Everything in Professional</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span class="text-gray-700">On-premise deployment</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span class="text-gray-700">Dedicated support team</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span class="text-gray-700">Custom integrations</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span class="text-gray-700">White-label options</span>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mr-3 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <span class="text-gray-700">Training & onboarding</span>
                            </li>
                        </ul>
                        
                        <a href="{{ route('support.contact') }}" class="block w-full text-center px-6 py-3 bg-gray-900 text-white rounded-lg font-semibold hover:bg-gray-800 transition">
                            Contact Sales
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Transaction Fees Section -->
        <section class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Platform Usage Fees</h2>
                    <p class="text-xl text-gray-600">Transparent pricing for platform services</p>
                </div>
                
                <div class="bg-white rounded-2xl shadow-lg overflow-hidden max-w-4xl mx-auto">
                    <table class="w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-4 text-left text-sm font-semibold text-gray-900">Service</th>
                                <th class="px-6 py-4 text-center text-sm font-semibold text-gray-900">Fee</th>
                                <th class="px-6 py-4 text-center text-sm font-semibold text-gray-900">Notes</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <tr>
                                <td class="px-6 py-4">GCU Currency Conversion</td>
                                <td class="px-6 py-4 text-center font-semibold">{{ config('platform.pricing.platform_fee') }}</td>
                                <td class="px-6 py-4 text-center text-gray-600">During alpha testing</td>
                            </tr>
                            <tr class="bg-gray-50">
                                <td class="px-6 py-4">Bank Transfers</td>
                                <td class="px-6 py-4 text-center font-semibold">Free</td>
                                <td class="px-6 py-4 text-center text-gray-600">Internal transfers only</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4">API Access</td>
                                <td class="px-6 py-4 text-center font-semibold">Free</td>
                                <td class="px-6 py-4 text-center text-gray-600">{{ config('platform.api.rate_limit') }}</td>
                            </tr>
                            <tr class="bg-gray-50">
                                <td class="px-6 py-4">Exchange Module</td>
                                <td class="px-6 py-4 text-center text-gray-500">TBD</td>
                                <td class="px-6 py-4 text-center text-gray-500">{{ config('platform.sub_products.exchange.launch_date') }}</td>
                            </tr>
                            <tr>
                                <td class="px-6 py-4">Lending Module</td>
                                <td class="px-6 py-4 text-center text-gray-500">TBD</td>
                                <td class="px-6 py-4 text-center text-gray-500">{{ config('platform.sub_products.lending.launch_date') }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section class="py-20 bg-white">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-4">Frequently Asked Questions</h2>
                </div>
                
                <div class="space-y-6">
                    <div class="bg-gray-50 rounded-lg p-6">
                        <h3 class="text-lg font-semibold mb-2">Can I use FinAegis for free?</h3>
                        <p class="text-gray-600">
                            Yes! FinAegis is open source and free to use for development, testing, and non-commercial purposes. You'll need a commercial license for production use.
                        </p>
                    </div>
                    
                    <div class="bg-gray-50 rounded-lg p-6">
                        <h3 class="text-lg font-semibold mb-2">What's included in the commercial license?</h3>
                        <p class="text-gray-600">
                            Commercial licenses include production deployment rights, professional support, security updates, and SLA guarantees. Enterprise licenses add custom integrations and white-label options.
                        </p>
                    </div>
                    
                    <div class="bg-gray-50 rounded-lg p-6">
                        <h3 class="text-lg font-semibold mb-2">When will commercial licenses be available?</h3>
                        <p class="text-gray-600">
                            We're planning to launch commercial licenses in Q2 2025. Join our alpha program now to get early access and special pricing.
                        </p>
                    </div>
                    
                    <div class="bg-gray-50 rounded-lg p-6">
                        <h3 class="text-lg font-semibold mb-2">Do I need a license for each module?</h3>
                        <p class="text-gray-600">
                            No. The commercial license covers the entire platform including all current and future modules. Module usage may have additional transaction fees.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-20 gradient-bg text-white">
            <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
                <h2 class="text-4xl font-bold mb-6">Start Building Today</h2>
                <p class="text-xl mb-8 text-purple-100">
                    Join our open source community and help shape the future of finance
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="https://github.com/FinAegis/core-banking-prototype-laravel" class="bg-white text-indigo-600 px-8 py-4 rounded-lg font-semibold text-lg hover:bg-gray-100 transition inline-block">
                        View on GitHub
                    </a>
                    <a href="{{ route('register') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg font-semibold text-lg hover:bg-white/20 transition inline-block">
                        Try Alpha Version
                    </a>
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
                            <li><a href="/developers/api" class="hover:text-white transition">API Reference</a></li>
                            <li><a href="https://github.com/FinAegis/core-banking-prototype-laravel" class="hover:text-white transition">GitHub</a></li>
                            <li><a href="/status" class="hover:text-white transition">System Status</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold mb-4">Company</h4>
                        <ul class="space-y-2">
                            <li><a href="/about" class="hover:text-white transition">About Us</a></li>
                            <li><a href="/blog" class="hover:text-white transition">Blog</a></li>
                            <li><a href="/partners" class="hover:text-white transition">Partners</a></li>
                            <li><a href="/support/contact" class="hover:text-white transition">Contact</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold mb-4">Legal</h4>
                        <ul class="space-y-2">
                            <li><a href="/legal/terms" class="hover:text-white transition">Terms of Service</a></li>
                            <li><a href="/legal/privacy" class="hover:text-white transition">Privacy Policy</a></li>
                            <li><a href="/legal/cookies" class="hover:text-white transition">Cookie Policy</a></li>
                            <li><a href="/support/faq" class="hover:text-white transition">FAQ</a></li>
                        </ul>
                    </div>
                </div>
                <div class="mt-8 pt-8 border-t border-gray-800 text-center">
                    <p>&copy; {{ date('Y') }} FinAegis. All rights reserved. Open Source Project.</p>
                </div>
            </div>
        </footer>
    </body>
</html>