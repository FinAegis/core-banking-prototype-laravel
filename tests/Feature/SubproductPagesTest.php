<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubproductPagesTest extends TestCase
{
/**
 * Test that all subproduct pages load without errors.
 */ #[Test]
    public function test_all_subproduct_pages_load_successfully(): void
    {
        $subproducts = [
            '/subproducts/exchange' => [
                'title'       => 'FinAegis Exchange',
                'description' => 'Exchange module demo: order matching',
                'status'      => 'Sandbox Demo',
            ],
            '/subproducts/lending' => [
                'title'       => 'FinAegis Lending',
                'description' => 'P2P lending module demo',
                'status'      => 'Sandbox Demo',
            ],
            '/subproducts/stablecoins' => [
                'title'       => 'FinAegis Stablecoins',
                'description' => 'Stablecoin module: model issuance',
                'status'      => 'Sandbox Demo',
            ],
            '/subproducts/treasury' => [
                'title'       => 'FinAegis Treasury',
                'description' => 'Treasury module demo: cash management',
                'status'      => 'Sandbox Demo',
            ],
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
 * Test that exchange page has correct links.
 */ #[Test]
    public function test_exchange_page_has_correct_links(): void
    {
        $response = $this->get('/subproducts/exchange');

        $response->assertStatus(200);
        // For non-authenticated users, should see "Sign In to the Sandbox"
        $response->assertSee('Sign In to the Sandbox');
        $response->assertSee('/login'); // Link to login page
        $response->assertSee('/gcu'); // Link to GCU page
    }

/**
 * Test that lending page has correct links.
 */ #[Test]
    public function test_lending_page_has_correct_links(): void
    {
        $response = $this->get('/subproducts/lending');

        $response->assertStatus(200);
        $response->assertSee('Explore the Lending Demo');
        $response->assertSee('/lending'); // Link to lending page
        $response->assertSee('/gcu'); // Link to GCU page
    }

/**
 * Test that stablecoins page has correct links.
 */ #[Test]
    public function test_stablecoins_page_has_correct_links(): void
    {
        $response = $this->get('/subproducts/stablecoins');

        $response->assertStatus(200);
        $response->assertSee('Explore the Sandbox');
        // Should now use dashboard route instead of stablecoins.index
        $response->assertSee('/dashboard'); // Link to dashboard
        $response->assertSee('/gcu'); // Link to GCU page
    }

/**
 * Test that treasury page doesn't have broken links.
 */ #[Test]
    public function test_treasury_page_has_no_broken_links(): void
    {
        $response = $this->get('/subproducts/treasury');

        $response->assertStatus(200);
        $response->assertSee('Sandbox Demo'); // Status badge (was "Available in Sandbox" pre-regulatory-copy pass)
        $response->assertSee('/gcu'); // Link to GCU page (shared public nav)
    }

/**
 * Test that all subproduct pages are linked from homepage.
 */ #[Test]
    public function test_homepage_links_to_all_subproducts(): void
    {
        $this->markTestSkipped('Homepage no longer links to subproduct pages directly; uses module cards instead');
    }
}
