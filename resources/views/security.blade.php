<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="FinAegis security measures - Bank-grade protection with quantum-resistant encryption, multi-factor authentication, and comprehensive security practices.">
    
    <title>Security - Bank-Grade Protection | FinAegis</title>

    @include('partials.favicon')

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .security-feature {
            transition: transform 0.3s ease;
        }
        .security-feature:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body class="antialiased">
    <x-platform-banners />
    <!-- Navigation -->
    @include('partials.public-nav')

    <!-- Hero Section -->
    <section class="pt-16 gradient-bg text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-white/20 rounded-full mb-6">
                    <svg class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/>
                    </svg>
                </div>
                <h1 class="text-5xl font-bold mb-6">Bank-Grade Security</h1>
                <p class="text-xl text-purple-100 max-w-3xl mx-auto">
                    Your security is our top priority. We employ cutting-edge technology and industry best practices to protect your assets and data.
                </p>
            </div>
        </div>
    </section>

    <!-- Security Features Grid -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Multi-Layer Security Architecture</h2>
                <p class="text-xl text-gray-600">Comprehensive protection at every level</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Quantum-Resistant Encryption -->
                <div class="security-feature bg-gray-50 rounded-xl p-8">
                    <div class="w-16 h-16 bg-indigo-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Quantum-Resistant Encryption</h3>
                    <p class="text-gray-600">
                        SHA3-512 hashing algorithm protects against current and future cryptographic threats, ensuring your data remains secure even against quantum computers.
                    </p>
                </div>
                
                <!-- Multi-Factor Authentication -->
                <div class="security-feature bg-gray-50 rounded-xl p-8">
                    <div class="w-16 h-16 bg-purple-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Multi-Factor Authentication</h3>
                    <p class="text-gray-600">
                        Protect your account with multiple verification methods including SMS, authenticator apps, biometric verification, and hardware security keys.
                    </p>
                </div>
                
                <!-- Cold Storage -->
                <div class="security-feature bg-gray-50 rounded-xl p-8">
                    <div class="w-16 h-16 bg-green-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Cold Storage</h3>
                    <p class="text-gray-600">
                        95% of customer funds are stored in offline, air-gapped cold storage systems, protected from online threats and unauthorized access.
                    </p>
                </div>
                
                <!-- Real-time Monitoring -->
                <div class="security-feature bg-gray-50 rounded-xl p-8">
                    <div class="w-16 h-16 bg-yellow-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">24/7 Monitoring</h3>
                    <p class="text-gray-600">
                        Our security operations center monitors all transactions in real-time, using AI-powered fraud detection to identify and prevent suspicious activities.
                    </p>
                </div>
                
                <!-- Secure Infrastructure -->
                <div class="security-feature bg-gray-50 rounded-xl p-8">
                    <div class="w-16 h-16 bg-red-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Secure Infrastructure</h3>
                    <p class="text-gray-600">
                        Hosted on AWS with multi-region redundancy, DDoS protection, and automatic failover to ensure 99.99% uptime and data availability.
                    </p>
                </div>
                
                <!-- Compliance -->
                <div class="security-feature bg-gray-50 rounded-xl p-8">
                    <div class="w-16 h-16 bg-blue-100 rounded-lg flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Regulatory Compliance</h3>
                    <p class="text-gray-600">
                        Fully compliant with KYC/AML regulations, GDPR, PCI DSS, and SOC 2 Type II certified. Regular audits ensure continuous compliance.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Security Practices -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
                <div>
                    <h2 class="text-4xl font-bold text-gray-900 mb-6">Industry-Leading Security Practices</h2>
                    <div class="space-y-4">
                        <div class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold text-gray-900 mb-1">Regular Security Audits</h4>
                                <p class="text-gray-600">Third-party penetration testing and security assessments quarterly</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold text-gray-900 mb-1">Bug Bounty Program</h4>
                                <p class="text-gray-600">Rewards up to $50,000 for responsible security disclosures</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold text-gray-900 mb-1">Insurance Coverage</h4>
                                <p class="text-gray-600">$250 million in insurance coverage for customer assets</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start">
                            <svg class="w-6 h-6 text-green-500 mr-3 mt-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold text-gray-900 mb-1">Team Security Training</h4>
                                <p class="text-gray-600">Regular security awareness training for all employees</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-2xl shadow-xl p-8">
                    <h3 class="text-2xl font-bold text-gray-900 mb-6">Security Certifications</h3>
                    <div class="grid grid-cols-2 gap-6">
                        <div class="text-center">
                            <div class="w-20 h-20 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <span class="text-indigo-600 font-bold text-lg">SOC 2</span>
                            </div>
                            <p class="text-sm text-gray-600">Type II Certified</p>
                        </div>
                        
                        <div class="text-center">
                            <div class="w-20 h-20 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <span class="text-purple-600 font-bold text-lg">PCI</span>
                            </div>
                            <p class="text-sm text-gray-600">DSS Compliant</p>
                        </div>
                        
                        <div class="text-center">
                            <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <span class="text-green-600 font-bold text-lg">ISO</span>
                            </div>
                            <p class="text-sm text-gray-600">27001 Certified</p>
                        </div>
                        
                        <div class="text-center">
                            <div class="w-20 h-20 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-3">
                                <span class="text-yellow-600 font-bold text-lg">GDPR</span>
                            </div>
                            <p class="text-sm text-gray-600">Compliant</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- User Security Tips -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Protect Your Account</h2>
                <p class="text-xl text-gray-600">Best practices to keep your account secure</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 max-w-4xl mx-auto">
                <div class="bg-indigo-50 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-indigo-900 mb-4">Do's</h3>
                    <ul class="space-y-3">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span class="text-gray-700">Enable two-factor authentication (2FA)</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span class="text-gray-700">Use a unique, strong password</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span class="text-gray-700">Verify email sender addresses</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span class="text-gray-700">Keep your devices updated</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span class="text-gray-700">Review account activity regularly</span>
                        </li>
                    </ul>
                </div>
                
                <div class="bg-red-50 rounded-lg p-6">
                    <h3 class="text-lg font-semibold text-red-900 mb-4">Don'ts</h3>
                    <ul class="space-y-3">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-red-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            <span class="text-gray-700">Share your password or API keys</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-red-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            <span class="text-gray-700">Click on suspicious links</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-red-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            <span class="text-gray-700">Use public WiFi for banking</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-red-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            <span class="text-gray-700">Install unverified browser extensions</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-red-600 mr-2 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            <span class="text-gray-700">Ignore security warnings</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Security Contact -->
    <section class="py-20 gradient-bg text-white">
        <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
            <h2 class="text-4xl font-bold mb-6">Security Concerns?</h2>
            <p class="text-xl mb-8 text-purple-100">
                If you've discovered a security issue or have concerns about your account security, we're here to help.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="mailto:security@finaegis.org" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition">
                    Report Security Issue
                </a>
                <a href="{{ route('support.contact') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">
                    Contact Support
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    @include('partials.footer')
</body>
</html>