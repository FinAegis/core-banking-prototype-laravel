<?php

namespace App\Http\Controllers;

use App\Domain\Newsletter\Models\Subscriber;
use App\Domain\Newsletter\Services\SubscriberEmailService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BlogController extends Controller
{
    /**
     * Display the blog index page.
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
            'platform'   => \App\Models\BlogPost::published()->category('platform')->count(),
            'security'   => \App\Models\BlogPost::published()->category('security')->count(),
            'developer'  => \App\Models\BlogPost::published()->category('developer')->count(),
            'industry'   => \App\Models\BlogPost::published()->category('industry')->count(),
            'compliance' => \App\Models\BlogPost::published()->category('compliance')->count(),
        ];

        return view('blog.index', compact('featuredPost', 'recentPosts', 'categories'));
    }

    /**
     * Display a single blog post.
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
     * Subscribe email to newsletter (now using internal subscriber system).
     */
    public function subscribe(Request $request, SubscriberEmailService $emailService)
    {
        $validated = $request->validate(
            [
                'email' => 'required|email',
            ]
        );

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
            $mailchimpConfigured = $this->syncWithMailchimp($validated['email']);

            $message = $mailchimpConfigured
                ? 'Thank you for subscribing! Check your email for confirmation.'
                : 'Thank you for subscribing! (Note: Mailchimp integration not configured)';

            return response()->json(
                [
                    'success' => true,
                    'message' => $message,
                ]
            );
        } catch (Exception $e) {
            // Check if it's a member exists error
            if ($e->getMessage() === 'member_exists') {
                return response()->json(
                    [
                        'success' => true,
                        'message' => 'You are already subscribed to our newsletter.',
                    ]
                );
            }

            // Check if it's a Mailchimp API error
            if ($e->getMessage() === 'mailchimp_api_error') {
                return response()->json(
                    [
                        'success' => false,
                        'message' => 'Failed to subscribe. Please try again later.',
                    ],
                    422
                );
            }

            Log::error(
                'Subscription error',
                [
                    'error' => $e->getMessage(),
                    'email' => $validated['email'],
                ]
            );

            return response()->json(
                [
                    'success' => false,
                    'message' => 'An error occurred. Please try again later.',
                ],
                500
            );
        }
    }

    /**
     * Sync with Mailchimp if configured.
     */
    private function syncWithMailchimp($email)
    {
        $apiKey = config('services.mailchimp.api_key');
        $listId = config('services.mailchimp.list_id');

        if (! $apiKey || ! $listId) {
            return false; // Mailchimp not configured, skip
        }

        $dataCenter = $this->getDataCenterFromApiKey($apiKey);

        try {
            $response = Http::withBasicAuth('apikey', $apiKey)
                ->post(
                    "https://{$dataCenter}.api.mailchimp.com/3.0/lists/{$listId}/members",
                    [
                        'email_address' => $email,
                        'status'        => 'subscribed',
                        'tags'          => ['blog_subscriber', 'finaegis_demo'],
                    ]
                );

            // Check if the response indicates member already exists
            if ($response->status() === 400 && $response->json('title') === 'Member Exists') {
                throw new Exception('member_exists');
            }

            $response->throw(); // Throw for other errors
        } catch (Exception $e) {
            if ($e->getMessage() === 'member_exists') {
                throw $e; // Re-throw to be handled by subscribe method
            }

            // Check if it's an API authentication error
            if ($e instanceof \Illuminate\Http\Client\RequestException) {
                $response = $e->response;
                if ($response && in_array($response->status(), [401, 403])) {
                    throw new Exception('mailchimp_api_error');
                }
            }

            Log::warning(
                'Mailchimp sync failed (non-critical)',
                [
                    'error' => $e->getMessage(),
                    'email' => $email,
                ]
            );
        }

        return true; // Mailchimp is configured
    }

    /**
     * Extract data center from Mailchimp API key.
     */
    private function getDataCenterFromApiKey($apiKey)
    {
        if (! $apiKey) {
            return 'us1';
        }

        $parts = explode('-', $apiKey);

        return isset($parts[1]) ? $parts[1] : 'us1';
    }
}
