<?php

namespace Tests\Browser;

use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use App\Models\User;
use App\Models\Team;

class CriticalPathsTest extends DuskTestCase
{
    /**
     * Test all public pages are accessible
     */
    public function test_public_pages_are_accessible(): void
    {
        $this->browse(function (Browser $browser) {
            $publicRoutes = [
                '/' => 'FinAegis',
                '/about' => 'About FinAegis',
                '/platform' => 'Platform',
                '/gcu' => 'Global Currency Unit',
                '/features' => 'Features',
                '/pricing' => 'Pricing',
                '/security' => 'Security',
                '/compliance' => 'Compliance',
                '/developers' => 'Developers',
                '/support' => 'Support',
                '/blog' => 'Blog',
                '/partners' => 'Partners',
                '/cgo' => 'CGO',
                '/status' => 'Status',
            ];

            foreach ($publicRoutes as $route => $expectedText) {
                $browser->visit($route)
                    ->assertSee($expectedText)
                    ->assertDontSee('404')
                    ->assertDontSee('500')
                    ->assertDontSee('Route')
                    ->assertDontSee('not defined');
            }
        });
    }

    /**
     * Test registration process
     */
    public function test_user_can_register(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/register')
                ->type('name', 'Test User')
                ->type('email', 'test' . time() . '@example.com')
                ->type('password', 'password123')
                ->type('password_confirmation', 'password123')
                ->check('terms')
                ->press('Register')
                ->waitForText('Dashboard', 10)
                ->assertPathIs('/dashboard')
                ->assertAuthenticated();
        });
    }

    /**
     * Test login process
     */
    public function test_user_can_login(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->visit('/login')
                ->type('email', $user->email)
                ->type('password', 'password')
                ->press('Log in')
                ->waitForText('Dashboard', 10)
                ->assertPathIs('/dashboard')
                ->assertAuthenticated();
        });
    }

    /**
     * Test authenticated navigation menu
     */
    public function test_authenticated_navigation_menu(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->assertSee('Dashboard')
                ->assertSee('Wallet')
                ->assertSee('Banking')
                ->assertDontSee('Route')
                ->assertDontSee('not defined');

            // Test wallet dropdown menu
            $browser->click('a[href="' . route('wallet.index') . '"]')
                ->waitForText('GCU Wallet', 10)
                ->assertPathIs('/wallet');

            // Test Banking dropdown (if visible)
            $browser->visit('/dashboard')
                ->click('button[id*="menu-button"]')
                ->waitFor('a[href="' . route('wallet.transactions') . '"]')
                ->click('a[href="' . route('wallet.transactions') . '"]')
                ->waitForText('Transaction History', 10);
        });
    }

    /**
     * Test wallet operations
     */
    public function test_wallet_operations(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/wallet')
                ->assertSee('GCU Wallet')
                ->assertSee('Deposit')
                ->assertSee('Withdraw')
                ->assertSee('Transfer')
                ->assertSee('Convert');

            // Test deposit page
            $browser->click('a[href="' . route('wallet.deposit') . '"]')
                ->waitForText('Choose Deposit Method', 10)
                ->assertSee('Bank Transfer')
                ->assertSee('Card Payment');

            // Test withdraw page
            $browser->visit('/wallet')
                ->click('a[href="' . route('wallet.withdraw') . '"]')
                ->waitForText('Choose Withdrawal Method', 10);

            // Test transfer page
            $browser->visit('/wallet')
                ->click('a[href="' . route('wallet.transfer') . '"]')
                ->waitForText('Send Money', 10);

            // Test convert page
            $browser->visit('/wallet')
                ->click('a[href="' . route('wallet.convert') . '"]')
                ->waitForText('Convert Currency', 10);
        });
    }

    /**
     * Test quick actions from dashboard
     */
    public function test_dashboard_quick_actions(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->visit('/dashboard')
                ->waitForText('Quick Actions')
                ->assertSee('Deposit')
                ->assertSee('Withdraw')
                ->assertSee('Transfer')
                ->assertSee('Convert')
                ->assertSee('Vote');

            // Test quick deposit link
            $browser->click('a[href="' . route('wallet.deposit') . '"]')
                ->waitForText('Choose Deposit Method', 10)
                ->assertPathIs('/wallet/deposit');

            // Test quick vote link
            $browser->visit('/dashboard')
                ->click('a[href="' . route('gcu.voting.index') . '"]')
                ->waitForText('GCU Governance', 10);
        });
    }

    /**
     * Test responsive navigation menu
     */
    public function test_responsive_navigation_menu(): void
    {
        $user = User::factory()->create();

        $this->browse(function (Browser $browser) use ($user) {
            $browser->loginAs($user)
                ->resize(375, 667) // iPhone size
                ->visit('/dashboard')
                ->click('button[aria-label="open menu"]')
                ->waitFor('nav[aria-label="Responsive Navigation"]')
                ->assertSee('Dashboard')
                ->assertSee('Wallet')
                ->assertSee('Transactions')
                ->assertSee('Track Status')
                ->assertSee('Fund Flow');
        });
    }

    /**
     * Test critical API endpoints are accessible
     */
    public function test_critical_api_endpoints(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $endpoints = [
            '/api/v1/auth/me',
            '/api/v1/assets',
            '/api/v1/accounts',
        ];

        foreach ($endpoints as $endpoint) {
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
                'Accept' => 'application/json',
            ])->get($endpoint);

            $this->assertNotEquals(404, $response->status());
            $this->assertNotEquals(500, $response->status());
        }
    }

    /**
     * Test error pages are properly shown
     */
    public function test_error_pages(): void
    {
        $this->browse(function (Browser $browser) {
            // Test 404 page
            $browser->visit('/non-existent-page')
                ->assertSee('404')
                ->assertDontSee('RouteNotFoundException')
                ->assertDontSee('route() expects');
        });
    }

    /**
     * Clean up after tests
     */
    protected function tearDown(): void
    {
        // Clean up test users
        User::where('email', 'like', 'test%@example.com')->delete();
        
        parent::tearDown();
    }
}