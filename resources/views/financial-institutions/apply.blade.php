<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Apply to become a FinAegis partner financial institution. Join us in building the future of democratic banking.">
        <meta name="keywords" content="FinAegis, partner bank, financial institution, banking partnership, GCU">
        
        <title>Partner Institution Application - FinAegis</title>

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
        </style>
    </head>
    <body class="antialiased">
        <x-alpha-banner />
        <x-main-navigation />

        <!-- Hero Section -->
        <section class="pt-16 gradient-bg text-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
                <div class="text-center">
                    <h1 class="text-4xl md:text-5xl font-bold mb-6">
                        Partner Institution Application
                    </h1>
                    <p class="text-xl text-purple-100 max-w-3xl mx-auto">
                        Join the FinAegis network and help us build the future of democratic banking
                    </p>
                </div>
            </div>
            
            <!-- Wave SVG -->
            <div class="relative">
                <svg class="absolute bottom-0 w-full h-24 -mb-1 text-white" preserveAspectRatio="none" viewBox="0 0 1440 74">
                    <path fill="currentColor" d="M0,32L48,37.3C96,43,192,53,288,58.7C384,64,480,64,576,58.7C672,53,768,43,864,42.7C960,43,1056,53,1152,58.7C1248,64,1344,64,1392,64L1440,64L1440,74L1392,74C1344,74,1248,74,1152,74C1056,74,960,74,864,74C768,74,672,74,576,74C480,74,384,74,288,74C192,74,96,74,48,74L0,74Z"></path>
                </svg>
            </div>
        </section>

        <!-- Requirements Section -->
        <section class="py-16 bg-white">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="mb-12">
                    <h2 class="text-3xl font-bold text-gray-900 mb-6">Partnership Requirements</h2>
                    <p class="text-lg text-gray-600 mb-8">
                        To ensure the security and stability of the Global Currency Unit, we have established comprehensive requirements for partner institutions.
                    </p>

                    <div class="space-y-8">
                        <!-- Technical Requirements -->
                        <div class="bg-gray-50 rounded-xl p-6">
                            <h3 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                                <svg class="w-6 h-6 text-indigo-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path>
                                </svg>
                                Technical Requirements
                            </h3>
                            <ul class="space-y-2 text-gray-700">
                                <li class="flex items-start">
                                    <span class="text-indigo-600 mr-2">•</span>
                                    Modern API infrastructure with REST/JSON support
                                </li>
                                <li class="flex items-start">
                                    <span class="text-indigo-600 mr-2">•</span>
                                    Real-time transaction processing capabilities
                                </li>
                                <li class="flex items-start">
                                    <span class="text-indigo-600 mr-2">•</span>
                                    OAuth 2.0 or similar secure authentication
                                </li>
                                <li class="flex items-start">
                                    <span class="text-indigo-600 mr-2">•</span>
                                    99.9% uptime SLA commitment
                                </li>
                                <li class="flex items-start">
                                    <span class="text-indigo-600 mr-2">•</span>
                                    ISO 27001 or equivalent security certification
                                </li>
                            </ul>
                        </div>

                        <!-- Juridical Requirements -->
                        <div class="bg-gray-50 rounded-xl p-6">
                            <h3 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                                <svg class="w-6 h-6 text-indigo-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3"></path>
                                </svg>
                                Juridical Requirements
                            </h3>
                            <ul class="space-y-2 text-gray-700">
                                <li class="flex items-start">
                                    <span class="text-indigo-600 mr-2">•</span>
                                    Valid banking license in operational jurisdiction
                                </li>
                                <li class="flex items-start">
                                    <span class="text-indigo-600 mr-2">•</span>
                                    Regulatory compliance with local and EU banking laws
                                </li>
                                <li class="flex items-start">
                                    <span class="text-indigo-600 mr-2">•</span>
                                    AML/KYC procedures meeting international standards
                                </li>
                                <li class="flex items-start">
                                    <span class="text-indigo-600 mr-2">•</span>
                                    Data protection compliance (GDPR or equivalent)
                                </li>
                                <li class="flex items-start">
                                    <span class="text-indigo-600 mr-2">•</span>
                                    Cross-border payment capabilities
                                </li>
                            </ul>
                        </div>

                        <!-- Financial Requirements -->
                        <div class="bg-gray-50 rounded-xl p-6">
                            <h3 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                                <svg class="w-6 h-6 text-indigo-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                </svg>
                                Financial Requirements
                            </h3>
                            <ul class="space-y-2 text-gray-700">
                                <li class="flex items-start">
                                    <span class="text-indigo-600 mr-2">•</span>
                                    Minimum €100M in assets under management
                                </li>
                                <li class="flex items-start">
                                    <span class="text-indigo-600 mr-2">•</span>
                                    Tier 1 capital ratio above regulatory minimums
                                </li>
                                <li class="flex items-start">
                                    <span class="text-indigo-600 mr-2">•</span>
                                    Demonstrated liquidity management capabilities
                                </li>
                                <li class="flex items-start">
                                    <span class="text-indigo-600 mr-2">•</span>
                                    Credit rating of BBB or higher (if applicable)
                                </li>
                                <li class="flex items-start">
                                    <span class="text-indigo-600 mr-2">•</span>
                                    Segregated client account capabilities
                                </li>
                            </ul>
                        </div>

                        <!-- Insurance Requirements -->
                        <div class="bg-gray-50 rounded-xl p-6">
                            <h3 class="text-xl font-semibold text-gray-900 mb-4 flex items-center">
                                <svg class="w-6 h-6 text-indigo-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                                </svg>
                                Insurance Requirements
                            </h3>
                            <ul class="space-y-2 text-gray-700">
                                <li class="flex items-start">
                                    <span class="text-indigo-600 mr-2">•</span>
                                    Deposit insurance scheme membership
                                </li>
                                <li class="flex items-start">
                                    <span class="text-indigo-600 mr-2">•</span>
                                    Professional indemnity insurance
                                </li>
                                <li class="flex items-start">
                                    <span class="text-indigo-600 mr-2">•</span>
                                    Cyber security insurance coverage
                                </li>
                                <li class="flex items-start">
                                    <span class="text-indigo-600 mr-2">•</span>
                                    Operational risk insurance
                                </li>
                                <li class="flex items-start">
                                    <span class="text-indigo-600 mr-2">•</span>
                                    Directors & Officers liability coverage
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Application Form -->
                <div class="mt-16">
                    <h2 class="text-3xl font-bold text-gray-900 mb-6">Application Form</h2>
                    <p class="text-lg text-gray-600 mb-8">
                        Please provide detailed information about your institution and how you meet our partnership requirements.
                    </p>

                    @if(session('success'))
                        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg mb-6">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if($errors->any())
                        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg mb-6">
                            <p class="font-semibold mb-2">Please correct the following errors:</p>
                            <ul class="list-disc list-inside">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('financial-institutions.submit') }}" method="POST" class="space-y-6">
                        @csrf
                        
                        <!-- Institution Information -->
                        <div class="bg-white border border-gray-200 rounded-xl p-6">
                            <h3 class="text-xl font-semibold text-gray-900 mb-4">Institution Information</h3>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label for="institution_name" class="block text-sm font-medium text-gray-700 mb-2">
                                        Institution Name *
                                    </label>
                                    <input type="text" id="institution_name" name="institution_name" required
                                           value="{{ old('institution_name') }}"
                                           class="w-full px-4 py-2 border {{ $errors->has('institution_name') ? 'border-red-500' : 'border-gray-300' }} rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                    @error('institution_name')
                                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                
                                <div>
                                    <label for="country" class="block text-sm font-medium text-gray-700 mb-2">
                                        Country of Operation *
                                    </label>
                                    <input type="text" id="country" name="country" required
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                </div>
                                
                                <div>
                                    <label for="contact_name" class="block text-sm font-medium text-gray-700 mb-2">
                                        Contact Person *
                                    </label>
                                    <input type="text" id="contact_name" name="contact_name" required
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                </div>
                                
                                <div>
                                    <label for="contact_email" class="block text-sm font-medium text-gray-700 mb-2">
                                        Contact Email *
                                    </label>
                                    <input type="email" id="contact_email" name="contact_email" required
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                </div>
                            </div>
                        </div>

                        <!-- Requirements Assessment -->
                        <div class="bg-white border border-gray-200 rounded-xl p-6">
                            <h3 class="text-xl font-semibold text-gray-900 mb-4">Requirements Assessment</h3>
                            <p class="text-sm text-gray-600 mb-4">
                                Please describe how your institution meets each of the partnership requirements:
                            </p>
                            
                            <div class="space-y-6">
                                <div>
                                    <label for="technical_capabilities" class="block text-sm font-medium text-gray-700 mb-2">
                                        Technical Capabilities *
                                    </label>
                                    <textarea id="technical_capabilities" name="technical_capabilities" rows="4" required
                                              placeholder="Describe your API infrastructure, security certifications, uptime guarantees, etc."
                                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"></textarea>
                                </div>
                                
                                <div>
                                    <label for="regulatory_compliance" class="block text-sm font-medium text-gray-700 mb-2">
                                        Regulatory Compliance *
                                    </label>
                                    <textarea id="regulatory_compliance" name="regulatory_compliance" rows="4" required
                                              placeholder="Detail your banking licenses, regulatory compliance, AML/KYC procedures, etc."
                                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"></textarea>
                                </div>
                                
                                <div>
                                    <label for="financial_strength" class="block text-sm font-medium text-gray-700 mb-2">
                                        Financial Strength *
                                    </label>
                                    <textarea id="financial_strength" name="financial_strength" rows="4" required
                                              placeholder="Provide information about your assets under management, capital ratios, credit ratings, etc."
                                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"></textarea>
                                </div>
                                
                                <div>
                                    <label for="insurance_coverage" class="block text-sm font-medium text-gray-700 mb-2">
                                        Insurance Coverage *
                                    </label>
                                    <textarea id="insurance_coverage" name="insurance_coverage" rows="4" required
                                              placeholder="Describe your deposit insurance, professional indemnity, cyber security, and other insurance coverage"
                                              class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"></textarea>
                                </div>
                            </div>
                        </div>

                        <!-- Additional Information -->
                        <div class="bg-white border border-gray-200 rounded-xl p-6">
                            <h3 class="text-xl font-semibold text-gray-900 mb-4">Additional Information</h3>
                            
                            <div>
                                <label for="partnership_vision" class="block text-sm font-medium text-gray-700 mb-2">
                                    Partnership Vision
                                </label>
                                <textarea id="partnership_vision" name="partnership_vision" rows="4"
                                          placeholder="Share your vision for partnering with FinAegis and how you see this collaboration benefiting both parties"
                                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"></textarea>
                            </div>
                        </div>

                        <!-- Terms -->
                        <div class="bg-amber-50 border border-amber-200 rounded-xl p-6">
                            <div class="flex items-start">
                                <input type="checkbox" id="terms" name="terms" required
                                       class="mt-1 mr-3 h-4 w-4 text-indigo-600 border-gray-300 rounded focus:ring-indigo-500">
                                <label for="terms" class="text-sm text-gray-700">
                                    I confirm that the information provided is accurate and that I am authorized to submit this application on behalf of the institution. I understand that FinAegis will conduct due diligence and that meeting the requirements does not guarantee acceptance as a partner institution.
                                </label>
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="flex justify-end">
                            <button type="submit" class="bg-indigo-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-indigo-700 transition">
                                Submit Application
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        @include('partials.footer')
    </body>
</html>