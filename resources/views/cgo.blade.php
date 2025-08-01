@extends('layouts.public')

@section('title', 'Continuous Growth Offering - FinAegis')

@section('seo')
    @include('partials.seo', [
        'title' => 'Continuous Growth Offering - FinAegis',
        'description' => 'FinAegis Continuous Growth Offering (CGO) - Invest in the future of democratic banking. Get ownership certificates and support platform development.',
        'keywords' => 'FinAegis CGO, continuous growth offering, investment, democratic banking, ownership certificates',
    ])
@endsection

@section('content')

    <!-- Hero Section -->
    <section class="pt-16 bg-gradient-to-br from-indigo-600 to-purple-700 text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
            <div class="text-center">
                <h1 class="text-5xl md:text-6xl font-bold mb-6 text-white">
                    Continuous Growth Offering (CGO)
                </h1>
                <p class="text-xl md:text-2xl mb-8 text-purple-100 max-w-3xl mx-auto">
                    Invest in the future of democratic banking. Own a piece of FinAegis and shape the future of global finance.
                </p>
                
                <!-- Important Notice -->
                <div class="bg-green-500/20 backdrop-blur-sm border-2 border-green-400 rounded-2xl p-6 max-w-2xl mx-auto mb-8">
                    <div class="flex items-start">
                        <svg class="w-6 h-6 text-green-300 mr-3 mt-1 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <div class="text-left">
                            <h3 class="text-lg font-bold text-white mb-2">Real Investment Opportunity</h3>
                            <p class="text-green-100 text-sm">
                                This is a genuine investment opportunity. While the rest of the platform is in alpha testing, 
                                the CGO program is fully operational. All investments are real, legally binding, and will result 
                                in actual ownership certificates of FinAegis Ltd.
                            </p>
                        </div>
                    </div>
                </div>
                
                <!-- Countdown Timer -->
                <div class="bg-white/10 backdrop-blur rounded-2xl p-8 max-w-2xl mx-auto mb-8">
                    <h3 class="text-2xl font-bold mb-4 text-white">CGO Launches In:</h3>
                    <div id="countdown" class="grid grid-cols-4 gap-4 text-center">
                        <div>
                            <div class="bg-white/20 rounded-lg p-4 text-white text-3xl font-bold" id="days">00</div>
                            <p class="mt-2 text-sm text-purple-200">Days</p>
                        </div>
                        <div>
                            <div class="bg-white/20 rounded-lg p-4 text-white text-3xl font-bold" id="hours">00</div>
                            <p class="mt-2 text-sm text-purple-200">Hours</p>
                        </div>
                        <div>
                            <div class="bg-white/20 rounded-lg p-4 text-white text-3xl font-bold" id="minutes">00</div>
                            <p class="mt-2 text-sm text-purple-200">Minutes</p>
                        </div>
                        <div>
                            <div class="bg-white/20 rounded-lg p-4 text-white text-3xl font-bold" id="seconds">00</div>
                            <p class="mt-2 text-sm text-purple-200">Seconds</p>
                        </div>
                    </div>
                </div>
                
                <!-- Early Access Form -->
                <div class="bg-white/10 backdrop-blur rounded-2xl p-8 max-w-lg mx-auto">
                    <h3 class="text-xl font-bold mb-4 text-white">Get Early Access</h3>
                    <p class="mb-6 text-purple-200">Be the first to know when CGO launches and get exclusive early investor benefits.</p>
                    
                    @if(session('success'))
                        <div class="bg-green-500/20 border border-green-500/50 rounded-lg p-4 mb-4">
                            <p class="text-green-100">{{ session('success') }}</p>
                        </div>
                    @endif
                    
                    <form action="{{ route('cgo.notify') }}" method="POST" class="space-y-4">
                        @csrf
                        <input type="email" name="email" required
                            class="w-full px-4 py-3 bg-white/20 border border-white/30 rounded-lg text-white placeholder-white/70 focus:outline-none focus:border-white"
                            placeholder="Enter your email address"
                            @auth value="{{ auth()->user()->email }}" @endauth>
                        <button type="submit" class="w-full bg-white text-indigo-600 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 transition">
                            Notify Me
                        </button>
                    </form>
                    
                    @auth
                        <div class="mt-4 pt-4 border-t border-white/20">
                            <a href="{{ route('cgo.invest') }}" id="investButton" class="block w-full bg-gray-400 text-gray-600 px-6 py-3 rounded-lg font-semibold cursor-not-allowed text-center" onclick="return checkCountdown()">
                                <span id="investButtonText">Invest Now (Launching Soon)</span>
                            </a>
                        </div>
                    @endauth
                </div>
            </div>
        </div>
    </section>

    <!-- What is CGO Section -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">What is a Continuous Growth Offering?</h2>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    A unique way to support the development of FinAegis. Your contributions help build the future of democratic banking while receiving recognition and benefits.
                </p>
            </div>
            
            <div class="grid md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div class="w-20 h-20 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Support Certificate</h3>
                    <p class="text-gray-600">Receive digital certificates recognizing your contribution to the democratic banking movement</p>
                </div>
                
                <div class="text-center">
                    <div class="w-20 h-20 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Join the Movement</h3>
                    <p class="text-gray-600">Become part of a community building a fairer, more transparent financial system</p>
                </div>
                
                <div class="text-center">
                    <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Fund Development</h3>
                    <p class="text-gray-600">100% of contributions go directly to platform development and expansion</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Investment Tiers -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Investment Tiers</h2>
                <p class="text-xl text-gray-600">Choose your investment level and benefits</p>
            </div>
            
            <div class="grid md:grid-cols-3 gap-8">
                <!-- Bronze Tier -->
                <div class="bg-white rounded-2xl shadow-lg p-8 border-2 border-gray-200">
                    <div class="text-center mb-6">
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Bronze</h3>
                        <p class="text-4xl font-bold text-indigo-600">$100 - $999</p>
                    </div>
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Digital certificate of support</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Recognition as project supporter</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Early access to platform features</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Monthly development updates</span>
                        </li>
                    </ul>
                </div>
                
                <!-- Silver Tier -->
                <div class="bg-white rounded-2xl shadow-lg p-8 border-2 border-indigo-400 relative">
                    <div class="absolute -top-4 left-1/2 transform -translate-x-1/2">
                        <span class="bg-indigo-600 text-white px-4 py-1 rounded-full text-sm font-semibold">RECOMMENDED</span>
                    </div>
                    <div class="text-center mb-6">
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Silver</h3>
                        <p class="text-4xl font-bold text-indigo-600">$1,000 - $9,999</p>
                    </div>
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Everything in Bronze</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Monthly community calls</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Digital "Idea Shareholding" certificate</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Input on feature prioritization</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Recognition as "Founding Supporter"</span>
                        </li>
                    </ul>
                </div>
                
                <!-- Gold Tier -->
                <div class="bg-white rounded-2xl shadow-lg p-8 border-2 border-yellow-400">
                    <div class="text-center mb-6">
                        <h3 class="text-2xl font-bold text-gray-900 mb-2">Gold</h3>
                        <p class="text-4xl font-bold text-indigo-600">$10,000+</p>
                    </div>
                    <ul class="space-y-3 mb-8">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Everything in Silver</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Direct discussions with founders</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Potential ownership discussion*</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Advisory board consideration</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Lifetime premium features</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <div class="text-center mt-12">
                <p class="text-sm text-gray-500 mb-4">* Ownership discussions are subject to separate agreements and regulatory compliance</p>
                <p class="text-gray-600 mb-6">This is a contribution to support development, not an investment for financial returns</p>
                <a href="{{ route('cgo.terms') }}" class="text-indigo-600 hover:text-indigo-700 underline font-medium">
                    Read Full Terms and Conditions
                </a>
            </div>
        </div>
    </section>

    <!-- Patreon Support Section -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-gradient-to-r from-orange-500 to-pink-500 rounded-3xl p-12 text-white text-center">
                <h2 class="text-3xl font-bold mb-4">Monthly Contributors via Patreon</h2>
                <p class="text-xl mb-8 max-w-2xl mx-auto">
                    Support ongoing development with monthly contributions and receive exclusive perks
                </p>
                <div class="grid md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white/20 backdrop-blur rounded-xl p-6">
                        <h3 class="text-xl font-bold mb-2">$10/month</h3>
                        <p>Supporter badge + Updates</p>
                    </div>
                    <div class="bg-white/20 backdrop-blur rounded-xl p-6">
                        <h3 class="text-xl font-bold mb-2">$25/month</h3>
                        <p>Beta features + Monthly Q&A</p>
                    </div>
                    <div class="bg-white/20 backdrop-blur rounded-xl p-6">
                        <h3 class="text-xl font-bold mb-2">$100/month</h3>
                        <p>Direct input on roadmap + Priority support</p>
                    </div>
                </div>
                <a href="https://patreon.com/finaegis" target="_blank" class="bg-white text-pink-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition inline-block">
                    Support on Patreon
                </a>
            </div>
        </div>
    </section>

    <!-- Exclusive Investment Section -->
    <section class="py-20 bg-indigo-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h2 class="text-4xl font-bold text-white mb-6">Exclusive Investment Opportunities</h2>
                <p class="text-xl text-indigo-200 mb-8 max-w-3xl mx-auto">
                    Are you an accredited investor or institutional partner looking for exclusive investment opportunities? 
                    We offer special terms for strategic investors who share our vision of democratizing global finance.
                </p>
                <div class="bg-white/10 backdrop-blur rounded-2xl p-8 max-w-2xl mx-auto">
                    <h3 class="text-2xl font-semibold text-white mb-4">Strategic Investment Benefits</h3>
                    <ul class="text-left space-y-3 mb-8 text-indigo-100">
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-400 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Direct equity participation opportunities</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-400 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Board observer rights for qualifying investments</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-400 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Strategic partnership opportunities</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-400 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Customized investment structures</span>
                        </li>
                    </ul>
                    <div class="text-center">
                        <p class="text-lg text-white mb-4">Contact us for exclusive investment details:</p>
                        <a href="mailto:info@finaegis.org" class="inline-flex items-center bg-white text-indigo-900 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            info@finaegis.org
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Documentation Links -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">Learn More About FinAegis</h2>
                <p class="text-xl text-gray-600">Deep dive into our vision, technology, and roadmap</p>
            </div>
            
            <div class="grid md:grid-cols-3 gap-6">
                <a href="{{ route('about') }}" class="block bg-white rounded-xl p-6 shadow-md hover:shadow-lg transition">
                    <h3 class="text-xl font-semibold mb-2">About FinAegis</h3>
                    <p class="text-gray-600">Our mission, story, team, and journey towards democratizing global finance</p>
                </a>
                
                <a href="{{ route('gcu') }}" class="block bg-white rounded-xl p-6 shadow-md hover:shadow-lg transition">
                    <h3 class="text-xl font-semibold mb-2">Global Currency Unit</h3>
                    <p class="text-gray-600">Learn about our innovative basket currency with democratic governance</p>
                </a>
                
                <a href="{{ route('features') }}" class="block bg-white rounded-xl p-6 shadow-md hover:shadow-lg transition">
                    <h3 class="text-xl font-semibold mb-2">Platform Features</h3>
                    <p class="text-gray-600">Explore all the features that make FinAegis unique</p>
                </a>
            </div>
        </div>
    </section>

