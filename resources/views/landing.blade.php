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
    
    <title>FinAegis - The Future of Democratic Banking</title>

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
    <!-- Navigation -->
    <nav class="bg-white shadow-lg fixed w-full z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <!-- Logo -->
                    <a href="/" class="flex items-center">
                        <span class="text-2xl font-bold text-indigo-600">Fin</span>
                        <span class="text-2xl font-bold text-purple-600">Aegis</span>
                    </a>
                    
                    <!-- Main Navigation -->
                    <div class="hidden md:ml-10 md:flex md:space-x-8">
                        <a href="#features" class="text-gray-700 hover:text-indigo-600 px-3 py-2 text-sm font-medium">Features</a>
                        <a href="/about" class="text-gray-700 hover:text-indigo-600 px-3 py-2 text-sm font-medium">About</a>
                        <a href="/pricing" class="text-gray-700 hover:text-indigo-600 px-3 py-2 text-sm font-medium">Pricing</a>
                        <a href="/developers" class="text-gray-700 hover:text-indigo-600 px-3 py-2 text-sm font-medium">Developers</a>
                        <a href="/support" class="text-gray-700 hover:text-indigo-600 px-3 py-2 text-sm font-medium">Support</a>
                    </div>
                </div>
                
                <!-- Right Navigation -->
                <div class="flex items-center space-x-4">
                    @auth
                        <a href="{{ route('dashboard') }}" class="text-gray-700 hover:text-indigo-600 px-3 py-2 text-sm font-medium">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="text-gray-700 hover:text-indigo-600 px-3 py-2 text-sm font-medium">Login</a>
                        <a href="{{ route('register') }}" class="bg-indigo-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-indigo-700 transition">Get Started</a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="pt-16 gradient-bg text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
            <div class="text-center">
                <h1 class="text-5xl md:text-6xl font-bold mb-6">
                    The Future of Democratic Banking
                </h1>
                <p class="text-xl md:text-2xl mb-8 text-purple-100 max-w-3xl mx-auto">
                    Experience the Global Currency Unit (GCU) - a revolutionary basket currency backed by real banks and governed by the community.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition">
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
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8 text-center">
                <div>
                    <div class="stat-number">Ǥ</div>
                    <p class="text-gray-600 font-semibold">Global Currency Unit</p>
                </div>
                <div>
                    <div class="stat-number">6+</div>
                    <p class="text-gray-600 font-semibold">Supported Assets</p>
                </div>
                <div>
                    <div class="stat-number">3</div>
                    <p class="text-gray-600 font-semibold">Partner Banks</p>
                </div>
                <div>
                    <div class="stat-number">24/7</div>
                    <p class="text-gray-600 font-semibold">Real-time Processing</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section id="features" class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Powerful Features for Modern Banking</h2>
                <p class="text-xl text-gray-600">Everything you need for secure, efficient, and democratic financial management</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Feature 1 -->
                <div class="feature-card bg-white rounded-xl p-8 shadow-md">
                    <div class="w-16 h-16 bg-indigo-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Global Currency Unit (GCU)</h3>
                    <p class="text-gray-600">Democratic basket currency backed by major fiat currencies and governed by community voting.</p>
                </div>
                
                <!-- Feature 2 -->
                <div class="feature-card bg-white rounded-xl p-8 shadow-md">
                    <div class="w-16 h-16 bg-purple-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Bank-Grade Security</h3>
                    <p class="text-gray-600">Quantum-resistant encryption, multi-factor authentication, and real-time fraud detection.</p>
                </div>
                
                <!-- Feature 3 -->
                <div class="feature-card bg-white rounded-xl p-8 shadow-md">
                    <div class="w-16 h-16 bg-green-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Instant Settlements</h3>
                    <p class="text-gray-600">Real-time multi-bank transfers with sub-second processing and automatic reconciliation.</p>
                </div>
                
                <!-- Feature 4 -->
                <div class="feature-card bg-white rounded-xl p-8 shadow-md">
                    <div class="w-16 h-16 bg-yellow-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Democratic Governance</h3>
                    <p class="text-gray-600">Community-driven decision making through monthly voting on basket composition and platform features.</p>
                </div>
                
                <!-- Feature 5 -->
                <div class="feature-card bg-white rounded-xl p-8 shadow-md">
                    <div class="w-16 h-16 bg-red-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Developer-Friendly APIs</h3>
                    <p class="text-gray-600">RESTful APIs, webhooks, and SDKs for seamless integration with your applications.</p>
                </div>
                
                <!-- Feature 6 -->
                <div class="feature-card bg-white rounded-xl p-8 shadow-md">
                    <div class="w-16 h-16 bg-blue-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Compliance Built-In</h3>
                    <p class="text-gray-600">KYC/AML, GDPR compliant, with automated regulatory reporting and audit trails.</p>
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

    <!-- CTA Section -->
    <section class="py-20 gradient-bg text-white">
        <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
            <h2 class="text-4xl font-bold mb-6">Ready to Experience Democratic Banking?</h2>
            <p class="text-xl mb-8 text-purple-100">Join thousands of users who are already part of the financial revolution.</p>
            <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition inline-block">
                Open Your Free Account
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <!-- Company -->
                <div>
                    <h4 class="text-lg font-semibold mb-4">Company</h4>
                    <ul class="space-y-2">
                        <li><a href="/about" class="text-gray-400 hover:text-white">About Us</a></li>
                        <li><a href="/careers" class="text-gray-400 hover:text-white">Careers</a></li>
                        <li><a href="/press" class="text-gray-400 hover:text-white">Press</a></li>
                        <li><a href="/blog" class="text-gray-400 hover:text-white">Blog</a></li>
                    </ul>
                </div>
                
                <!-- Product -->
                <div>
                    <h4 class="text-lg font-semibold mb-4">Product</h4>
                    <ul class="space-y-2">
                        <li><a href="/features" class="text-gray-400 hover:text-white">Features</a></li>
                        <li><a href="/pricing" class="text-gray-400 hover:text-white">Pricing</a></li>
                        <li><a href="/security" class="text-gray-400 hover:text-white">Security</a></li>
                        <li><a href="/compliance" class="text-gray-400 hover:text-white">Compliance</a></li>
                    </ul>
                </div>
                
                <!-- Developers -->
                <div>
                    <h4 class="text-lg font-semibold mb-4">Developers</h4>
                    <ul class="space-y-2">
                        <li><a href="/developers/docs" class="text-gray-400 hover:text-white">Documentation</a></li>
                        <li><a href="/developers/api" class="text-gray-400 hover:text-white">API Reference</a></li>
                        <li><a href="/developers/sdks" class="text-gray-400 hover:text-white">SDKs</a></li>
                        <li><a href="https://github.com/FinAegis" class="text-gray-400 hover:text-white">GitHub</a></li>
                    </ul>
                </div>
                
                <!-- Support -->
                <div>
                    <h4 class="text-lg font-semibold mb-4">Support</h4>
                    <ul class="space-y-2">
                        <li><a href="/support/contact" class="text-gray-400 hover:text-white">Contact Us</a></li>
                        <li><a href="/support/faq" class="text-gray-400 hover:text-white">FAQ</a></li>
                        <li><a href="/support/guides" class="text-gray-400 hover:text-white">User Guides</a></li>
                        <li><a href="/status" class="text-gray-400 hover:text-white">System Status</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="border-t border-gray-800 mt-8 pt-8">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <div class="text-gray-400 text-sm mb-4 md:mb-0">
                        © 2025 FinAegis. All rights reserved.
                    </div>
                    <div class="flex space-x-6">
                        <a href="/legal/terms" class="text-gray-400 hover:text-white text-sm">Terms</a>
                        <a href="/legal/privacy" class="text-gray-400 hover:text-white text-sm">Privacy</a>
                        <a href="/legal/cookies" class="text-gray-400 hover:text-white text-sm">Cookies</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>