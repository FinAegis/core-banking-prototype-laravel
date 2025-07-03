<x-guest-layout>
    <div class="bg-white">
        <!-- Header -->
        <div class="bg-gradient-to-r from-gray-900 to-gray-800">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-24">
                <div class="text-center">
                    <h1 class="text-4xl font-bold text-white sm:text-5xl lg:text-6xl">
                        FinAegis Blog
                    </h1>
                    <p class="mt-6 text-xl text-gray-300 max-w-3xl mx-auto">
                        Insights, updates, and thought leadership on the future of multi-asset banking and financial technology.
                    </p>
                </div>
            </div>
        </div>

        <!-- Featured Post -->
        @if($featuredPost)
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="mb-12">
                <span class="text-blue-600 font-semibold text-sm uppercase tracking-wide">Featured Post</span>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                <div class="md:flex">
                    <div class="md:w-1/2">
                        <div class="h-64 md:h-full bg-gradient-to-br from-{{ $featuredPost->gradient_from }} to-{{ $featuredPost->gradient_to }} flex items-center justify-center">
                            <div class="text-center text-white">
                                @if($featuredPost->icon_svg)
                                    {!! $featuredPost->icon_svg !!}
                                @else
                                    <svg class="w-16 h-16 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                    </svg>
                                @endif
                                <p class="text-lg font-semibold">Featured Article</p>
                            </div>
                        </div>
                    </div>
                    <div class="md:w-1/2 p-8">
                        <div class="flex items-center mb-4">
                            <span class="bg-{{ $featuredPost->category_badge_color }}-100 text-{{ $featuredPost->category_badge_color }}-800 text-xs font-semibold px-2.5 py-0.5 rounded capitalize">{{ $featuredPost->category }}</span>
                            <span class="text-gray-500 text-sm ml-3">{{ $featuredPost->published_at->format('F j, Y') }}</span>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900 mb-4">
                            <a href="{{ route('blog.show', $featuredPost->slug) }}" class="hover:text-blue-600 transition">{{ $featuredPost->title }}</a>
                        </h2>
                        <p class="text-gray-600 mb-6">
                            {{ $featuredPost->excerpt }}
                        </p>
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-gray-300 rounded-full flex items-center justify-center mr-4">
                                <span class="text-gray-600 font-semibold text-sm">{{ $featuredPost->author_initials }}</span>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">{{ $featuredPost->author_name }}</p>
                                <p class="text-gray-500 text-sm">{{ $featuredPost->author_role }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        @endif

        <!-- Categories -->
        <div class="bg-gray-50 py-16">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 class="text-3xl font-bold text-gray-900">Explore by Category</h2>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 text-center hover:shadow-md transition duration-200">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v4"/>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-gray-900 mb-2">Platform Updates</h3>
                        <p class="text-gray-600 text-sm mb-3">Latest features and improvements</p>
                        <span class="text-blue-600 text-sm font-medium">{{ $categories['platform'] }} posts</span>
                    </div>

                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 text-center hover:shadow-md transition duration-200">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-gray-900 mb-2">Security</h3>
                        <p class="text-gray-600 text-sm mb-3">Security insights and best practices</p>
                        <span class="text-blue-600 text-sm font-medium">{{ $categories['security'] }} posts</span>
                    </div>

                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 text-center hover:shadow-md transition duration-200">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-gray-900 mb-2">Developer Resources</h3>
                        <p class="text-gray-600 text-sm mb-3">API guides and integration tutorials</p>
                        <span class="text-blue-600 text-sm font-medium">{{ $categories['developer'] }} posts</span>
                    </div>

                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 text-center hover:shadow-md transition duration-200">
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-gray-900 mb-2">Industry Insights</h3>
                        <p class="text-gray-600 text-sm mb-3">Market trends and financial analysis</p>
                        <span class="text-blue-600 text-sm font-medium">{{ $categories['industry'] }} posts</span>
                    </div>

                    <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6 text-center hover:shadow-md transition duration-200">
                        <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mx-auto mb-4">
                            <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                            </svg>
                        </div>
                        <h3 class="font-semibold text-gray-900 mb-2">Compliance</h3>
                        <p class="text-gray-600 text-sm mb-3">Regulatory updates and guidelines</p>
                        <span class="text-blue-600 text-sm font-medium">{{ $categories['compliance'] }} posts</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Posts -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
            <div class="mb-12">
                <h2 class="text-3xl font-bold text-gray-900">Recent Posts</h2>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                @foreach($recentPosts as $post)
                <article class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition duration-200">
                    <div class="h-48 bg-gradient-to-br from-{{ $post->gradient_from }} to-{{ $post->gradient_to }} flex items-center justify-center">
                        @if($post->icon_svg)
                            {!! $post->icon_svg !!}
                        @else
                            <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                            </svg>
                        @endif
                    </div>
                    <div class="p-6">
                        <div class="flex items-center mb-3">
                            <span class="bg-{{ $post->category_badge_color }}-100 text-{{ $post->category_badge_color }}-800 text-xs font-semibold px-2.5 py-0.5 rounded capitalize">{{ $post->category }}</span>
                            <span class="text-gray-500 text-sm ml-3">{{ $post->published_at->format('M j, Y') }}</span>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-3">
                            <a href="{{ route('blog.show', $post->slug) }}" class="hover:text-blue-600 transition">{{ $post->title }}</a>
                        </h3>
                        <p class="text-gray-600 mb-4 line-clamp-3">{{ $post->excerpt }}</p>
                        <div class="flex items-center justify-between">
                            <div class="flex items-center">
                                <div class="w-8 h-8 bg-gray-300 rounded-full flex items-center justify-center mr-3">
                                    <span class="text-gray-600 font-semibold text-xs">{{ $post->author_initials }}</span>
                                </div>
                                <span class="text-gray-700 text-sm">{{ $post->author_name }}</span>
                            </div>
                            <span class="text-blue-600 text-sm font-medium">{{ $post->reading_time }} min read</span>
                        </div>
                    </div>
                </article>
                @endforeach
            </div>

            @if($recentPosts->count() == 6)
            <!-- Load More -->
            <div class="text-center mt-12">
                <a href="{{ route('blog') }}?page=2" class="bg-blue-600 text-white px-8 py-3 rounded-lg font-semibold hover:bg-blue-700 transition duration-200 inline-block">
                    View All Posts
                </a>
            </div>
            @endif
        </div>

        <!-- Newsletter Signup -->
        <div class="bg-blue-900 py-16">
            <div class="max-w-4xl mx-auto text-center px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-bold text-white mb-4">Stay Updated</h2>
                <p class="text-xl text-blue-100 mb-8">Get the latest insights and updates delivered to your inbox.</p>
                <form id="newsletter-form" class="max-w-md mx-auto" onsubmit="handleSubscribe(event)">
                    @csrf
                    <div class="flex">
                        <input type="email" name="email" id="newsletter-email" placeholder="Enter your email" required
                               class="flex-1 px-4 py-3 rounded-l-lg border-0 focus:ring-2 focus:ring-blue-500">
                        <button type="submit" id="subscribe-button" 
                                class="bg-blue-600 text-white px-6 py-3 rounded-r-lg font-semibold hover:bg-blue-700 transition duration-200">
                            Subscribe
                        </button>
                    </div>
                    <p class="text-blue-200 text-sm mt-3">We respect your privacy. Unsubscribe at any time.</p>
                    <div id="newsletter-message" class="mt-3 text-sm hidden"></div>
                </form>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        function handleSubscribe(event) {
            event.preventDefault();
            
            const form = document.getElementById('newsletter-form');
            const email = document.getElementById('newsletter-email').value;
            const button = document.getElementById('subscribe-button');
            const messageDiv = document.getElementById('newsletter-message');
            
            // Disable button and show loading state
            button.disabled = true;
            button.textContent = 'Subscribing...';
            messageDiv.classList.add('hidden');
            
            // Send subscription request
            fetch('{{ route('blog.subscribe') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ email: email })
            })
            .then(response => response.json())
            .then(data => {
                messageDiv.classList.remove('hidden');
                
                if (data.success) {
                    messageDiv.classList.remove('text-red-300');
                    messageDiv.classList.add('text-green-300');
                    messageDiv.textContent = data.message;
                    form.reset();
                } else {
                    messageDiv.classList.remove('text-green-300');
                    messageDiv.classList.add('text-red-300');
                    messageDiv.textContent = data.message || 'Subscription failed. Please try again.';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                messageDiv.classList.remove('hidden', 'text-green-300');
                messageDiv.classList.add('text-red-300');
                messageDiv.textContent = 'An error occurred. Please try again later.';
            })
            .finally(() => {
                button.disabled = false;
                button.textContent = 'Subscribe';
            });
        }
    </script>
    @endpush
</x-guest-layout>