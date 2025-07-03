<x-guest-layout>
    <div class="bg-white">
        <!-- Header -->
        <div class="bg-gradient-to-r from-{{ $post->gradient_from }} to-{{ $post->gradient_to }}">
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
                <div class="text-center">
                    <div class="mb-4">
                        <span class="bg-white/20 text-white text-sm font-semibold px-3 py-1 rounded-full capitalize">
                            {{ $post->category }}
                        </span>
                    </div>
                    <h1 class="text-4xl font-bold text-white sm:text-5xl lg:text-6xl">
                        {{ $post->title }}
                    </h1>
                    <div class="mt-6 flex items-center justify-center space-x-4 text-white/90">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center mr-3">
                                <span class="text-white font-semibold text-sm">{{ $post->author_initials }}</span>
                            </div>
                            <div class="text-left">
                                <p class="font-semibold">{{ $post->author_name }}</p>
                                <p class="text-sm text-white/80">{{ $post->author_role }}</p>
                            </div>
                        </div>
                        <span class="text-white/60">•</span>
                        <span>{{ $post->published_at->format('F j, Y') }}</span>
                        <span class="text-white/60">•</span>
                        <span>{{ $post->reading_time }} min read</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <div class="prose prose-lg max-w-none">
                {!! \Illuminate\Support\Str::markdown($post->content) !!}
            </div>

            <!-- Share buttons -->
            <div class="mt-12 pt-8 border-t border-gray-200">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">Share this article</h3>
                <div class="flex space-x-4">
                    <a href="https://twitter.com/intent/tweet?text={{ urlencode($post->title) }}&url={{ urlencode(url()->current()) }}" 
                       target="_blank"
                       class="bg-blue-400 text-white px-4 py-2 rounded-lg hover:bg-blue-500 transition">
                        Share on Twitter
                    </a>
                    <a href="https://www.linkedin.com/sharing/share-offsite/?url={{ urlencode(url()->current()) }}" 
                       target="_blank"
                       class="bg-blue-700 text-white px-4 py-2 rounded-lg hover:bg-blue-800 transition">
                        Share on LinkedIn
                    </a>
                </div>
            </div>
        </div>

        <!-- Related Posts -->
        @if($relatedPosts->count() > 0)
        <div class="bg-gray-50 py-16">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-bold text-gray-900 mb-8">Related Articles</h2>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    @foreach($relatedPosts as $relatedPost)
                    <article class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden hover:shadow-md transition duration-200">
                        <div class="h-48 bg-gradient-to-br from-{{ $relatedPost->gradient_from }} to-{{ $relatedPost->gradient_to }} flex items-center justify-center">
                            @if($relatedPost->icon_svg)
                                {!! $relatedPost->icon_svg !!}
                            @else
                                <svg class="w-12 h-12 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                                </svg>
                            @endif
                        </div>
                        <div class="p-6">
                            <div class="flex items-center mb-3">
                                <span class="bg-{{ $relatedPost->category_badge_color }}-100 text-{{ $relatedPost->category_badge_color }}-800 text-xs font-semibold px-2.5 py-0.5 rounded capitalize">{{ $relatedPost->category }}</span>
                                <span class="text-gray-500 text-sm ml-3">{{ $relatedPost->published_at->format('M j, Y') }}</span>
                            </div>
                            <h3 class="text-xl font-bold text-gray-900 mb-3">
                                <a href="{{ route('blog.show', $relatedPost->slug) }}" class="hover:text-blue-600 transition">{{ $relatedPost->title }}</a>
                            </h3>
                            <p class="text-gray-600 mb-4 line-clamp-3">{{ $relatedPost->excerpt }}</p>
                            <a href="{{ route('blog.show', $relatedPost->slug) }}" class="text-blue-600 font-medium hover:text-blue-700 transition">
                                Read more →
                            </a>
                        </div>
                    </article>
                    @endforeach
                </div>
            </div>
        </div>
        @endif

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