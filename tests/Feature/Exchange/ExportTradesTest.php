<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Exchange\Projections\Trade;
use App\Models\User;
use Illuminate\Support\Str;

// The trades page links to /exchange/export-trades, whose controller method
// did not exist.

function exportTradesRow(string $buyerAccountId, string $sellerAccountId, string $makerSide): Trade
{
    return Trade::query()->create([
        'trade_id'          => (string) Str::uuid(),
        'buy_order_id'      => (string) Str::uuid(),
        'sell_order_id'     => (string) Str::uuid(),
        'buyer_account_id'  => $buyerAccountId,
        'seller_account_id' => $sellerAccountId,
        'base_currency'     => 'BTC',
        'quote_currency'    => 'EUR',
        'price'             => '45000.00',
        'amount'            => '0.5',
        'value'             => '22500.00',
        'maker_fee'         => '22.5',
        'taker_fee'         => '45',
        'maker_side'        => $makerSide,
    ]);
}

it('downloads the signed-in user trades as CSV', function (): void {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $account = Account::factory()->create(['user_uuid' => $user->uuid]);
    $other = Account::factory()->create();

    $bought = exportTradesRow((string) $account->id, (string) $other->id, 'buy');
    $sold = exportTradesRow((string) $other->id, (string) $account->id, 'buy');
    $notMine = exportTradesRow((string) $other->id, '999999', 'sell');

    $response = $this->actingAs($user)->get(route('exchange.export-trades'));

    $response->assertOk();
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($response->headers->get('Content-Disposition'))->toContain('trades-');

    $lines = array_values(array_filter(explode("\n", $response->streamedContent())));
    expect($lines)->toHaveCount(3)
        ->and($lines[0])->toBe('Date,"Trade ID",Pair,Side,Role,Price,Amount,Value,Fee,"Fee currency"');

    $columns = ['Date', 'Trade ID', 'Pair', 'Side', 'Role', 'Price', 'Amount', 'Value', 'Fee', 'Fee currency'];
    $rows = [];
    foreach (array_slice($lines, 1) as $line) {
        $row = array_combine($columns, str_getcsv($line));
        $rows[(string) $row['Trade ID']] = $row;
    }

    expect(array_keys($rows))->toEqualCanonicalizing([$bought->trade_id, $sold->trade_id])
        ->and($rows[$bought->trade_id]['Pair'])->toBe('BTC/EUR')
        ->and($rows[$bought->trade_id]['Side'])->toBe('buy')
        ->and($rows[$bought->trade_id]['Role'])->toBe('maker')
        ->and((float) $rows[$bought->trade_id]['Fee'])->toBe(22.5)
        ->and($rows[$sold->trade_id]['Side'])->toBe('sell')
        ->and($rows[$sold->trade_id]['Role'])->toBe('taker')
        ->and((float) $rows[$sold->trade_id]['Fee'])->toBe(45.0)
        ->and(array_key_exists($notMine->trade_id, $rows))->toBeFalse();
});

it('sends guests to log in', function (): void {
    $this->get(route('exchange.export-trades'))->assertRedirect(route('login'));
});
