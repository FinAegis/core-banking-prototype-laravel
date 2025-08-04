@extends('layouts.public')

@section('title', 'Security - Bank-Grade Protection | FinAegis')

@section('seo')
    @include('partials.seo', [
        'title' => 'Security - Bank-Grade Protection | FinAegis',
        'description' => 'FinAegis security overview - Bank-grade security meets blockchain immutability. Learn about our security measures and best practices.',
        'keywords' => 'FinAegis security, bank-grade security, blockchain security, secure banking, cybersecurity, data protection',
    ])
    
    {{-- Schema.org Markup --}}
    <x-schema type="service" :data="[
        'name' => 'FinAegis Security',
        'description' => 'Bank-grade security for the FinAegis platform',
        'category' => 'Financial Security'
    ]" />
    <x-schema type="breadcrumb" :data="[
        ['name' => 'Home', 'url' => url('/')],
        ['name' => 'Security', 'url' => url('/security')]
    ]" />
@endsection

@push('styles')
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
@endpush

@section('content')
    <!-- Hero Section -->
    <section class="pt-16 gradient-bg text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20">
            <div class="text-center">
                <div class="inline-flex items-center justify-center w-20 h-20 bg-white/20 rounded-full mb-6">
                    <svg class="w-10 h-10" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 1L3 5v6c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V5l-9-4z"/>
                    </svg>
                </div>
                <h1 class="text-5xl font-bold mb-6">Security Overview</h1>
                <p class="text-xl text-purple-100 max-w-3xl mx-auto">
                    Bank-grade security meets blockchain immutability. Your assets are protected by the most advanced security measures in the industry.
                </p>
            </div>
        </div>
    </section>

    <!-- Development Notice -->
    <section class="py-8 bg-amber-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-amber-400">
                <div class="flex items-start">
                    <div class="flex-shrink-0">
                        <svg class="w-6 h-6 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-lg font-semibold text-gray-900">Project Under Active Development</h3>
                        <p class="mt-2 text-gray-600">
                            This project is currently under active development. The security criteria listed below are 
                            <strong>guidelines and goals</strong> that we are working towards. Many of these features 
                            may not be implemented in the current framework yet. This page represents our security roadmap 
                            and the standards we aim to achieve as the platform matures.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Current State Section -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Enterprise Security Standards</h2>
                <p class="text-xl text-gray-600">Comprehensive security at every level</p>
            </div>
            
            <div class="max-w-4xl mx-auto">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <!-- Infrastructure Security -->
                    <div class="bg-green-50 rounded-xl p-8">
                        <h3 class="text-xl font-semibold text-green-900 mb-4 flex items-center">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            Infrastructure Security
                        </h3>
                        <ul class="space-y-3 text-gray-700">
                            <li class="flex items-start">
                                <span class="text-green-600 mr-2">•</span>
                                End-to-end encryption (TLS 1.3)
                            </li>
                            <li class="flex items-start">
                                <span class="text-green-600 mr-2">•</span>
                                DDoS protection & rate limiting
                            </li>
                            <li class="flex items-start">
                                <span class="text-green-600 mr-2">•</span>
                                Multi-region data redundancy
                            </li>
                            <li class="flex items-start">
                                <span class="text-green-600 mr-2">•</span>
                                24/7 security monitoring
                            </li>
                            <li class="flex items-start">
                                <span class="text-green-600 mr-2">•</span>
                                Regular penetration testing
                            </li>
                            <li class="flex items-start">
                                <span class="text-green-600 mr-2">•</span>
                                ISO 27001 compliance ready
                            </li>
                        </ul>
                    </div>
                    
                    <!-- Application Security -->
                    <div class="bg-blue-50 rounded-xl p-8">
                        <h3 class="text-xl font-semibold text-blue-900 mb-4 flex items-center">
                            <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                            </svg>
                            Application Security
                        </h3>
                        <ul class="space-y-3 text-gray-700">
                            <li class="flex items-start">
                                <span class="text-blue-600 mr-2">•</span>
                                Multi-factor authentication (2FA)
                            </li>
                            <li class="flex items-start">
                                <span class="text-blue-600 mr-2">•</span>
                                Advanced password policies
                            </li>
                            <li class="flex items-start">
                                <span class="text-blue-600 mr-2">•</span>
                                Session security & timeout
                            </li>
                            <li class="flex items-start">
                                <span class="text-blue-600 mr-2">•</span>
                                CSRF & XSS protection
                            </li>
                            <li class="flex items-start">
                                <span class="text-blue-600 mr-2">•</span>
                                SQL injection prevention
                            </li>
                            <li class="flex items-start">
                                <span class="text-blue-600 mr-2">•</span>
                                API authentication & rate limiting
                            </li>
                        </ul>
                    </div>
                </div>
                
                <!-- Compliance & Standards -->
                <div class="mt-8 bg-purple-50 rounded-xl p-8">
                    <h3 class="text-xl font-semibold text-purple-900 mb-4 flex items-center">
                        <svg class="w-6 h-6 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                        Compliance & Standards
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div class="bg-white rounded-lg p-4">
                            <h4 class="font-semibold text-gray-900 mb-2">Data Protection</h4>
                            <p class="text-sm text-gray-600">GDPR compliant data handling and user privacy protection</p>
                        </div>
                        <div class="bg-white rounded-lg p-4">
                            <h4 class="font-semibold text-gray-900 mb-2">Financial Compliance</h4>
                            <p class="text-sm text-gray-600">KYC/AML procedures and regulatory reporting</p>
                        </div>
                        <div class="bg-white rounded-lg p-4">
                            <h4 class="font-semibold text-gray-900 mb-2">Industry Standards</h4>
                            <p class="text-sm text-gray-600">PCI DSS compliance for payment processing</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Security Features -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Advanced Security Features</h2>
                <p class="text-xl text-gray-600">Protecting your assets with cutting-edge technology</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="security-feature bg-white rounded-xl shadow-lg p-8">
                    <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 11c0 3.517-1.009 6.799-2.753 9.571m-3.44-2.04l.054-.09A13.916 13.916 0 008 11a4 4 0 118 0c0 1.017-.07 2.019-.203 3m-2.118 6.844A21.88 21.88 0 0015.171 17m3.839 1.132c.645-2.266.99-4.659.99-7.132A8 8 0 008 4.07M3 15.364c.64-1.319 1-2.8 1-4.364 0-1.457.39-2.823 1.07-4"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Biometric Authentication</h3>
                    <p class="text-gray-600">
                        Advanced biometric verification including fingerprint and facial recognition for secure access.
                    </p>
                </div>
                
                <div class="security-feature bg-white rounded-xl shadow-lg p-8">
                    <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2zM9 9h6v6H9V9z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Hardware Security</h3>
                    <p class="text-gray-600">
                        Support for hardware security keys and HSM integration for cryptographic operations.
                    </p>
                </div>
                
                <div class="security-feature bg-white rounded-xl shadow-lg p-8">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Real-time Monitoring</h3>
                    <p class="text-gray-600">
                        AI-powered fraud detection and real-time transaction monitoring for suspicious activities.
                    </p>
                </div>
                
                <div class="security-feature bg-white rounded-xl shadow-lg p-8">
                    <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Zero-Knowledge Proofs</h3>
                    <p class="text-gray-600">
                        Advanced cryptographic techniques ensuring privacy while maintaining transparency.
                    </p>
                </div>
                
                <div class="security-feature bg-white rounded-xl shadow-lg p-8">
                    <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.618 5.984A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016zM12 9v2m0 4h.01"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Incident Response</h3>
                    <p class="text-gray-600">
                        24/7 security operations center with rapid incident response and recovery procedures.
                    </p>
                </div>
                
                <div class="security-feature bg-white rounded-xl shadow-lg p-8">
                    <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mb-6">
                        <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7v8a2 2 0 002 2h6M8 7V5a2 2 0 012-2h4.586a1 1 0 01.707.293l4.414 4.414a1 1 0 01.293.707V15a2 2 0 01-2 2h-2M8 7H6a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2v-2"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-4">Audit Logging</h3>
                    <p class="text-gray-600">
                        Comprehensive audit trails with immutable logging for all transactions and access attempts.
                    </p>
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
            <h2 class="text-4xl font-bold mb-6">Security First Approach</h2>
            <p class="text-xl mb-8 text-purple-100">
                We take security seriously. Our team works around the clock to ensure your assets and data are protected.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('support') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition">
                    Contact Security Team
                </a>
                <a href="{{ route('compliance') }}" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">
                    View Compliance
                </a>
            </div>
        </div>
    </section>
@endsection