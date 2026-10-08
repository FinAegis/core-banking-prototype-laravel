<?php

namespace App\Http\Controllers;

use App\Domain\Newsletter\Exceptions\SubscriptionThrottledException;
use App\Domain\Newsletter\Models\Subscriber;
use App\Domain\Newsletter\Services\MailchimpSyncService;
use App\Domain\Newsletter\Services\SubscriberEmailService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Blog',
    description: 'Public blog and newsletter'
)]
class BlogController extends Controller
{
        #[OA\Get(
            path: '/blog',
            operationId: 'blogIndex',
            tags: ['Blog'],
            summary: 'List blog posts',
            description: 'Returns the blog listing page'
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
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

        #[OA\Get(
            path: '/blog/{slug}',
            operationId: 'blogShow',
            tags: ['Blog'],
            summary: 'Show blog post',
            description: 'Returns a specific blog post by slug',
            parameters: [
            new OA\Parameter(name: 'slug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
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

        #[OA\Post(
            path: '/blog/subscribe',
            operationId: 'blogSubscribe',
            tags: ['Blog'],
            summary: 'Subscribe to newsletter',
            description: 'Subscribes an email to the blog newsletter'
        )]
    #[OA\Response(
        response: 201,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    public function subscribe(Request $request, SubscriberEmailService $emailService)
    {
        $validated = $request->validate(
            [
                'email' => 'required|email',
            ]
        );

        try {
            // Double opt-in: the address stays pending until the emailed
            // link is opened. Mailchimp is updated only at that point
            // (SubscriberController::confirm()).
            $emailService->subscribe(
                $validated['email'],
                Subscriber::SOURCE_BLOG,
                ['newsletter', MailchimpSyncService::BLOG_TAG],
                $request->ip(),
                $request->userAgent()
            );

            return response()->json(
                [
                    'success' => true,
                    'message' => 'Almost done! Check your inbox and confirm your email address to subscribe.',
                ]
            );
        } catch (SubscriptionThrottledException $e) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Too many requests. Please try again later.',
                ],
                429,
                ['Retry-After' => (string) max(1, $e->retryAfterSeconds)]
            );
        } catch (Exception $e) {
            Log::error(
                'Subscription error',
                [
                    'error' => $e->getMessage(),
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
}
