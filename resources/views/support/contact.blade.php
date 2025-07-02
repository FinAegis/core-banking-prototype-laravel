<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Contact FinAegis support team. Get help with alpha testing feedback and report issues for our open source banking platform.">
    
    <title>Contact Us - FinAegis Support</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    
    <style>
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
    </style>
</head>
<body class="antialiased bg-gray-50">
    <x-platform-banners />
    
    <!-- Spacer for fixed banner -->
    <div class="h-12"></div>
    
    <x-main-navigation />

    <!-- Hero Section -->
    <section class="pt-16 pb-20 gradient-bg text-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <h1 class="text-5xl font-bold mb-6">Contact Us</h1>
                <p class="text-xl text-purple-100 max-w-3xl mx-auto">
                    We're here to help during our alpha testing phase. Share your feedback, report issues, or ask questions about FinAegis.
                </p>
            </div>
        </div>
    </section>

    <!-- Contact Options -->
    <section class="py-20 bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 mb-16">
                <!-- Email Support -->
                <div class="text-center">
                    <div class="w-16 h-16 bg-indigo-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Email Support</h3>
                    <p class="text-gray-600 mb-2">General inquiries & support</p>
                    <a href="mailto:info@finaegis.org" class="text-indigo-600 hover:text-indigo-700 font-medium">info@finaegis.org</a>
                </div>
                
                <!-- GitHub Issues -->
                <div class="text-center">
                    <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-purple-600" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">GitHub Issues</h3>
                    <p class="text-gray-600 mb-2">Report bugs & features</p>
                    <a href="https://github.com/FinAegis/core-banking-prototype-laravel/issues" class="text-purple-600 hover:text-purple-700 font-medium">Create Issue</a>
                </div>
                
                <!-- Community Forum -->
                <div class="text-center">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Community Forum</h3>
                    <p class="text-gray-600 mb-2">Join discussions</p>
                    <a href="https://github.com/FinAegis/core-banking-prototype-laravel/discussions" target="_blank" class="text-green-600 hover:text-green-700 font-medium">Visit Forum</a>
                </div>
            </div>

            <!-- Contact Form -->
            <div class="max-w-3xl mx-auto">
                @if(session('success'))
                    <div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
                        <p class="text-green-800">
                            <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            {{ session('success') }}
                        </p>
                    </div>
                @endif

                <div class="bg-white rounded-2xl shadow-xl p-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Send us a message</h2>
                    
                    <form method="POST" action="{{ route('support.contact.submit') }}" class="space-y-6" enctype="multipart/form-data">
                        @csrf
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <!-- Name -->
                            <div>
                                <label for="name" class="block text-sm font-medium text-gray-700 mb-2">
                                    Your Name
                                </label>
                                <input type="text" name="name" id="name" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                            
                            <!-- Email -->
                            <div>
                                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">
                                    Email Address
                                </label>
                                <input type="email" name="email" id="email" required
                                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            </div>
                        </div>
                        
                        <!-- Subject -->
                        <div>
                            <label for="subject" class="block text-sm font-medium text-gray-700 mb-2">
                                Subject
                            </label>
                            <select name="subject" id="subject" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="">Select a topic</option>
                                <option value="account">Account Issues</option>
                                <option value="technical">Technical Support</option>
                                <option value="billing">Billing & Payments</option>
                                <option value="gcu">GCU Questions</option>
                                <option value="api">API & Integration</option>
                                <option value="compliance">Compliance & Security</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <!-- Message -->
                        <div>
                            <label for="message" class="block text-sm font-medium text-gray-700 mb-2">
                                Message
                            </label>
                            <textarea name="message" id="message" rows="6" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                placeholder="Please describe your issue or question in detail..."></textarea>
                        </div>
                        
                        <!-- Priority -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">
                                Priority Level
                            </label>
                            <div class="flex space-x-4">
                                <label class="flex items-center">
                                    <input type="radio" name="priority" value="low" class="mr-2" checked>
                                    <span class="text-sm text-gray-600">Low</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="priority" value="medium" class="mr-2">
                                    <span class="text-sm text-gray-600">Medium</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="priority" value="high" class="mr-2">
                                    <span class="text-sm text-gray-600">High</span>
                                </label>
                                <label class="flex items-center">
                                    <input type="radio" name="priority" value="urgent" class="mr-2">
                                    <span class="text-sm text-gray-600">Urgent</span>
                                </label>
                            </div>
                        </div>
                        
                        <!-- Attachment -->
                        <div>
                            <label for="attachment" class="block text-sm font-medium text-gray-700 mb-2">
                                Attachment (optional)
                            </label>
                            <input type="file" name="attachment" id="attachment"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                accept=".pdf,.png,.jpg,.jpeg,.doc,.docx">
                            <p class="text-sm text-gray-500 mt-1">Max file size: 10MB. Accepted formats: PDF, PNG, JPG, DOC, DOCX</p>
                        </div>
                        
                        <!-- Submit Button -->
                        <div>
                            <button type="submit"
                                class="w-full bg-indigo-600 text-white py-3 px-6 rounded-lg font-semibold hover:bg-indigo-700 transition">
                                Send Message
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Response Time Notice -->
                <div class="mt-8 bg-blue-50 border border-blue-200 rounded-lg p-4">
                    <p class="text-blue-800 text-center">
                        <svg class="w-5 h-5 inline-block mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="font-semibold">Response Time:</span> We typically respond within 24-48 hours during business days.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Open Source Section -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">Open Source Project</h2>
                <p class="text-xl text-gray-600">FinAegis is open source and community-driven</p>
            </div>
            
            <div class="max-w-3xl mx-auto bg-white rounded-2xl shadow-xl p-8">
                <div class="grid md:grid-cols-2 gap-8">
                    <div>
                        <h3 class="text-xl font-semibold mb-4">Contribute Code</h3>
                        <p class="text-gray-600 mb-4">
                            Help us build the future of democratic banking. Review our code, submit pull requests, and improve the platform.
                        </p>
                        <a href="https://github.com/FinAegis/core-banking-prototype-laravel" class="text-indigo-600 font-medium hover:text-indigo-700">
                            View Repository →
                        </a>
                    </div>
                    
                    <div>
                        <h3 class="text-xl font-semibold mb-4">Report Issues</h3>
                        <p class="text-gray-600 mb-4">
                            Found a bug or have a feature request? Let us know on GitHub so we can improve the platform.
                        </p>
                        <a href="https://github.com/FinAegis/core-banking-prototype-laravel/issues/new" class="text-indigo-600 font-medium hover:text-indigo-700">
                            Create Issue →
                        </a>
                    </div>
                </div>
                
                <div class="mt-8 pt-8 border-t border-gray-200 text-center">
                    <p class="text-gray-600">
                        <span class="font-semibold">License:</span> MIT Open Source License
                    </p>
                </div>
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
                        <li><a href="/developers/api-docs" class="hover:text-white transition">API Reference</a></li>
                        <li><a href="/developers/sdks" class="hover:text-white transition">SDKs</a></li>
                        <li><a href="/status" class="hover:text-white transition">System Status</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-4">Resources</h4>
                    <ul class="space-y-2">
                        <li><a href="/support" class="hover:text-white transition">Support</a></li>
                        <li><a href="/blog" class="hover:text-white transition">Blog</a></li>
                        <li><a href="/partners" class="hover:text-white transition">Partners</a></li>
                        <li><a href="/about" class="hover:text-white transition">About</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="text-white font-semibold mb-4">Legal</h4>
                    <ul class="space-y-2">
                        <li><a href="/legal/terms" class="hover:text-white transition">Terms</a></li>
                        <li><a href="/legal/privacy" class="hover:text-white transition">Privacy</a></li>
                        <li><a href="/legal/cookies" class="hover:text-white transition">Cookies</a></li>
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