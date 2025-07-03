<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\Email\SubscriberEmailService;
use App\Models\Subscriber;

class BlogController extends Controller
{
    /**
     * Display the blog index page
     */
    public function index()
    {
        $featuredPost = \App\Models\BlogPost::published()
            ->featured()
            ->latest('published_at')
            ->first();
            
        $recentPosts = \App\Models\BlogPost::published()
            ->where('is_featured', false)
            ->latest('published_at')
            ->take(6)
            ->get();
            
        $categories = [
            'platform' => \App\Models\BlogPost::published()->category('platform')->count(),
            'security' => \App\Models\BlogPost::published()->category('security')->count(),
            'developer' => \App\Models\BlogPost::published()->category('developer')->count(),
            'industry' => \App\Models\BlogPost::published()->category('industry')->count(),
            'compliance' => \App\Models\BlogPost::published()->category('compliance')->count(),
        ];
        
        return view('blog.index', compact('featuredPost', 'recentPosts', 'categories'));
    }
    
    /**
     * Display a single blog post
     */
    public function show($slug)
    {
        $post = \App\Models\BlogPost::published()
            ->where('slug', $slug)
            ->firstOrFail();
            
        $relatedPosts = \App\Models\BlogPost::published()
            ->where('category', $post->category)
            ->where('id', '!=', $post->id)
            ->latest('published_at')
            ->take(3)
            ->get();
            
        return view('blog.show', compact('post', 'relatedPosts'));
    }
    
    /**
     * Subscribe email to newsletter (now using internal subscriber system)
     */
    public function subscribe(Request $request, SubscriberEmailService $emailService)
    {
        $validated = $request->validate([
            'email' => 'required|email'
        ]);
        
        try {
            // Use internal subscriber system
            $emailService->subscribe(
                $validated['email'],
                Subscriber::SOURCE_BLOG,
                ['newsletter', 'blog_updates'],
                $request->ip(),
                $request->userAgent()
            );
            
            // Also sync with Mailchimp if configured
            $this->syncWithMailchimp($validated['email']);
            
            return response()->json([
                'success' => true,
                'message' => 'Thank you for subscribing! Check your email for confirmation.'
            ]);
            
        } catch (\Exception $e) {
            Log::error('Subscription error', [
                'error' => $e->getMessage(),
                'email' => $validated['email']
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'An error occurred. Please try again later.'
            ], 500);
        }
    }
    
    /**
     * Sync with Mailchimp if configured
     */
    private function syncWithMailchimp($email)
    {
        $apiKey = config('services.mailchimp.api_key');
        $listId = config('services.mailchimp.list_id');
        
        if (!$apiKey || !$listId) {
            return; // Mailchimp not configured, skip
        }
        
        $dataCenter = $this->getDataCenterFromApiKey($apiKey);
        
        try {
            Http::withBasicAuth('apikey', $apiKey)
                ->post("https://{$dataCenter}.api.mailchimp.com/3.0/lists/{$listId}/members", [
                    'email_address' => $email,
                    'status' => 'subscribed',
                    'tags' => ['blog_subscriber', 'finaegis_demo']
                ]);
        } catch (\Exception $e) {
            Log::warning('Mailchimp sync failed (non-critical)', [
                'error' => $e->getMessage(),
                'email' => $email
            ]);
        }
    }
    
    /**
     * Extract data center from Mailchimp API key
     */
    private function getDataCenterFromApiKey($apiKey)
    {
        if (!$apiKey) {
            return 'us1';
        }
        
        $parts = explode('-', $apiKey);
        return isset($parts[1]) ? $parts[1] : 'us1';
    }
}