@endsection

@push('scripts')
<script>
    // Set the date we're counting down to (September 1st, 2025)
    const countDownDate = new Date("Sep 1, 2025 00:00:00").getTime();
    let isLive = false;
    
    function checkCountdown() {
        if (!isLive) {
            alert('The CGO will launch on September 1st, 2025. Please check back then!');
            return false;
        }
        return true;
    }
    
    function updateInvestButton(live) {
        const investButton = document.getElementById('investButton');
        const investButtonText = document.getElementById('investButtonText');
        
        if (investButton) {
            if (live) {
                investButton.classList.remove('bg-gray-400', 'text-gray-600', 'cursor-not-allowed');
                investButton.classList.add('bg-white', 'text-indigo-600', 'hover:bg-gray-100');
                investButtonText.textContent = 'Invest Now';
            } else {
                investButton.classList.remove('bg-white', 'text-indigo-600', 'hover:bg-gray-100');
                investButton.classList.add('bg-gray-400', 'text-gray-600', 'cursor-not-allowed');
            }
        }
    }
    
    // Update the countdown every 1 second
    const x = setInterval(function() {
        const now = new Date().getTime();
        const distance = countDownDate - now;
        
        // Calculate days, hours, minutes and seconds
        const days = Math.floor(distance / (1000 * 60 * 60 * 24));
        const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);
        
        // Display the result
        document.getElementById("days").innerHTML = days.toString().padStart(2, '0');
        document.getElementById("hours").innerHTML = hours.toString().padStart(2, '0');
        document.getElementById("minutes").innerHTML = minutes.toString().padStart(2, '0');
        document.getElementById("seconds").innerHTML = seconds.toString().padStart(2, '0');
        
        // If the countdown is finished
        if (distance < 0) {
            clearInterval(x);
            document.getElementById("countdown").innerHTML = "<div class='text-3xl font-bold text-green-300'>CGO IS LIVE!</div>";
            isLive = true;
            updateInvestButton(true);
        }
    }, 1000);
    
    // Initial check
    const now = new Date().getTime();
    if (countDownDate - now < 0) {
        isLive = true;
        updateInvestButton(true);
    }
</script>
@endpush