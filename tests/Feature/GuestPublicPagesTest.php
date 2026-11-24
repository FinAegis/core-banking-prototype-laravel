<?php

declare(strict_types=1);

use App\Domain\Exchange\Projections\LiquidityPool;
use App\Domain\Governance\Models\GcuVotingProposal;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

// These pages are public, but their layout's navigation menu called
// auth()->user()->hasRole() and Auth::user()->currentTeam without a user,
// so every guest got a 500. A guest now sees the menu of a signed-in user
// without staff roles, minus the profile, team and sign-out entries, plus
// "Log in" and "Register". The pool page also read metric keys that
// LiquidityPoolService::getPoolMetrics() does not return, for every user.

function guestPublicPagesProposal(): GcuVotingProposal
{
    return GcuVotingProposal::create([
        'title'                => 'Rebalance the demo basket',
        'description'          => 'Shift weight to EUR.',
        'proposed_composition' => ['USD' => 30, 'EUR' => 40, 'GBP' => 30],
        'current_composition'  => ['USD' => 40, 'EUR' => 30, 'GBP' => 30],
        'rationale'            => 'Test',
        'status'               => 'active',
        'voting_starts_at'     => now()->subDay(),
        'voting_ends_at'       => now()->addDay(),
    ]);
}

function guestPublicPagesPool(): LiquidityPool
{
    return LiquidityPool::query()->create([
        'pool_id'        => (string) Str::uuid(),
        'base_currency'  => 'BTC',
        'quote_currency' => 'EUR',
        'base_reserve'   => '10',
        'quote_reserve'  => '450000',
        'total_shares'   => '2121.32',
        'fee_rate'       => '0.003',
        'is_active'      => true,
    ]);
}

/**
 * @param  TestResponse<Response>  $response
 */
function assertGuestMenu(TestResponse $response): void
{
    $response->assertSee(route('login'), false)
        ->assertSee('Log in')
        ->assertSee(route('exchange.index'), false)
        ->assertSee(route('wallet.index'), false)
        ->assertDontSee(route('logout'), false)
        ->assertDontSee(route('profile.show'), false)
        ->assertDontSee(route('wallet.stablecoin-operations.index'), false);

    if (Route::has('register')) {
        $response->assertSee(route('register'), false)->assertSee('Register');
    }
}

it('renders the public page for a guest with the guest menu', function (string $page): void {
    $path = match ($page) {
        'voting index'   => '/gcu/voting',
        'proposal'       => '/gcu/voting/' . guestPublicPagesProposal()->id,
        'exchange'       => '/exchange',
        'liquidity'      => '/liquidity',
        'liquidity pool' => '/liquidity/' . guestPublicPagesPool()->pool_id,
        default          => throw new InvalidArgumentException("Unknown page {$page}"),
    };

    $response = $this->get($path);

    $response->assertOk();
    assertGuestMenu($response);

    if ($page === 'liquidity pool') {
        // spot price = quote reserve / base reserve = 450000 / 10
        $response->assertSee('45,000.0000');
    }
})->with(['voting index', 'proposal', 'exchange', 'liquidity', 'liquidity pool']);

it('keeps the signed-in menu for a user without staff roles', function (): void {
    $user = User::factory()->withPersonalTeam()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get('/gcu/voting')
        ->assertOk()
        ->assertSee(route('logout'), false)
        ->assertSee(route('profile.show'), false)
        ->assertSee(route('exchange.index'), false)
        ->assertDontSee(route('wallet.stablecoin-operations.index'), false)
        ->assertDontSee('>Log in<', false);
});
