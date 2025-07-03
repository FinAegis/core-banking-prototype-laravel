<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BlogController extends Controller
{
    /**
     * Display the blog index page
     */
    public function index()
    {
        return view('blog.index');
    }
    
    /**
     * Subscribe email to Mailchimp list
     */
    public function subscribe(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email'
        ]);
        
        $apiKey = config('services.mailchimp.api_key');
        $listId = config('services.mailchimp.list_id');
        $dataCenter = $this->getDataCenterFromApiKey($apiKey);
        
        if (!$apiKey || !$listId) {
            Log::warning('Mailchimp not configured', [
                'api_key_present' => !empty($apiKey),
                'list_id_present' => !empty($listId)
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Thank you for subscribing! (Note: Mailchimp integration not configured)'
            ]);
        }
        
        try {
            $response = Http::withBasicAuth('apikey', $apiKey)
                ->post("https://{$dataCenter}.api.mailchimp.com/3.0/lists/{$listId}/members", [
                    'email_address' => $validated['email'],
                    'status' => 'subscribed',
                    'tags' => ['blog_subscriber', 'finaegis_demo']
                ]);
            
            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Thank you for subscribing! Check your email for confirmation.'
                ]);
            }
            
            $error = $response->json();
            
            // Handle "already subscribed" case
            if ($response->status() === 400 && str_contains($error['title'] ?? '', 'already a list member')) {
                return response()->json([
                    'success' => true,
                    'message' => 'You are already subscribed to our newsletter.'
                ]);
            }
            
            Log::error('Mailchimp subscription failed', [
                'status' => $response->status(),
                'error' => $error
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to subscribe. Please try again later.'
            ], 422);
            
        } catch (\Exception $e) {
            Log::error('Mailchimp subscription error', [
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