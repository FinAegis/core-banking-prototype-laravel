<?php

/**
 * The AI deposit, withdraw and transfer tools used to hand the services an
 * array (['amount' => ..., 'currency' => ...]) where they take Money or an
 * int, so every call that passed the checks ended in a TypeError. Deposit
 * and transfer also counted the amount with (int) ($amount * 100): 0.29 USD
 * became 28 cents and 1.1 BTC became 110 satoshis.
 *
 * Now they count exact minor units in the precision of the asset, start the
 * same workflows as the REST endpoints, and deposit and withdraw stay off
 * while direct ledger funding is off.
 */

declare(strict_types=1);

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Account\Workflows\DepositAccountActivity;
use App\Domain\Account\Workflows\WithdrawAccountActivity;
use App\Domain\AI\MCP\Tools\Account\DepositTool;
use App\Domain\AI\MCP\Tools\Account\WithdrawTool;
use App\Domain\AI\MCP\Tools\Payment\TransferTool;
use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Workflows\Activities\DepositAssetActivity;
use App\Domain\Asset\Workflows\Activities\WithdrawAssetActivity;
use App\Domain\Wallet\Workflows\WalletDepositWorkflow;
use App\Domain\Wallet\Workflows\WalletWithdrawWorkflow;
use App\Models\User;
use Workflow\WorkflowStub;

beforeEach(function (): void {
    Asset::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
    Asset::firstOrCreate(['code' => 'BTC'], ['name' => 'Bitcoin', 'type' => 'crypto', 'precision' => 8, 'is_active' => true]);

    $this->user = User::factory()->create();
    $this->account = Account::factory()->create(['user_uuid' => $this->user->uuid, 'frozen' => false]);
    $this->actingAs($this->user);

    config(['ledger.direct_funding_enabled' => true]);
    WorkflowStub::fake();
    WorkflowStub::mock(DepositAccountActivity::class, true);
    WorkflowStub::mock(WithdrawAccountActivity::class, true);
    WorkflowStub::mock(DepositAssetActivity::class, 'transaction-id');
    WorkflowStub::mock(WithdrawAssetActivity::class, 'transaction-id');
    WorkflowStub::mock(WalletWithdrawWorkflow::class, true);
    WorkflowStub::mock(WalletDepositWorkflow::class, true);
});

describe('account.deposit', function (): void {
    it('deposits the exact minor units of the asset', function (): void {
        $usd = app(DepositTool::class)->execute(['account_uuid' => $this->account->uuid, 'amount' => 0.29, 'currency' => 'USD']);
        $btc = app(DepositTool::class)->execute(['account_uuid' => $this->account->uuid, 'amount' => 1.1, 'currency' => 'BTC']);

        expect($usd->isSuccess())->toBeTrue()
            ->and($usd->getData()['amount'])->toBe('0.29')
            ->and($btc->isSuccess())->toBeTrue()
            ->and($btc->getData()['amount'])->toBe('1.10000000');

        WorkflowStub::assertDispatched(
            DepositAccountActivity::class,
            fn (AccountUuid $uuid, Money $money): bool => $uuid->getUuid() === $this->account->uuid && $money->getAmount() === 29
        );
        WorkflowStub::assertDispatched(
            DepositAssetActivity::class,
            fn (AccountUuid $uuid, string $asset, Money $money): bool => $asset === 'BTC' && $money->getAmount() === 110000000
        );
    });

    it('refuses an amount with more decimals than the asset has', function (): void {
        $result = app(DepositTool::class)->execute(['account_uuid' => $this->account->uuid, 'amount' => '1.005', 'currency' => 'USD']);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getError())->toBe('The amount can have at most 2 decimal places for USD.');
        WorkflowStub::assertNothingDispatched();
    });

    it('stays off while direct ledger funding is off', function (): void {
        config(['ledger.direct_funding_enabled' => false]);

        $result = app(DepositTool::class)->execute(['account_uuid' => $this->account->uuid, 'amount' => 10, 'currency' => 'USD']);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getError())->toBe('This operation is not available.');
        WorkflowStub::assertNothingDispatched();
    });
});

describe('account.withdraw', function (): void {
    it('withdraws the exact minor units of the asset', function (): void {
        AccountBalance::create(['account_uuid' => $this->account->uuid, 'asset_code' => 'USD', 'balance' => 29]);
        AccountBalance::create(['account_uuid' => $this->account->uuid, 'asset_code' => 'BTC', 'balance' => 110000000]);

        $usd = app(WithdrawTool::class)->execute(['account_uuid' => $this->account->uuid, 'amount' => 0.29, 'currency' => 'USD']);
        $btc = app(WithdrawTool::class)->execute(['account_uuid' => $this->account->uuid, 'amount' => 1.1, 'currency' => 'BTC']);

        expect($usd->isSuccess())->toBeTrue()
            ->and($btc->isSuccess())->toBeTrue();

        WorkflowStub::assertDispatched(
            WithdrawAccountActivity::class,
            fn (AccountUuid $uuid, Money $money): bool => $uuid->getUuid() === $this->account->uuid && $money->getAmount() === 29
        );
        WorkflowStub::assertDispatched(
            WithdrawAssetActivity::class,
            fn (AccountUuid $uuid, string $asset, Money $money): bool => $asset === 'BTC' && $money->getAmount() === 110000000
        );
    });

    it('stays off while direct ledger funding is off', function (): void {
        config(['ledger.direct_funding_enabled' => false]);
        AccountBalance::create(['account_uuid' => $this->account->uuid, 'asset_code' => 'USD', 'balance' => 10000]);

        $result = app(WithdrawTool::class)->execute(['account_uuid' => $this->account->uuid, 'amount' => 10, 'currency' => 'USD']);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getError())->toBe('This operation is not available.');
        WorkflowStub::assertNothingDispatched();
    });
});

describe('payment.transfer', function (): void {
    beforeEach(function (): void {
        $this->destination = Account::factory()->create(['frozen' => false]);
    });

    it('moves the exact minor units of the asset', function (string $asset, int $balance, float $amount, int $minor, string $shown): void {
        AccountBalance::create(['account_uuid' => $this->account->uuid, 'asset_code' => $asset, 'balance' => $balance]);

        $result = app(TransferTool::class)->execute([
            'from_account_uuid' => $this->account->uuid,
            'to_account_uuid'   => $this->destination->uuid,
            'amount'            => $amount,
            'currency'          => $asset,
        ]);

        expect($result->isSuccess())->toBeTrue()
            ->and($result->getData()['amount'])->toBe($shown)
            ->and($result->getData()['fee'])->toBe('0');

        WorkflowStub::assertDispatched(
            WalletWithdrawWorkflow::class,
            fn (AccountUuid $uuid, string $code, int $units): bool => $uuid->getUuid() === $this->account->uuid && $code === $asset && $units === $minor
        );
        WorkflowStub::assertDispatched(
            WalletDepositWorkflow::class,
            fn (AccountUuid $uuid, string $code, int $units): bool => $uuid->getUuid() === $this->destination->uuid && $code === $asset && $units === $minor
        );
    })->with([
        '0.29 USD' => ['USD', 29, 0.29, 29, '0.29'],
        '1.1 BTC'  => ['BTC', 110000000, 1.1, 110000000, '1.10000000'],
    ]);

    it('refuses a transfer one minor unit above the balance', function (): void {
        AccountBalance::create(['account_uuid' => $this->account->uuid, 'asset_code' => 'USD', 'balance' => 28]);

        $result = app(TransferTool::class)->execute([
            'from_account_uuid' => $this->account->uuid,
            'to_account_uuid'   => $this->destination->uuid,
            'amount'            => 0.29,
            'currency'          => 'USD',
        ]);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getError())->toBe('Insufficient balance. Available: 0.28 USD');
        WorkflowStub::assertNothingDispatched();
    });

    it('refuses a transfer from or to a frozen account', function (string $frozen, string $error): void {
        AccountBalance::create(['account_uuid' => $this->account->uuid, 'asset_code' => 'USD', 'balance' => 10000]);
        ($frozen === 'source' ? $this->account : $this->destination)->update(['frozen' => true]);

        $result = app(TransferTool::class)->execute([
            'from_account_uuid' => $this->account->uuid,
            'to_account_uuid'   => $this->destination->uuid,
            'amount'            => 10,
            'currency'          => 'USD',
        ]);

        expect($result->isSuccess())->toBeFalse()
            ->and($result->getError())->toBe($error);
        WorkflowStub::assertNothingDispatched();
    })->with([
        'source'      => ['source', 'The source account is frozen.'],
        'destination' => ['destination', 'The destination account is frozen.'],
    ]);
});
