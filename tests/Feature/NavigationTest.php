<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->withPersonalTeam()->create();
    }

    /** @test */
    public function all_navigation_links_are_accessible_for_authenticated_users()
    {
        $response = $this->actingAs($this->user)->get('/dashboard');
        $response->assertStatus(200);

        // Test main navigation links
        $navigationLinks = [
            '/dashboard' => 200,
            '/wallet' => 200,
            '/accounts' => 200,
            '/transactions' => 302, // Redirects to /wallet/transactions
            '/transfers' => 302, // Redirects to /wallet/transfers
            '/exchange' => 302, // Redirects to /wallet/exchange
            '/user/profile' => 200, // Jetstream profile route
            '/gcu/voting' => 200,
            '/fraud/alerts' => 200,
            '/risk/analysis' => 200,
            '/monitoring/transactions' => 200,
            '/cgo/invest' => 200,
        ];

        foreach ($navigationLinks as $url => $expectedStatus) {
            $response = $this->actingAs($this->user)->get($url);
            if ($response->status() !== $expectedStatus) {
                dump("Failed to access: $url - got status {$response->status()} instead of $expectedStatus");
                if ($response->status() === 302) {
                    dump("Redirected to: " . $response->headers->get('Location'));
                }
            }
            $response->assertStatus($expectedStatus, "Failed to access: $url");
        }
    }

    /** @test */
    public function public_pages_are_accessible_without_authentication()
    {
        $publicPages = [
            '/' => 200,
            '/about' => 200,
            '/platform' => 200,
            '/gcu' => 200,
            '/pricing' => 200,
            '/security' => 200,
            '/compliance' => 200,
            '/partners' => 200,
            '/blog' => 200,
            '/support/contact' => 200,
            '/developers' => 200,
            '/status' => 200,
            '/cgo' => 200,
            '/legal/terms' => 200,
            '/legal/privacy' => 200,
            '/legal/cookies' => 200,
        ];

        foreach ($publicPages as $url => $expectedStatus) {
            $response = $this->get($url);
            if ($response->status() !== $expectedStatus) {
                dump("Failed to access public page: $url - got status {$response->status()} instead of $expectedStatus");
            }
            $response->assertStatus($expectedStatus, "Failed to access public page: $url");
        }
    }

    /** @test */
    public function wallet_sub_pages_are_accessible()
    {
        $walletPages = [
            '/wallet' => 200,
            '/wallet/deposit' => 200,
            '/wallet/deposit/card' => 302, // May redirect if no payment methods
            '/wallet/deposit/bank' => 200,
            '/wallet/deposit/paysera' => 200,
            '/wallet/deposit/openbanking' => 200,
            '/wallet/withdraw' => 200,
            '/wallet/withdraw/bank' => 302, // May redirect if no bank accounts
            '/wallet/transfer' => 200,
            '/wallet/convert' => 200,
            '/wallet/transactions' => 200,
        ];

        foreach ($walletPages as $url => $expectedStatus) {
            $response = $this->actingAs($this->user)->get($url);
            if ($response->status() !== $expectedStatus) {
                dump("Failed to access wallet page: $url - got status {$response->status()} instead of $expectedStatus");
            }
            $response->assertStatus($expectedStatus, "Failed to access wallet page: $url");
        }
    }

    /** @test */
    public function admin_panel_redirects_to_login_for_regular_users()
    {
        $response = $this->actingAs($this->user)->get('/admin');
        $response->assertStatus(403); // Forbidden for non-admin users
    }

    /** @test */
    public function api_documentation_is_accessible()
    {
        $response = $this->get('/api/documentation');
        $response->assertStatus(200);
    }

    /** @test */
    public function gcu_voting_pages_render_correctly()
    {
        // Test main voting page
        $response = $this->actingAs($this->user)->get('/gcu/voting');
        $response->assertStatus(200);
        $response->assertSee('GCU Composition Voting');
        
        // The page should render without errors even if no proposals exist
        $response->assertViewHas('activeProposals');
        $response->assertViewHas('upcomingProposals');
        $response->assertViewHas('pastProposals');
        $response->assertViewHas('gcuBalance');
    }

    /** @test */
    public function fraud_alerts_page_is_accessible()
    {
        $response = $this->actingAs($this->user)->get('/fraud/alerts');
        $response->assertStatus(200);
        $response->assertSee('Fraud Alerts');
    }

    /** @test */
    public function protected_routes_redirect_to_login_when_not_authenticated()
    {
        $protectedRoutes = [
            '/dashboard',
            '/wallet',
            '/accounts',
            '/transactions',
            '/transfers',
            '/user/profile',
            '/fraud/alerts',
            '/cgo/invest',
        ];

        foreach ($protectedRoutes as $url) {
            $response = $this->get($url);
            $response->assertRedirect('/login', "Route $url should redirect to login");
        }
    }

    /** @test */
    public function user_dropdown_menu_links_work()
    {
        $response = $this->actingAs($this->user)->get('/dashboard');
        $response->assertStatus(200);

        // Test profile links
        $response = $this->actingAs($this->user)->get('/user/profile');
        $response->assertStatus(200);

        // Test logout functionality
        $response = $this->actingAs($this->user)->post('/logout');
        $response->assertRedirect('/');
    }
}