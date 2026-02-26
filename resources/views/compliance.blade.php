@extends('layouts.public')

@section('title', 'Compliance - FinAegis')

@section('seo')
    @include('partials.seo', [
        'title' => 'Compliance - FinAegis',
        'description' => 'FinAegis compliance-ready architecture. Built to meet EU regulatory standards and integrate with licensed financial institutions.',
        'keywords' => 'FinAegis, compliance, regulation, PSD2, EMD2, MiCA, KYC, AML, GDPR, security',
    ])
@endsection

@section('content')
    <div class="bg-white">
        <!-- Header -->
        <div class="bg-gradient-to-r from-blue-600 to-purple-600">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
                <div class="text-center">
                    <h1 class="text-4xl font-bold text-white sm:text-5xl lg:text-6xl">
                        Compliance-Ready Architecture
                    </h1>
                    <p class="mt-6 text-xl text-blue-100 max-w-3xl mx-auto">
                        Built for EU regulatory compliance from day one. PSD2, EMD2, MiCA, KYC/AML, GDPR, and MiFID II adapters with jurisdiction-aware routing.
                    </p>
                </div>
            </div>
        </div>

        <!-- Regulatory Strategy -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-bold text-gray-900">Designed for EU Regulatory Compliance</h2>
                <p class="mt-4 text-lg text-gray-600 max-w-3xl mx-auto">
                    Our platform architecture is built to support EMI licensing requirements and can integrate with licensed partners for compliant operations across the European Union.
                </p>
            </div>

            <!-- Primary Regulations -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8 mb-16">
                
                <!-- PSD2 Compliance -->
                <div class="bg-white border border-gray-200 rounded-lg p-6 hover:shadow-lg transition duration-200">
                    <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">PSD2 Compatible</h3>
                    <p class="text-gray-600 mb-4">Architecture designed to support Payment Services Directive 2 requirements when operating with licensed partners.</p>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Strong Customer Authentication (SCA)</li>
                        <li>• Open Banking API standards</li>
                        <li>• Account Information Services (AIS)</li>
                        <li>• Payment Initiation Services (PIS)</li>
                    </ul>
                </div>

                <!-- EMD2 Framework -->
                <div class="bg-white border border-gray-200 rounded-lg p-6 hover:shadow-lg transition duration-200">
                    <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">EMD2 Ready</h3>
                    <p class="text-gray-600 mb-4">Platform architecture prepared for Electronic Money Directive 2 compliance requirements.</p>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Support for fund safeguarding</li>
                        <li>• Customer fund segregation</li>
                        <li>• E-money redemption workflows</li>
                        <li>• Operational resilience features</li>
                    </ul>
                </div>

                <!-- MiCA Ready -->
                <div class="bg-white border border-gray-200 rounded-lg p-6 hover:shadow-lg transition duration-200">
                    <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                        <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-bold text-gray-900 mb-3">MiCA Compatible Design</h3>
                    <p class="text-gray-600 mb-4">Infrastructure designed with Markets in Crypto-Assets regulation in mind for future digital asset operations.</p>
                    <ul class="text-sm text-gray-600 space-y-1">
                        <li>• Asset-referenced token framework</li>
                        <li>• E-money token compliance</li>
                        <li>• Reserve asset management</li>
                        <li>• Stability mechanism requirements</li>
                    </ul>
                </div>
            </div>

            <!-- KYC Implementation -->
            <div class="bg-gray-50 rounded-lg p-8 mb-16">
                <h3 class="text-2xl font-bold text-gray-900 mb-6">KYC/AML System Architecture</h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    
                    <!-- Basic KYC -->
                    <div class="bg-white rounded-lg p-6 border border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-lg font-semibold text-gray-900">Basic Verification</h4>
                            <span class="bg-blue-100 text-blue-700 text-sm px-3 py-1 rounded-full">Level 1</span>
                        </div>
                        <ul class="space-y-2 text-gray-600 mb-4">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                National ID verification
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                Selfie with liveness check
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                Automated risk scoring
                            </li>
                        </ul>
                        <div class="pt-4 border-t border-gray-200">
                            <div class="text-sm text-gray-600">Daily Limit</div>
                            <div class="text-2xl font-bold text-gray-900">€10,000</div>
                        </div>
                    </div>

                    <!-- Enhanced KYC -->
                    <div class="bg-white rounded-lg p-6 border border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-lg font-semibold text-gray-900">Enhanced Verification</h4>
                            <span class="bg-green-100 text-green-700 text-sm px-3 py-1 rounded-full">Level 2</span>
                        </div>
                        <ul class="space-y-2 text-gray-600 mb-4">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                Passport verification
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                Proof of address
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                PEP & sanctions screening
                            </li>
                        </ul>
                        <div class="pt-4 border-t border-gray-200">
                            <div class="text-sm text-gray-600">Daily Limit</div>
                            <div class="text-2xl font-bold text-gray-900">€50,000</div>
                        </div>
                    </div>

                    <!-- Full KYC -->
                    <div class="bg-white rounded-lg p-6 border border-gray-200">
                        <div class="flex items-center justify-between mb-4">
                            <h4 class="text-lg font-semibold text-gray-900">Full Verification</h4>
                            <span class="bg-purple-100 text-purple-700 text-sm px-3 py-1 rounded-full">Level 3</span>
                        </div>
                        <ul class="space-y-2 text-gray-600 mb-4">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                All previous checks
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                Source of funds verification
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                Enhanced due diligence
                            </li>
                        </ul>
                        <div class="pt-4 border-t border-gray-200">
                            <div class="text-sm text-gray-600">Daily Limit</div>
                            <div class="text-2xl font-bold text-gray-900">Unlimited</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- AML Framework -->
            <div class="bg-white border border-gray-200 rounded-lg p-8 mb-16">
                <h3 class="text-2xl font-bold text-gray-900 mb-6">Anti-Money Laundering (AML) Framework</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    
                    <!-- Transaction Monitoring -->
                    <div>
                        <h4 class="text-lg font-semibold text-gray-900 mb-4">Real-Time Transaction Monitoring</h4>
                        <ul class="space-y-3 text-gray-600">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-blue-500 mt-0.5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                <div>
                                    <div class="font-medium">Pattern Detection</div>
                                    <div class="text-sm">Structuring, velocity, and unusual patterns</div>
                                </div>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-blue-500 mt-0.5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                <div>
                                    <div class="font-medium">Threshold Monitoring</div>
                                    <div class="text-sm">€10,000 CTR threshold tracking</div>
                                </div>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-blue-500 mt-0.5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                <div>
                                    <div class="font-medium">Risk Scoring</div>
                                    <div class="text-sm">ML-based transaction risk assessment</div>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <!-- Regulatory Reporting -->
                    <div>
                        <h4 class="text-lg font-semibold text-gray-900 mb-4">Automated Regulatory Reporting</h4>
                        <ul class="space-y-3 text-gray-600">
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                <div>
                                    <div class="font-medium">Currency Transaction Reports (CTR)</div>
                                    <div class="text-sm">Daily automated generation</div>
                                </div>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                <div>
                                    <div class="font-medium">Suspicious Activity Reports (SAR)</div>
                                    <div class="text-sm">Monthly candidate identification</div>
                                </div>
                            </li>
                            <li class="flex items-start">
                                <svg class="w-5 h-5 text-green-500 mt-0.5 mr-3" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                                </svg>
                                <div>
                                    <div class="font-medium">Compliance Dashboards</div>
                                    <div class="text-sm">Real-time metrics and alerts</div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- GDPR Compliance -->
            <div class="bg-blue-50 rounded-lg p-8 mb-16">
                <h3 class="text-2xl font-bold text-gray-900 mb-6">GDPR & Data Protection</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                    <div class="text-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <h4 class="font-semibold text-gray-900">Data Export</h4>
                        <p class="text-sm text-gray-600 mt-1">Full data portability support</p>
                    </div>
                    <div class="text-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                        </div>
                        <h4 class="font-semibold text-gray-900">Right to Deletion</h4>
                        <p class="text-sm text-gray-600 mt-1">Secure data anonymization</p>
                    </div>
                    <div class="text-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                        <h4 class="font-semibold text-gray-900">Retention Policies</h4>
                        <p class="text-sm text-gray-600 mt-1">Automated data lifecycle</p>
                    </div>
                    <div class="text-center">
                        <div class="w-12 h-12 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                        </div>
                        <h4 class="font-semibold text-gray-900">Consent Management</h4>
                        <p class="text-sm text-gray-600 mt-1">Granular privacy controls</p>
                    </div>
                </div>
            </div>

            <!-- Banking Partners -->
            <div class="bg-gray-50 rounded-lg p-8 mb-16">
                <h3 class="text-2xl font-bold text-gray-900 mb-6">Example Banking Integrations</h3>
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 mb-6">
                    <p class="text-sm text-amber-800">
                        <strong>Disclaimer:</strong> The following are example integrations demonstrating our platform's technical capabilities. 
                        These are not active partnerships or endorsements. If your institution is listed and you would like it removed, 
                        please contact us at info@finaegis.org.
                    </p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    
                    <!-- Paysera -->
                    <div class="bg-white rounded-lg p-6 border border-gray-200">
                        <div class="bg-blue-100 text-blue-700 text-xs px-2 py-1 rounded mb-2 inline-block">Example</div>
                        <h4 class="font-semibold text-gray-900 mb-2">Paysera Connector</h4>
                        <p class="text-sm text-gray-600 mb-3">EMI Integration Example</p>
                        <ul class="text-xs text-gray-500 space-y-1">
                            <li>• API integration ready</li>
                            <li>• Multi-currency support</li>
                            <li>• SEPA payments</li>
                            <li>• PSD2 compatible APIs</li>
                        </ul>
                    </div>

                    <!-- Deutsche Bank -->
                    <div class="bg-white rounded-lg p-6 border border-gray-200">
                        <div class="bg-blue-100 text-blue-700 text-xs px-2 py-1 rounded mb-2 inline-block">Example</div>
                        <h4 class="font-semibold text-gray-900 mb-2">Deutsche Bank API</h4>
                        <p class="text-sm text-gray-600 mb-3">Banking Integration Demo</p>
                        <ul class="text-xs text-gray-500 space-y-1">
                            <li>• Corporate API access</li>
                            <li>• Multi-currency accounts</li>
                            <li>• SWIFT connectivity</li>
                            <li>• SEPA Instant support</li>
                        </ul>
                    </div>

                    <!-- Santander -->
                    <div class="bg-white rounded-lg p-6 border border-gray-200">
                        <div class="bg-blue-100 text-blue-700 text-xs px-2 py-1 rounded mb-2 inline-block">Example</div>
                        <h4 class="font-semibold text-gray-900 mb-2">Santander Module</h4>
                        <p class="text-sm text-gray-600 mb-3">Open Banking Sample</p>
                        <ul class="text-xs text-gray-500 space-y-1">
                            <li>• Open Banking APIs</li>
                            <li>• Account aggregation</li>
                            <li>• Payment initiation</li>
                            <li>• Real-time balances</li>
                        </ul>
                    </div>

                    <!-- Wise -->
                    <div class="bg-white rounded-lg p-6 border border-gray-200">
                        <div class="bg-green-100 text-green-700 text-xs px-2 py-1 rounded mb-2 inline-block">Available</div>
                        <h4 class="font-semibold text-gray-900 mb-2">Custom Integrations</h4>
                        <p class="text-sm text-gray-600 mb-3">Your Bank Here</p>
                        <ul class="text-xs text-gray-500 space-y-1">
                            <li>• Modular architecture</li>
                            <li>• Standard interfaces</li>
                            <li>• Webhook support</li>
                            <li>• Easy integration</li>
                        </ul>
                    </div>
                </div>
                <div class="mt-6 text-center">
                    <p class="text-sm text-gray-600">
                        These reference implementations demonstrate our platform's integration patterns and capabilities.
                        <br>No partnerships or endorsements are implied.
                    </p>
                </div>
            </div>

            <!-- Security Standards -->
            <div class="bg-white border border-gray-200 rounded-lg p-8 mb-16">
                <h3 class="text-2xl font-bold text-gray-900 mb-6">Security Architecture & Best Practices</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                    <div class="text-center">
                        <div class="text-lg font-semibold text-gray-900">OWASP</div>
                        <div class="text-sm text-gray-600">Security Guidelines</div>
                    </div>
                    <div class="text-center">
                        <div class="text-lg font-semibold text-gray-900">AES-256</div>
                        <div class="text-sm text-gray-600">Data Encryption</div>
                    </div>
                    <div class="text-center">
                        <div class="text-lg font-semibold text-gray-900">OAuth 2.0</div>
                        <div class="text-sm text-gray-600">API Security</div>
                    </div>
                    <div class="text-center">
                        <div class="text-lg font-semibold text-gray-900">bcrypt</div>
                        <div class="text-sm text-gray-600">Password Hashing</div>
                    </div>
                </div>
            </div>

            <!-- Compliance Metrics -->
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 rounded-lg p-8 text-white mb-16">
                <h3 class="text-2xl font-bold mb-6">Platform Compliance Features</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                    <div class="text-center">
                        <div class="text-3xl font-bold mb-2">Real-time</div>
                        <div class="text-blue-100">Transaction Logging</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold mb-2">Automated</div>
                        <div class="text-blue-100">Risk Scoring</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold mb-2">Built-in</div>
                        <div class="text-blue-100">Audit Trail</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold mb-2">Flexible</div>
                        <div class="text-blue-100">KYC Workflows</div>
                    </div>
                </div>
            </div>

            <!-- API Endpoints Info -->
            <div class="bg-white border border-gray-200 rounded-lg p-8 mb-16">
                <h3 class="text-2xl font-bold text-gray-900 mb-6">API Endpoints for Compliance Features</h3>
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                    <p class="text-sm text-blue-800">
                        <strong>Note:</strong> For api.finaegis.org subdomain, these endpoints are available without the /api prefix. 
                        For example: https://api.finaegis.org/compliance/kyc/status instead of https://api.finaegis.org/api/compliance/kyc/status
                    </p>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <h4 class="text-lg font-semibold text-gray-900 mb-4">KYC Endpoints</h4>
                        <ul class="space-y-2 text-sm text-gray-600 font-mono">
                            <li>GET /api/compliance/kyc/status</li>
                            <li>GET /api/compliance/kyc/requirements</li>
                            <li>POST /api/compliance/kyc/submit</li>
                            <li>POST /api/compliance/kyc/documents</li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-lg font-semibold text-gray-900 mb-4">GDPR Endpoints</h4>
                        <ul class="space-y-2 text-sm text-gray-600 font-mono">
                            <li>GET /api/compliance/gdpr/consent</li>
                            <li>POST /api/compliance/gdpr/consent</li>
                            <li>POST /api/compliance/gdpr/export</li>
                            <li>POST /api/compliance/gdpr/delete</li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-lg font-semibold text-gray-900 mb-4">Regulatory Reporting</h4>
                        <ul class="space-y-2 text-sm text-gray-600 font-mono">
                            <li>POST /api/regulatory/reports/ctr</li>
                            <li>POST /api/regulatory/reports/sar-candidates</li>
                            <li>GET /api/regulatory/reports</li>
                            <li>GET /api/regulatory/metrics</li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-lg font-semibold text-gray-900 mb-4">Bank Health & Alerting</h4>
                        <ul class="space-y-2 text-sm text-gray-600 font-mono">
                            <li>POST /api/bank-health/check</li>
                            <li>GET /api/bank-health/status</li>
                            <li>GET /api/bank-health/alerts/stats</li>
                            <li>PUT /api/bank-health/alerts/config</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Contact Compliance -->
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-8 text-center">
                <h3 class="text-2xl font-bold text-gray-900 mb-4">Learn More About Our Compliance Architecture</h3>
                <p class="text-gray-600 mb-6">
                    Interested in how our platform can support your compliance requirements? Contact us to learn more about integration possibilities.
                </p>
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <a href="mailto:info@finaegis.org" class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition duration-200">
                        Contact Us
                    </a>
                    <a href="{{ route('developers.show', 'api-docs') }}" class="border border-blue-600 text-blue-600 px-6 py-3 rounded-lg hover:bg-blue-50 transition duration-200">
                        View API Documentation
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection