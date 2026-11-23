<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Payment\Services\PaymentGatewayService;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Mockery\MockInterface;

/**
 * The bank withdrawal page (behind ledger.direct_funding_enabled, which is on
 * in testing). Saving bank accounts for reuse was removed: it wrote and read
 * columns the bank_accounts table does not have. People type the bank
 * details for each withdrawal.
 */
function withdrawalUserWithBalance(int $cents): User
{
    $user = User::factory()->create();
    $account = Account::factory()->create(['user_uuid' => $user->uuid]);
    AccountBalance::factory()->create([
        'account_uuid' => $account->uuid,
        'asset_code'   => 'USD',
        'balance'      => $cents,
    ]);

    return $user;
}

it('no longer offers to save or reuse bank accounts', function (): void {
    $this->actingAs(withdrawalUserWithBalance(100_00));

    $this->get('/wallet/withdraw/bank')
        ->assertOk()
        ->assertDontSee('Save this bank account for future withdrawals')
        ->assertDontSee('Manage Bank Accounts');

    expect(Route::has('wallet.withdraw.bank-account.add'))->toBeFalse()
        ->and(Route::has('wallet.withdraw.bank-account.remove'))->toBeFalse();
});

it('sends the typed bank details and the exact amount in cents', function (): void {
    $this->actingAs(withdrawalUserWithBalance(100_00));

    /** @var PaymentGatewayService&MockInterface $gateway */
    $gateway = Mockery::mock(PaymentGatewayService::class);
    $gateway->shouldReceive('createWithdrawalRequest')
        ->once()
        ->withArgs(fn (Account $account, int $cents, string $currency, array $details): bool => $cents === 19_99
            && $currency === 'USD'
            && $details['bank_name'] === 'Example Bank'
            && $details['account_number'] === '12345678'
            && $details['account_holder_name'] === 'Ada Lovelace')
        ->andReturn(['reference' => 'wd_1']);
    app()->instance(PaymentGatewayService::class, $gateway);

    $this->post('/wallet/withdraw/bank', [
        'amount'              => '19.99',
        'currency'            => 'USD',
        'bank_name'           => 'Example Bank',
        'account_number'      => '12345678',
        'account_holder_name' => 'Ada Lovelace',
    ])->assertRedirect(route('wallet.index'));
});

it('requires the bank details', function (): void {
    $this->actingAs(withdrawalUserWithBalance(100_00));

    $this->post('/wallet/withdraw/bank', ['amount' => '20', 'currency' => 'USD'])
        ->assertSessionHasErrors(['bank_name', 'account_number', 'account_holder_name']);
});
