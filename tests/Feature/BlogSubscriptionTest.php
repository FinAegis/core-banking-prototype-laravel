<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;

class BlogSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_can_view_blog_page()
    {
        $response = $this->get('/blog');
        
        $response->assertOk();
        $response->assertSee('FinAegis Blog');
        $response->assertSee('Stay Updated');
        $response->assertSee('Subscribe');
    }
    
    #[Test]
    public function user_can_subscribe_to_newsletter()
    {
        // Mock successful Mailchimp response
        Http::fake([
            '*.api.mailchimp.com/*' => Http::response([
                'id' => 'abc123',
                'email_address' => 'test@example.com',
                'status' => 'subscribed'
            ], 200)
        ]);
        
        // Set test config
        config(['services.mailchimp.api_key' => 'test-key-us1']);
        config(['services.mailchimp.list_id' => 'test-list']);
        
        $response = $this->postJson('/blog/subscribe', [
            'email' => 'test@example.com'
        ]);
        
        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Thank you for subscribing! Check your email for confirmation.'
        ]);
    }
    
    #[Test]
    public function handles_already_subscribed_email()
    {
        // Mock "already subscribed" response
        Http::fake([
            '*.api.mailchimp.com/*' => Http::response([
                'type' => 'http://developer.mailchimp.com/documentation/mailchimp/guides/error-glossary/',
                'title' => 'Member Exists',
                'status' => 400,
                'detail' => 'test@example.com is already a list member'
            ], 400)
        ]);
        
        config(['services.mailchimp.api_key' => 'test-key-us1']);
        config(['services.mailchimp.list_id' => 'test-list']);
        
        $response = $this->postJson('/blog/subscribe', [
            'email' => 'test@example.com'
        ]);
        
        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'You are already subscribed to our newsletter.'
        ]);
    }
    
    #[Test]
    public function validates_email_format()
    {
        $response = $this->postJson('/blog/subscribe', [
            'email' => 'invalid-email'
        ]);
        
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['email']);
    }
    
    #[Test]
    public function handles_missing_mailchimp_configuration()
    {
        // Clear Mailchimp config
        config(['services.mailchimp.api_key' => null]);
        config(['services.mailchimp.list_id' => null]);
        
        $response = $this->postJson('/blog/subscribe', [
            'email' => 'test@example.com'
        ]);
        
        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Thank you for subscribing! (Note: Mailchimp integration not configured)'
        ]);
    }
    
    #[Test]
    public function handles_mailchimp_api_error()
    {
        // Mock error response
        Http::fake([
            '*.api.mailchimp.com/*' => Http::response([
                'type' => 'http://developer.mailchimp.com/documentation/mailchimp/guides/error-glossary/',
                'title' => 'API Key Invalid',
                'status' => 401,
                'detail' => 'Your API key may be invalid'
            ], 401)
        ]);
        
        config(['services.mailchimp.api_key' => 'invalid-key-us1']);
        config(['services.mailchimp.list_id' => 'test-list']);
        
        $response = $this->postJson('/blog/subscribe', [
            'email' => 'test@example.com'
        ]);
        
        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'Failed to subscribe. Please try again later.'
        ]);
    }
}