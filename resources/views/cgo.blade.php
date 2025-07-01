<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="FinAegis Continuous Growth Offering (CGO) - Invest in the future of democratic banking. Get ownership certificates and support platform development.">
    
    <title>Continuous Growth Offering - FinAegis</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
</head>
<body class="antialiased">
    <x-platform-banners />
    <x-main-navigation />

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
                    
                    @auth
                        <a href="{{ route('cgo.invest') }}" id="investButton" class="block w-full bg-gray-400 text-gray-600 px-6 py-3 rounded-lg font-semibold cursor-not-allowed text-center" onclick="return checkCountdown()">
                            <span id="investButtonText">Invest Now (Launching Soon)</span>
                        </a>
                    @else
                        <form action="{{ route('cgo.notify') }}" method="POST" class="space-y-4">
                            @csrf
                            <input type="email" name="email" required
                                class="w-full px-4 py-3 bg-white/20 border border-white/30 rounded-lg text-white placeholder-white/70 focus:outline-none focus:border-white"
                                placeholder="Enter your email address">
                            <button type="submit" class="w-full bg-white text-indigo-600 px-6 py-3 rounded-lg font-semibold hover:bg-gray-100 transition">
                                Notify Me
                            </button>
                        </form>
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
                    Unlike traditional ICOs with fixed end dates, our CGO continues indefinitely, allowing continuous investment as the platform grows.
                </p>
            </div>
            
            <div class="grid md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div class="w-20 h-20 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Ownership Certificates</h3>
                    <p class="text-gray-600">Receive official certificates proving your ownership stake in FinAegis (max 1% per round)</p>
                </div>
                
                <div class="text-center">
                    <div class="w-20 h-20 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Increasing Value</h3>
                    <p class="text-gray-600">Share prices increase over time as the platform grows and develops new features</p>
                </div>
                
                <div class="text-center">
                    <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                        <svg class="w-10 h-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-3">Fund Development</h3>
                    <p class="text-gray-600">100% of CGO funds go directly to platform development and expansion</p>
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
                            <span>Digital ownership certificate</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Early access to new features</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Monthly investor updates</span>
                        </li>
                    </ul>
                </div>
                
                <!-- Silver Tier -->
                <div class="bg-white rounded-2xl shadow-lg p-8 border-2 border-indigo-400 relative">
                    <div class="absolute -top-4 left-1/2 transform -translate-x-1/2">
                        <span class="bg-indigo-600 text-white px-4 py-1 rounded-full text-sm font-semibold">POPULAR</span>
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
                            <span>Physical certificate option</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Voting rights on platform decisions</span>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-5 h-5 text-green-500 mt-1 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <span>Quarterly investor calls</span>
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
                            <span>Direct access to founding team</span>
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
                <p class="text-gray-600 mb-4">Maximum 1% ownership per investment round to ensure fair distribution</p>
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
                        <p>Supporter badge + Discord access</p>
                    </div>
                    <div class="bg-white/20 backdrop-blur rounded-xl p-6">
                        <h3 class="text-xl font-bold mb-2">$25/month</h3>
                        <p>Beta features + Monthly Q&A</p>
                    </div>
                    <div class="bg-white/20 backdrop-blur rounded-xl p-6">
                        <h3 class="text-xl font-bold mb-2">$100/month</h3>
                        <p>Direct input on roadmap + 1-on-1 calls</p>
                    </div>
                </div>
                <a href="https://patreon.com/finaegis" target="_blank" class="bg-white text-pink-600 px-8 py-3 rounded-lg font-semibold hover:bg-gray-100 transition inline-block">
                    Support on Patreon
                </a>
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
                <a href="/docs/VISION.md" target="_blank" class="block bg-white rounded-xl p-6 shadow-md hover:shadow-lg transition">
                    <h3 class="text-xl font-semibold mb-2">Vision & Mission</h3>
                    <p class="text-gray-600">Our long-term vision for democratizing global finance</p>
                </a>
                
                <a href="/docs/ARCHITECTURE.md" target="_blank" class="block bg-white rounded-xl p-6 shadow-md hover:shadow-lg transition">
                    <h3 class="text-xl font-semibold mb-2">Technical Architecture</h3>
                    <p class="text-gray-600">How we're building a robust, scalable banking platform</p>
                </a>
                
                <a href="/docs/ROADMAP.md" target="_blank" class="block bg-white rounded-xl p-6 shadow-md hover:shadow-lg transition">
                    <h3 class="text-xl font-semibold mb-2">Development Roadmap</h3>
                    <p class="text-gray-600">Our plans for features, integrations, and expansion</p>
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    @include('partials.footer')
    
    <!-- Countdown Script -->
    <script>
        // Set the date we're counting down to (July 21st, 2025)
        const countDownDate = new Date("Jul 21, 2025 00:00:00").getTime();
        let isLive = false;
        
        function checkCountdown() {
            if (!isLive) {
                alert('The CGO will launch on July 21st, 2025. Please check back then!');
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
</body>
</html>