<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Contact FinAegis support team. Get help with your account, technical issues, or general inquiries about our democratic banking platform.">
    
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
<body class="antialiased">
    <!-- Navigation -->
    @include('partials.public-nav')

    <!-- Hero Section -->
    <section class="pt-16 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="text-center">
                <h1 class="text-5xl font-bold text-gray-900 mb-6">Contact Us</h1>
                <p class="text-xl text-gray-600 max-w-3xl mx-auto">
                    We're here to help. Reach out to our support team for assistance with your account or any questions about FinAegis.
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
                    <p class="text-gray-600 mb-2">For general inquiries</p>
                    <a href="mailto:support@finaegis.com" class="text-indigo-600 hover:text-indigo-700 font-medium">support@finaegis.com</a>
                </div>
                
                <!-- Live Chat -->
                <div class="text-center">
                    <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Live Chat</h3>
                    <p class="text-gray-600 mb-2">Available 24/7</p>
                    <button onclick="alert('Live chat coming soon!')" class="text-purple-600 hover:text-purple-700 font-medium">Start Chat</button>
                </div>
                
                <!-- Phone Support -->
                <div class="text-center">
                    <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Phone Support</h3>
                    <p class="text-gray-600 mb-2">Business hours only</p>
                    <a href="tel:+1234567890" class="text-green-600 hover:text-green-700 font-medium">+1 (234) 567-890</a>
                </div>
            </div>

            <!-- Contact Form -->
            <div class="max-w-3xl mx-auto">
                <div class="bg-white rounded-2xl shadow-xl p-8">
                    <h2 class="text-2xl font-bold text-gray-900 mb-6">Send us a message</h2>
                    
                    <form method="POST" action="#" class="space-y-6">
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
                <div class="mt-8 text-center">
                    <p class="text-gray-600">
                        <svg class="w-5 h-5 inline-block mr-2 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Average response time: <span class="font-semibold">2-4 business hours</span>
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Office Locations -->
    <section class="py-20 bg-gray-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <h2 class="text-3xl font-bold text-center text-gray-900 mb-12">Our Offices</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- HQ -->
                <div class="bg-white rounded-xl p-6 shadow-md">
                    <h3 class="text-xl font-semibold mb-3">Headquarters</h3>
                    <p class="text-gray-600">
                        123 Financial District<br>
                        New York, NY 10004<br>
                        United States
                    </p>
                </div>
                
                <!-- Europe -->
                <div class="bg-white rounded-xl p-6 shadow-md">
                    <h3 class="text-xl font-semibold mb-3">Europe Office</h3>
                    <p class="text-gray-600">
                        456 Banking Street<br>
                        London EC2N 4AG<br>
                        United Kingdom
                    </p>
                </div>
                
                <!-- Asia -->
                <div class="bg-white rounded-xl p-6 shadow-md">
                    <h3 class="text-xl font-semibold mb-3">Asia Pacific</h3>
                    <p class="text-gray-600">
                        789 Finance Tower<br>
                        Singapore 048623<br>
                        Singapore
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    @include('partials.footer')
</body>
</html>