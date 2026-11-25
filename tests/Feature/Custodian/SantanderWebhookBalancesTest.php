<?php

/**
 * A Santander account.updated webhook reports balances in major units. The
 * processor converted them with (int) ($amount * 100), so 128.14 EUR became
 * 12813 cents and every currency was assumed to have two decimals.
 */

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Asset\Models\Asset;
use App\Domain\Custodian\Events\AccountBalanceUpdated;
use App\Domain\Custodian\Models\CustodianAccount;
use App\Domain\Custodian\Models\CustodianWebhook;
use App\Domain\Custodian\Services\WebhookProcessorService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Asset::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'type' => 'fiat', 'precision' => 2, 'is_active' => true]);
    Asset::firstOrCreate(['code' => 'JPY'], ['name' => 'Yen', 'type' => 'fiat', 'precision' => 0, 'is_active' => true]);

    $account = Account::factory()->create();
    CustodianAccount::create([
        'account_uuid'         => $account->uuid,
        'custodian_name'       => 'santander',
        'custodian_account_id' => 'ES-123',
        'status'               => 'active',
        'is_primary'           => true,
    ]);
});

/**
 * @param  array<string, mixed>  $balances
 */
function santanderBalanceWebhook(array $balances): CustodianWebhook
{
    return CustodianWebhook::create([
        'uuid'           => (string) Str::uuid(),
        'custodian_name' => 'santander',
        'event_type'     => 'account.updated',
        'event_id'       => (string) Str::uuid(),
        'payload'        => ['account_number' => 'ES-123', 'balance_changed' => true, 'balances' => $balances],
        'status'         => 'pending',
    ]);
}

it('reports Santander balances in exact minor units of each currency', function (): void {
    Event::fake([AccountBalanceUpdated::class]);

    app(WebhookProcessorService::class)->process(santanderBalanceWebhook(['EUR' => 128.14, 'JPY' => 1500]));

    Event::assertDispatched(AccountBalanceUpdated::class, fn (AccountBalanceUpdated $event): bool => $event->balances === ['EUR' => 12814, 'JPY' => 1500]);
});

it('refuses a Santander balance with more decimals than the currency has', function (): void {
    Event::fake([AccountBalanceUpdated::class]);

    expect(fn () => app(WebhookProcessorService::class)->process(santanderBalanceWebhook(['EUR' => '128.145'])))
        ->toThrow(RuntimeException::class);

    Event::assertNotDispatched(AccountBalanceUpdated::class);
});
