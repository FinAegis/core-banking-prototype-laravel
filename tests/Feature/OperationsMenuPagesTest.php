<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Console\QueuedCommand;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

// The Operations menu and the stablecoin and custodian pages linked to
// route names without their "wallet." prefix, so every app page failed for
// staff with those roles. The custodian pages also read the wrong config
// level and called a health monitor method, a connector method and a job
// class that do not exist.

function operationsMenuUser(string $role): User
{
    Role::findOrCreate($role, 'web');
    $user = User::factory()->withPersonalTeam()->create(['email_verified_at' => now()]);
    $user->assignRole($role);
    Account::factory()->create(['user_uuid' => $user->uuid]);

    return $user;
}

it('renders the dashboard with the Operations menu for a super admin', function (): void {
    $this->actingAs(operationsMenuUser('super_admin'))
        ->get('/dashboard')
        ->assertOk()
        ->assertSee(route('wallet.stablecoin-operations.index'), false)
        ->assertSee(route('wallet.custodian-integration.index'), false);
});

it('renders the stablecoin operation pages for a stablecoin operator', function (string $path): void {
    $this->actingAs(operationsMenuUser('stablecoin_operator'))
        ->get($path)
        ->assertOk()
        ->assertSee(route('wallet.stablecoin-operations.index'), false);
})->with([
    '/wallet/stablecoin-operations',
    '/wallet/stablecoin-operations/mint',
    '/wallet/stablecoin-operations/burn',
    '/wallet/stablecoin-operations/history',
]);

it('renders the custodian integration pages for an operations manager', function (): void {
    $user = operationsMenuUser('operations_manager');

    // The pages list the connectors from config('custodians.connectors').
    $this->actingAs($user)
        ->get('/wallet/custodian-integration')
        ->assertOk()
        ->assertSee('Mock Bank')
        ->assertSee(route('wallet.custodian-integration.show', 'mock'), false);

    $this->actingAs($user)
        ->get('/wallet/custodian-integration/mock')
        ->assertOk()
        ->assertSee('Mock Bank')
        ->assertSee(url('/wallet/custodian-integration'), false);
});

it('tests the connection and queues a balance sync for a bank admin', function (): void {
    Queue::fake();
    $user = operationsMenuUser('bank_admin');

    $this->actingAs($user)
        ->postJson('/wallet/custodian-integration/mock/test-connection')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.available', true);

    $this->actingAs($user)
        ->post('/wallet/custodian-integration/mock/synchronize')
        ->assertRedirect(route('wallet.custodian-integration.show', 'mock'))
        ->assertSessionHas('success');

    Queue::assertPushed(QueuedCommand::class);
});
