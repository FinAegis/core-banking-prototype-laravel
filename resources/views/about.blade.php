<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Learn about FinAegis - revolutionizing banking with democratic governance and the Global Currency Unit. Our mission, team, and journey.">
    
    <title>About FinAegis - Our Mission & Team</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .team-card {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .team-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="antialiased">
    <!-- Navigation -->
    @include('partials.public-nav')

    <!-- Hero Section -->
    <section class="pt-16 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="text-center">
                <h1 class="text-5xl font-bold text-gray-900 mb-6">About FinAegis</h1>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    We're building the future of democratic banking, where financial services are transparent, accessible, and governed by the community.
                </p>
            </div>
        </div>
    </section>

    <!-- Mission Section -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
                <div>
                    <h2 class="text-4xl font-bold text-gray-900 mb-6">Our Mission</h2>
                    <p class="text-lg text-gray-600 mb-4">
                        FinAegis is on a mission to democratize global finance through innovative technology and community governance. We believe that financial services should be accessible to everyone, regardless of geography or economic status.
                    </p>
                    <p class="text-lg text-gray-600 mb-4">
                        Our Global Currency Unit (GCU) represents a new paradigm in currency design - a basket currency that's stable, democratic, and backed by real assets across multiple partner banks.
                    </p>
                    <p class="text-lg text-gray-600">
                        By combining cutting-edge technology with democratic principles, we're creating a financial ecosystem that serves the many, not just the few.
                    </p>
                </div>
                <div class="gradient-bg rounded-2xl p-8 text-white">
                    <h3 class="text-2xl font-bold mb-6">Our Core Values</h3>
                    <ul class="space-y-4">
                        <li class="flex items-start">
                            <svg class="w-6 h-6 mr-3 mt-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold">Transparency</h4>
                                <p class="text-purple-100">Open-source technology and clear governance</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 mr-3 mt-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold">Democracy</h4>
                                <p class="text-purple-100">Community-driven decision making</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 mr-3 mt-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold">Security</h4>
                                <p class="text-purple-100">Bank-grade protection for all users</p>
                            </div>
                        </li>
                        <li class="flex items-start">
                            <svg class="w-6 h-6 mr-3 mt-1 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                            </svg>
                            <div>
                                <h4 class="font-semibold">Innovation</h4>
                                <p class="text-purple-100">Pushing boundaries of financial technology</p>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    <!-- Story Section -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-4xl font-bold text-center text-gray-900 mb-12">Our Story</h2>
            <div class="prose prose-lg mx-auto">
                <p>
                    FinAegis was founded in 2024 with a simple yet ambitious goal: to create a financial system that truly serves its users. We saw how traditional banking systems were failing to adapt to the global, interconnected world we live in.
                </p>
                <p>
                    The idea for the Global Currency Unit (GCU) came from recognizing that no single national currency could serve as a stable, neutral medium of exchange for the global economy. By creating a basket currency governed democratically by its users, we could provide stability while ensuring that no single entity controls the system.
                </p>
                <p>
                    Today, FinAegis serves thousands of users across the globe, processing millions in transactions daily. Our platform has evolved from a simple idea to a comprehensive financial ecosystem, but our core mission remains the same: democratizing finance for everyone.
                </p>
            </div>
        </div>
    </section>

    <!-- Team Section -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-4xl font-bold text-gray-900 mb-4">Meet Our Team</h2>
                <p class="text-xl text-gray-600">The passionate people behind FinAegis</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- Team Member 1 -->
                <div class="team-card bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="h-64 bg-gradient-to-br from-indigo-400 to-purple-500"></div>
                    <div class="p-6">
                        <h3 class="text-xl font-semibold mb-1">Sarah Chen</h3>
                        <p class="text-indigo-600 mb-3">Chief Executive Officer</p>
                        <p class="text-gray-600">
                            Former central bank economist with 15 years of experience in monetary policy and digital currencies.
                        </p>
                    </div>
                </div>
                
                <!-- Team Member 2 -->
                <div class="team-card bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="h-64 bg-gradient-to-br from-purple-400 to-pink-500"></div>
                    <div class="p-6">
                        <h3 class="text-xl font-semibold mb-1">Marcus Weber</h3>
                        <p class="text-indigo-600 mb-3">Chief Technology Officer</p>
                        <p class="text-gray-600">
                            Blockchain pioneer and distributed systems expert with a passion for scalable financial infrastructure.
                        </p>
                    </div>
                </div>
                
                <!-- Team Member 3 -->
                <div class="team-card bg-white rounded-xl shadow-md overflow-hidden">
                    <div class="h-64 bg-gradient-to-br from-green-400 to-blue-500"></div>
                    <div class="p-6">
                        <h3 class="text-xl font-semibold mb-1">Elena Rodriguez</h3>
                        <p class="text-indigo-600 mb-3">Chief Compliance Officer</p>
                        <p class="text-gray-600">
                            International banking regulation expert ensuring our platform exceeds global compliance standards.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Timeline Section -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-4xl font-bold text-center text-gray-900 mb-12">Our Journey</h2>
            
            <div class="relative">
                <div class="absolute left-1/2 transform -translate-x-1/2 h-full w-1 bg-indigo-200"></div>
                
                <!-- Timeline Items -->
                <div class="space-y-12">
                    <!-- 2024 Q1 -->
                    <div class="flex items-center justify-center">
                        <div class="w-full md:w-5/12 text-right pr-8">
                            <h3 class="text-xl font-semibold">Company Founded</h3>
                            <p class="text-gray-600">Q1 2024</p>
                        </div>
                        <div class="w-8 h-8 bg-indigo-600 rounded-full border-4 border-white shadow-md"></div>
                        <div class="w-full md:w-5/12 pl-8">
                            <p class="text-gray-600">Started with a vision to democratize global finance</p>
                        </div>
                    </div>
                    
                    <!-- 2024 Q2 -->
                    <div class="flex items-center justify-center">
                        <div class="w-full md:w-5/12 text-right pr-8">
                            <p class="text-gray-600">Built core banking infrastructure and event sourcing architecture</p>
                        </div>
                        <div class="w-8 h-8 bg-indigo-600 rounded-full border-4 border-white shadow-md"></div>
                        <div class="w-full md:w-5/12 pl-8">
                            <h3 class="text-xl font-semibold">Platform Development</h3>
                            <p class="text-gray-600">Q2 2024</p>
                        </div>
                    </div>
                    
                    <!-- 2024 Q3 -->
                    <div class="flex items-center justify-center">
                        <div class="w-full md:w-5/12 text-right pr-8">
                            <h3 class="text-xl font-semibold">GCU Launch</h3>
                            <p class="text-gray-600">Q3 2024</p>
                        </div>
                        <div class="w-8 h-8 bg-indigo-600 rounded-full border-4 border-white shadow-md"></div>
                        <div class="w-full md:w-5/12 pl-8">
                            <p class="text-gray-600">Introduced the Global Currency Unit with democratic governance</p>
                        </div>
                    </div>
                    
                    <!-- 2025 Q1 -->
                    <div class="flex items-center justify-center">
                        <div class="w-full md:w-5/12 text-right pr-8">
                            <p class="text-gray-600">Integrated with Paysera, Deutsche Bank, and Santander</p>
                        </div>
                        <div class="w-8 h-8 bg-indigo-600 rounded-full border-4 border-white shadow-md"></div>
                        <div class="w-full md:w-5/12 pl-8">
                            <h3 class="text-xl font-semibold">Bank Partnerships</h3>
                            <p class="text-gray-600">Q1 2025</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-20 gradient-bg text-white">
        <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
            <h2 class="text-4xl font-bold mb-6">Join Us in Building the Future</h2>
            <p class="text-xl mb-8 text-purple-100">Be part of the financial revolution with FinAegis</p>
            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                <a href="{{ route('register') }}" class="bg-white text-indigo-600 px-8 py-4 rounded-lg text-lg font-semibold hover:bg-gray-100 transition">
                    Open an Account
                </a>
                <a href="/careers" class="border-2 border-white text-white px-8 py-4 rounded-lg text-lg font-semibold hover:bg-white hover:text-indigo-600 transition">
                    Join Our Team
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    @include('partials.footer')
</body>
</html>