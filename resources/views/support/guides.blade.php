@extends('layouts.public')

@section('title', 'Support Guides - ' . config('brand.name', 'Zelta'))

@section('seo')
    @include('partials.seo', [
        'title' => 'Support Guides - ' . config('brand.name', 'Zelta'),
        'description' => 'Guides for ' . config('brand.name', 'Zelta') . ', the open-source core banking platform with 61 domain modules. They explain how to try the sandbox, run the code on your own machine and send feedback.',
        'keywords' => config('brand.name', 'Zelta') . ' guides, documentation, tutorials, open source banking, core banking platform',
    ])
@endsection

@push('styles')
<style>
    .card-feature {
        transition: all 0.3s ease;
    }
</style>
@endpush
@section('content')

        <!-- Hero Section -->
        <section class="bg-fa-navy relative overflow-hidden">
            <div class="absolute inset-0 bg-grid-pattern"></div>
            <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 lg:py-24">
                <div class="text-center">
                    <h1 class="font-display text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">
                        Platform Guides
                    </h1>
                    <p class="text-xl md:text-2xl mb-8 text-slate-400 max-w-4xl mx-auto">
                        These guides explain how to try the {{ config('brand.name', 'Zelta') }} sandbox and how to contribute to the open-source project.
                    </p>
                    <div class="max-w-2xl mx-auto">
                        <div class="relative">
                            <input type="text" id="guide-search" placeholder="Search guides..."
                                class="w-full px-6 py-3 rounded-lg text-slate-900 placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-white pl-12">
                            <svg class="absolute left-4 top-3.5 w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>
            </div>
            <div class="absolute bottom-0 left-0 right-0 h-px bg-gradient-to-r from-transparent via-blue-500/20 to-transparent"></div>
        </section>

        <!-- Quick Links -->
        <section class="py-16 bg-white">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="font-display text-3xl font-bold text-slate-900">Popular Guides</h2>
                    <p class="mt-4 text-xl text-slate-500">Start with these four guides.</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <a href="#getting-started" class="card-feature">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-slate-900 mb-2">Getting Started</h3>
                        <p class="text-slate-500 text-sm">Platform overview</p>
                    </a>

                    <a href="#development" class="card-feature">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-slate-900 mb-2">Development Setup</h3>
                        <p class="text-slate-500 text-sm">Contributing to the project</p>
                    </a>

                    <a href="#testing" class="card-feature">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-slate-900 mb-2">Testing Features</h3>
                        <p class="text-slate-500 text-sm">Exploring demo functionality</p>
                    </a>

                    <a href="#feedback" class="card-feature">
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mb-4">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"/>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-slate-900 mb-2">Providing Feedback</h3>
                        <p class="text-slate-500 text-sm">Reporting issues and ideas</p>
                    </a>
                </div>
            </div>
        </section>

        <!-- Guides Content -->
        <section class="py-16 bg-slate-50">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
                <!-- Getting Started -->
                <section id="getting-started" class="mb-16">
                    <div class="border-b border-gray-200 pb-6 mb-8">
                        <h2 class="text-2xl font-bold text-slate-900">Getting Started</h2>
                        <p class="mt-2 text-slate-500">This section explains what the sandbox is and how to use it.</p>
                    </div>
                    
                    <div class="space-y-6">
                        <div class="card-feature">
                            <h3 class="text-lg font-semibold text-slate-900 mb-3">About the Sandbox</h3>
                            <p class="text-slate-500 mb-4">The public {{ config('brand.name', 'Zelta') }} site runs in sandbox mode.</p>
                            <ul class="list-disc list-inside text-slate-500 space-y-2">
                                <li>All transactions and balances use test data, so no real money is involved.</li>
                                <li>Most modules can be tried in demo mode. Some features, such as virtual cards, stay switched off until their pilot starts.</li>
                                <li>The sandbox runs the latest release, which is v7.18.1.</li>
                                <li>Your feedback tells us what to fix and build next.</li>
                            </ul>
                            <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                <p class="text-blue-800 text-sm">
                                    The sandbox uses test data only. It processes no real financial transactions.
                                </p>
                            </div>
                        </div>

                        <div class="card-feature">
                            <h3 class="text-lg font-semibold text-slate-900 mb-3">Creating Your Test Account</h3>
                            <p class="text-slate-500 mb-4">You can create a test account in four steps.</p>
                            <ol class="list-decimal list-inside text-slate-500 space-y-2">
                                <li>Click "Register" on the homepage.</li>
                                <li>Enter an email address. It can be a test address or your real one.</li>
                                <li>Choose a password that you use only for testing.</li>
                                <li>Open the dashboard and look around with sandbox data.</li>
                            </ol>
                            <p class="text-slate-500 mt-4">
                                The sandbox does not ask for a real identity check.
                            </p>
                        </div>

                        <div class="card-feature">
                            <h3 class="text-lg font-semibold text-slate-900 mb-3">Available Features</h3>
                            <p class="text-slate-500 mb-4">The sandbox lets you try these parts of the platform.</p>
                            <ul class="list-disc list-inside text-slate-500 space-y-2">
                                <li>You can register and sign in.</li>
                                <li>The dashboard shows simulated account data.</li>
                                <li>The GCU demo shows a simulated currency basket.</li>
                                <li>More than 1,000 API routes and a GraphQL API are available with test data.</li>
                                <li>Testers with an administrator role can open the admin panel.</li>
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- Development Setup -->
                <section id="development" class="mb-16">
                    <div class="border-b border-gray-200 pb-6 mb-8">
                        <h2 class="text-2xl font-bold text-slate-900">Development Setup</h2>
                        <p class="mt-2 text-slate-500">This section explains how to run the code and send changes.</p>
                    </div>
                    
                    <div class="space-y-6">
                        <div class="card-feature">
                            <h3 class="text-lg font-semibold text-slate-900 mb-3">Local Development Environment</h3>
                            <p class="text-slate-500 mb-4">These commands install {{ config('brand.name', 'Zelta') }} on your own machine. The README file on GitHub has the full steps, including demo mode.</p>
                            <div class="bg-slate-50 rounded-lg p-4 font-mono text-sm">
                                <p class="text-slate-600"># Clone the repository</p>
                                <p class="text-green-600">git clone {{ config('brand.github_url') }}.git</p>
                                <p class="text-green-600">cd core-banking-prototype-laravel</p>
                                <br>
                                <p class="text-slate-600"># Install dependencies</p>
                                <p class="text-green-600">composer install</p>
                                <p class="text-green-600">npm install</p>
                                <br>
                                <p class="text-slate-600"># Configure environment</p>
                                <p class="text-green-600">cp .env.example .env</p>
                                <p class="text-green-600">php artisan key:generate</p>
                                <br>
                                <p class="text-slate-600"># Run migrations</p>
                                <p class="text-green-600">php artisan migrate</p>
                                <br>
                                <p class="text-slate-600"># Start development server</p>
                                <p class="text-green-600">php artisan serve</p>
                            </div>
                        </div>

                        <div class="card-feature">
                            <h3 class="text-lg font-semibold text-slate-900 mb-3">Tech Stack Overview</h3>
                            <p class="text-slate-500 mb-4">{{ config('brand.name', 'Zelta') }} is a Laravel 12 application written in PHP 8.4.</p>
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <h4 class="font-semibold text-slate-800 mb-2">Backend</h4>
                                    <ul class="text-sm text-slate-500 space-y-1">
                                        <li>• Laravel 12</li>
                                        <li>• MySQL, MariaDB or PostgreSQL</li>
                                        <li>• Redis for cache, queues and event streams</li>
                                        <li>• Laravel Sanctum for API sign-in</li>
                                    </ul>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-slate-800 mb-2">Frontend</h4>
                                    <ul class="text-sm text-slate-500 space-y-1">
                                        <li>• Blade templates</li>
                                        <li>• Tailwind CSS</li>
                                        <li>• Alpine.js</li>
                                        <li>• Laravel Livewire</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="card-feature">
                            <h3 class="text-lg font-semibold text-slate-900 mb-3">Contributing Guidelines</h3>
                            <p class="text-slate-500 mb-4">You can contribute to {{ config('brand.name', 'Zelta') }} in six steps.</p>
                            <ol class="list-decimal list-inside text-slate-500 space-y-2">
                                <li>Fork the repository on GitHub.</li>
                                <li>Create a feature branch with <code class="bg-slate-100 px-2 py-1 rounded">git checkout -b feature/your-feature</code>.</li>
                                <li>Make your changes and add tests for them.</li>
                                <li>Run the tests with <code class="bg-slate-100 px-2 py-1 rounded">./vendor/bin/pest</code>.</li>
                                <li>Commit with clear messages.</li>
                                <li>Push to your fork and open a pull request.</li>
                            </ol>
                        </div>
                    </div>
                </section>

                <!-- Testing Features -->
                <section id="testing" class="mb-16">
                    <div class="border-b border-gray-200 pb-6 mb-8">
                        <h2 class="text-2xl font-bold text-slate-900">Testing Platform Features</h2>
                        <p class="mt-2 text-slate-500">This section explains how to try the demo features.</p>
                    </div>
                    
                    <div class="space-y-6">
                        <div class="card-feature">
                            <h3 class="text-lg font-semibold text-slate-900 mb-3">Simulated Transactions</h3>
                            <p class="text-slate-500 mb-4">Every transaction in the sandbox is simulated.</p>
                            <ul class="list-disc list-inside text-slate-500 space-y-2">
                                <li>All balances are simulated, and new accounts start with demo funds.</li>
                                <li>Transactions involve no real money and no real banks.</li>
                                <li>Currency conversions use demo exchange rates.</li>
                                <li>The transaction history is for demonstration only.</li>
                            </ul>
                            <p class="text-slate-500 mt-4">
                                You can try any transaction type, because nothing in the sandbox is real.
                            </p>
                        </div>

                        <div class="card-feature">
                            <h3 class="text-lg font-semibold text-slate-900 mb-3">API Testing</h3>
                            <p class="text-slate-500 mb-4">You can call the REST API with your sandbox account.</p>
                            <ol class="list-decimal list-inside text-slate-500 space-y-2">
                                <li>Register for an account to get API access.</li>
                                <li>Read the API documentation at <code class="bg-slate-100 px-2 py-1 rounded">/developers/api-docs</code>.</li>
                                <li>Send requests with a tool such as Postman or curl.</li>
                                <li>All API responses use simulated data.</li>
                            </ol>
                            <div class="mt-4 bg-slate-50 rounded-lg p-4 font-mono text-sm">
                                <p class="text-slate-600"># Example API call</p>
                                <p class="text-green-600">curl -X GET http://localhost:8000/api/v1/accounts \</p>
                                <p class="text-green-600">  -H "Authorization: Bearer YOUR_TOKEN"</p>
                            </div>
                        </div>

                        <div class="card-feature">
                            <h3 class="text-lg font-semibold text-slate-900 mb-3">GCU Demo Features</h3>
                            <p class="text-slate-500 mb-4">The GCU demo shows how a currency basket works.</p>
                            <ul class="list-disc list-inside text-slate-500 space-y-2">
                                <li>You can see the current GCU composition with demonstration values.</li>
                                <li>You can see how the simulated basket "votes" work.</li>
                                <li>You can try simulated conversions with GCU demo balances.</li>
                            </ul>
                            <p class="text-slate-500 mt-4">
                                GCU balances, conversions and basket "votes" in the demo are simulated. GCU is not issued, offered or sold, and it has no monetary value.
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Providing Feedback -->
                <section id="feedback" class="mb-16">
                    <div class="border-b border-gray-200 pb-6 mb-8">
                        <h2 class="text-2xl font-bold text-slate-900">Providing Feedback</h2>
                        <p class="mt-2 text-slate-500">Your feedback helps us improve {{ config('brand.name', 'Zelta') }}.</p>
                    </div>
                    
                    <div class="space-y-6">
                        <div class="card-feature">
                            <h3 class="text-lg font-semibold text-slate-900 mb-3">Reporting Bugs</h3>
                            <p class="text-slate-500 mb-4">Please report a bug as a GitHub issue.</p>
                            <ol class="list-decimal list-inside text-slate-500 space-y-2">
                                <li>Check whether the issue already exists on GitHub.</li>
                                <li>Create a new issue with a clear title.</li>
                                <li>Describe the steps that reproduce the bug.</li>
                                <li>Add error messages or screenshots.</li>
                                <li>Name your operating system and browser.</li>
                            </ol>
                            <div class="mt-4">
                                <a href="{{ config('brand.github_url') }}/issues/new" class="text-indigo-600 font-medium hover:text-indigo-700">
                                    Create Bug Report →
                                </a>
                            </div>
                        </div>

                        <div class="card-feature">
                            <h3 class="text-lg font-semibold text-slate-900 mb-3">Feature Requests</h3>
                            <p class="text-slate-500 mb-4">You can suggest a new feature on GitHub.</p>
                            <ul class="list-disc list-inside text-slate-500 space-y-2">
                                <li>Check the existing issues to see whether someone has asked for it.</li>
                                <li>Create a GitHub issue that starts with "[Feature Request]".</li>
                                <li>Describe the feature and what it would give users.</li>
                                <li>Add use cases and examples.</li>
                                <li>Comment on existing requests that you also need.</li>
                            </ul>
                        </div>

                        <div class="card-feature">
                            <h3 class="text-lg font-semibold text-slate-900 mb-3">Community Discussion</h3>
                            <p class="text-slate-500 mb-4">You can talk to the project team and other users in two places.</p>
                            <ul class="list-disc list-inside text-slate-500 space-y-2">
                                <li>GitHub Discussions is for general questions and ideas.</li>
                                <li>You can email {{ config('brand.support_email', 'info@zelta.app') }} with direct feedback.</li>
                            </ul>
                            <div class="mt-4 flex gap-4">
                                <a href="{{ config('brand.github_url') }}/discussions" class="text-indigo-600 font-medium hover:text-indigo-700">
                                    Join Discussions →
                                </a>
                                <a href="mailto:{{ config('brand.support_email', 'info@zelta.app') }}" class="text-indigo-600 font-medium hover:text-indigo-700">
                                    Email Feedback →
                                </a>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Additional Resources -->
                <div class="bg-indigo-50 rounded-xl p-8 text-center">
                    <h3 class="text-xl font-semibold text-slate-900 mb-4">Need More Help?</h3>
                    <p class="text-slate-500 mb-6">
                        The frequently asked questions may answer your question. If not, send us a message.
                    </p>
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <a href="{{ route('support.faq') }}" class="btn-outline-dark">
                            Browse FAQ
                        </a>
                        <a href="{{ route('support.contact') }}" class="btn-primary">
                            Contact Support
                        </a>
                    </div>
                </div>
            </div>
        </section>


        <script>
            // Smooth scrolling for anchor links
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });

            // Search functionality
            const searchInput = document.getElementById('guide-search');
            searchInput.addEventListener('input', (e) => {
                const searchTerm = e.target.value.toLowerCase();
                
                document.querySelectorAll('section[id]').forEach(section => {
                    const content = section.textContent.toLowerCase();
                    
                    if (searchTerm === '' || content.includes(searchTerm)) {
                        section.style.display = 'block';
                    } else {
                        section.style.display = 'none';
                    }
                });
            });
        </script>
@endsection