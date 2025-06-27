<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="FinAegis - The future of democratic banking with Global Currency Unit (GCU). Multi-asset support, real-time settlements, and community governance.">
        <meta name="keywords" content="banking, GCU, global currency unit, multi-asset, democratic banking, fintech">
        
        <!-- Open Graph -->
        <meta property="og:title" content="FinAegis - Democratic Banking Platform">
        <meta property="og:description" content="Experience the future of banking with Global Currency Unit. Democratic governance, multi-asset support, and real bank integration.">
        <meta property="og:type" content="website">
        <meta property="og:url" content="{{ url('/') }}">

        <title>FinAegis - Core Banking as a Service</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        
        <!-- Custom Styles -->
        <style>
            .gradient-bg {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }
            .feature-card {
                transition: transform 0.3s ease, box-shadow 0.3s ease;
            }
            .feature-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            }
            .stat-number {
                font-size: 3rem;
                font-weight: 800;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
            }
        </style>
    </head>
    <body class="antialiased">
        <x-main-navigation />

        <!-- Hero Section -->
        <section class="pt-16 gradient-bg text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
                <div class="text-center">
                    <h1 class="text-5xl md:text-6xl font-bold mb-6">
                        Experience the Global Currency Unit (GCU)
                    </h1>
                    <p class="text-xl md:text-2xl mb-8 text-purple-100 max-w-3xl mx-auto">
                        The future of democratic finance. Built on real banking infrastructure, governed by the community, backed by a basket of global currencies.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition shadow-lg hover:shadow-xl">
                            Open Free Account
                        </a>
                        <a href="#how-it-works" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">
                            Learn More
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Wave SVG -->
            <div class="relative">
                <svg class="absolute bottom-0 w-full h-24 -mb-1 text-white" preserveAspectRatio="none" viewBox="0 0 1440 74">
                    <path fill="currentColor" d="M0,32L48,37.3C96,43,192,53,288,58.7C384,64,480,64,576,58.7C672,53,768,43,864,42.7C960,43,1056,53,1152,58.7C1248,64,1344,64,1392,64L1440,64L1440,74L1392,74C1344,74,1248,74,1152,74C1056,74,960,74,864,74C768,74,672,74,576,74C480,74,384,74,288,74C192,74,96,74,48,74L0,74Z"></path>
                </svg>
            </div>
        </section>

        <!-- Stats Section -->
        <section class="py-16 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-8 text-center">
                    <div>
                        <div class="stat-number">Ǥ</div>
                        <p class="text-gray-600 font-semibold">Global Currency Unit</p>
                    </div>
                    <div>
                        <div class="stat-number">6+</div>
                        <p class="text-gray-600 font-semibold">Supported Assets</p>
                    </div>
                    <div>
                        <div class="stat-number">100%</div>
                        <p class="text-gray-600 font-semibold">Bank Backed</p>
                    </div>
                    <div>
                        <div class="stat-number">24/7</div>
                        <p class="text-gray-600 font-semibold">Global Access</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Features Section -->
        <section id="features" class="py-20 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">Revolutionary Banking Features</h2>
                    <p class="text-xl text-gray-600 max-w-2xl mx-auto">
                        Built on cutting-edge technology with security and transparency at its core
                    </p>
                </div>

                <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-8">
                    <!-- Feature 1 -->
                    <div class="feature-card bg-white rounded-xl p-8 shadow-md">
                        <div class="w-16 h-16 bg-indigo-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Democratic Basket Currency</h3>
                        <p class="text-gray-600">
                            GCU composition is determined by community voting. Every holder has a say in the currency basket allocation.
                        </p>
                    </div>

                    <!-- Feature 2 -->
                    <div class="feature-card bg-white rounded-xl p-8 shadow-md">
                        <div class="w-16 h-16 bg-purple-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Real Bank Integration</h3>
                        <p class="text-gray-600">
                            Connected to major European banks including Santander, Deutsche Bank, and Paysera for real-world settlements.
                        </p>
                    </div>

                    <!-- Feature 3 -->
                    <div class="feature-card bg-white rounded-xl p-8 shadow-md">
                        <div class="w-16 h-16 bg-green-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Bank-Grade Security</h3>
                        <p class="text-gray-600">
                            Quantum-resistant cryptography, multi-signature controls, and full regulatory compliance built-in.
                        </p>
                    </div>

                    <!-- Feature 4 -->
                    <div class="feature-card bg-white rounded-xl p-8 shadow-md">
                        <div class="w-16 h-16 bg-yellow-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Instant Global Transfers</h3>
                        <p class="text-gray-600">
                            Send money anywhere in the world instantly. Multi-currency support with competitive exchange rates.
                        </p>
                    </div>

                    <!-- Feature 5 -->
                    <div class="feature-card bg-white rounded-xl p-8 shadow-md">
                        <div class="w-16 h-16 bg-red-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Full Transparency</h3>
                        <p class="text-gray-600">
                            Real-time reserves, open-source code, and complete audit trails. Every transaction is verifiable.
                        </p>
                    </div>

                    <!-- Feature 6 -->
                    <div class="feature-card bg-white rounded-xl p-8 shadow-md">
                        <div class="w-16 h-16 bg-blue-100 rounded-lg flex items-center justify-center mb-6">
                            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                        </div>
                        <h3 class="text-xl font-semibold mb-3">Community Governed</h3>
                        <p class="text-gray-600">
                            Democratic voting on all major decisions. Your GCU holdings determine your voting power.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- How It Works Section -->
        <section id="how-it-works" class="py-20 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-16">
                    <h2 class="text-4xl font-bold text-gray-900 mb-4">How It Works</h2>
                    <p class="text-xl text-gray-600">Get started with FinAegis in three simple steps</p>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <!-- Step 1 -->
                    <div class="text-center">
                        <div class="w-20 h-20 bg-indigo-600 text-white rounded-full flex items-center justify-center text-2xl font-bold mx-auto mb-6">1</div>
                        <h3 class="text-xl font-semibold mb-3">Create Your Account</h3>
                        <p class="text-gray-600">Sign up in minutes with our streamlined KYC process. Choose between personal and business accounts.</p>
                    </div>
                    
                    <!-- Step 2 -->
                    <div class="text-center">
                        <div class="w-20 h-20 bg-indigo-600 text-white rounded-full flex items-center justify-center text-2xl font-bold mx-auto mb-6">2</div>
                        <h3 class="text-xl font-semibold mb-3">Fund Your Wallet</h3>
                        <p class="text-gray-600">Deposit funds via bank transfer or card. Convert between multiple currencies including GCU.</p>
                    </div>
                    
                    <!-- Step 3 -->
                    <div class="text-center">
                        <div class="w-20 h-20 bg-indigo-600 text-white rounded-full flex items-center justify-center text-2xl font-bold mx-auto mb-6">3</div>
                        <h3 class="text-xl font-semibold mb-3">Start Banking</h3>
                        <p class="text-gray-600">Send money globally, vote on governance decisions, and enjoy democratic banking.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Developer Section -->
        <section class="py-16 bg-gray-50">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="lg:grid lg:grid-cols-2 lg:gap-16 items-center">
                    <div>
                        <h3 class="text-3xl font-bold text-gray-900 mb-4">Built for Developers</h3>
                        <p class="text-lg text-gray-600 mb-6">
                            Integrate FinAegis into your applications with our comprehensive APIs and SDKs. Build the future of finance with enterprise-grade infrastructure.
                        </p>
                        <div class="space-y-4">
                            <div class="flex items-start">
                                <svg class="w-6 h-6 text-green-500 mt-1 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <div>
                                    <h4 class="font-semibold text-gray-900">RESTful APIs</h4>
                                    <p class="text-gray-600">Well-documented endpoints for all banking operations</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <svg class="w-6 h-6 text-green-500 mt-1 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <div>
                                    <h4 class="font-semibold text-gray-900">Webhooks & Events</h4>
                                    <p class="text-gray-600">Real-time notifications for all account activities</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <svg class="w-6 h-6 text-green-500 mt-1 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                </svg>
                                <div>
                                    <h4 class="font-semibold text-gray-900">SDKs & Libraries</h4>
                                    <p class="text-gray-600">Native support for PHP, Python, JavaScript, and more</p>
                                </div>
                            </div>
                        </div>
                        <div class="mt-8">
                            <a href="/developers" class="inline-flex items-center text-indigo-600 font-semibold hover:text-indigo-700">
                                Explore Documentation
                                <svg class="w-5 h-5 ml-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                                </svg>
                            </a>
                        </div>
                    </div>
                    <div class="mt-10 lg:mt-0">
                        <div class="bg-gray-900 rounded-lg p-6 text-gray-300 font-mono text-sm overflow-x-auto">
                            <div class="text-green-400 mb-2"># Create a new GCU transfer</div>
                            <div>curl -X POST https://api.finaegis.com/v2/transfers \</div>
                            <div class="ml-4">-H "Authorization: Bearer YOUR_API_KEY" \</div>
                            <div class="ml-4">-H "Content-Type: application/json" \</div>
                            <div class="ml-4">-d '{</div>
                            <div class="ml-8">"from_account": "acc_123456",</div>
                            <div class="ml-8">"to_account": "acc_789012",</div>
                            <div class="ml-8">"amount": 1000,</div>
                            <div class="ml-8">"currency": "GCU",</div>
                            <div class="ml-8">"reference": "Invoice #2024-001"</div>
                            <div class="ml-4">}'</div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA Section -->
        <section class="py-20 gradient-bg text-white">
            <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
                <h2 class="text-4xl font-bold mb-6">Ready to Experience the Future of Banking?</h2>
                <p class="text-xl mb-8 text-purple-100">
                    Join thousands of users already benefiting from democratic finance
                </p>
                <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg font-semibold text-lg hover:bg-gray-100 transition inline-block">
                    Create Your Free Account
                </a>
            </div>
        </section>

        <!-- Footer -->
        <footer class="bg-gray-900 text-gray-400 py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="grid md:grid-cols-4 gap-8">
                    <div>
                        <h4 class="text-white font-semibold mb-4">Product</h4>
                        <ul class="space-y-2">
                            <li><a href="/features" class="hover:text-white transition">Features</a></li>
                            <li><a href="/pricing" class="hover:text-white transition">Pricing</a></li>
                            <li><a href="/security" class="hover:text-white transition">Security</a></li>
                            <li><a href="/compliance" class="hover:text-white transition">Compliance</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-white font-semibold mb-4">Developers</h4>
                        <ul class="space-y-2">
                            <li><a href="/developers" class="hover:text-white transition">Documentation</a></li>
                            <li><a href="/developers/api" class="hover:text-white transition">API Reference</a></li>
                            <li><a href="/developers/sdks" class="hover:text-white transition">SDKs</a></li>
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
                    <p>&copy; {{ date('Y') }} FinAegis. All rights reserved. Built with Laravel v{{ Illuminate\Foundation\Application::VERSION }}</p>
                </div>
            </div>
        </footer>
    </body>
</html>