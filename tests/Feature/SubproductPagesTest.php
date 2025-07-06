<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SubproductPagesTest extends TestCase
{
    /**
     * Test that all subproduct pages load without errors
     */
    public function test_all_subproduct_pages_load_successfully(): void
    {
        $subproducts = [
            '/subproducts/exchange' => [
                'title' => 'FinAegis Exchange',
                'description' => 'Professional trading platform',
                'status' => 'Now Live'
            ],
            '/subproducts/lending' => [
                'title' => 'FinAegis Lending',
                'description' => 'P2P lending marketplace', 
                'status' => 'Now Live'
            ],
            '/subproducts/stablecoins' => [
                'title' => 'FinAegis Stablecoins',
                'description' => 'EUR-pegged digital currency',
                'status' => 'Now Live'
            ],
            '/subproducts/treasury' => [
                'title' => 'FinAegis Treasury',
                'description' => 'Multi-bank cash management',
                'status' => 'Coming Soon'
            ]
        ];

        foreach ($subproducts as $url => $expected) {
            $response = $this->get($url);
            
            $response->assertStatus(200);
            $response->assertSee($expected['title']);
            $response->assertSee($expected['description']);
            $response->assertSee($expected['status']);
            
            // Ensure no route errors
            $response->assertDontSee('Route [');
            $response->assertDontSee('not defined');
        }
    }

    /**
     * Test that exchange page has correct links
     */
    public function test_exchange_page_has_correct_links(): void
    {
        $response = $this->get('/subproducts/exchange');
        
        $response->assertStatus(200);
        // For non-authenticated users, should see "Sign In to Trade"
        $response->assertSee('Sign In to Trade');
        $response->assertSee('/login'); // Link to login page
        $response->assertSee('/gcu'); // Link to GCU page
    }

    /**
     * Test that lending page has correct links
     */
    public function test_lending_page_has_correct_links(): void
    {
        $response = $this->get('/subproducts/lending');
        
        $response->assertStatus(200);
        $response->assertSee('Start Lending or Borrowing');
        $response->assertSee('/lending'); // Link to lending page
        $response->assertSee('/gcu'); // Link to GCU page
    }

    /**
     * Test that stablecoins page has correct links
     */
    public function test_stablecoins_page_has_correct_links(): void
    {
        $response = $this->get('/subproducts/stablecoins');
        
        $response->assertStatus(200);
        $response->assertSee('Get Started with EURS');
        // Should now use dashboard route instead of stablecoins.index
        $response->assertSee('/dashboard'); // Link to dashboard
        $response->assertSee('/gcu'); // Link to GCU page
    }

    /**
     * Test that treasury page doesn't have broken links
     */
    public function test_treasury_page_has_no_broken_links(): void
    {
        $response = $this->get('/subproducts/treasury');
        
        $response->assertStatus(200);
        $response->assertSee('Coming Soon');
        $response->assertSee('/gcu'); // Link to GCU page
        
        // Treasury doesn't have an action button, just "Coming Soon"
        $response->assertDontSee('/treasury'); // No treasury route
    }

    /**
     * Test that all subproduct pages are linked from homepage
     */
    public function test_homepage_links_to_all_subproducts(): void
    {
        $response = $this->get('/');
        
        $response->assertStatus(200);
        
        // Check that all subproduct links exist on homepage
        $response->assertSee('FinAegis Exchange');
        $response->assertSee('FinAegis Lending');
        $response->assertSee('FinAegis Stablecoins');
        $response->assertSee('FinAegis Treasury');
        
        // Check the routes
        $response->assertSee('/subproducts/exchange');
        $response->assertSee('/subproducts/lending');
        $response->assertSee('/subproducts/stablecoins');
        $response->assertSee('/subproducts/treasury');
    }
}