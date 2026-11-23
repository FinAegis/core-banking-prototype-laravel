<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Asset\Models\Asset;
use App\Domain\Asset\Models\ExchangeRate;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Fixed demonstration exchange rates for the FinAegis demo site.
 *
 * The demo has no scheduler to refresh rates, and rates the exchange rate
 * service fetches expire after an hour, so currency conversions failed
 * there. These rates do not expire. They are illustrative, not market data,
 * and the seeder refuses to run in production.
 *
 * Every pair is derived from one US-dollar price per asset, so the rates are
 * consistent in both directions. Assets that do not exist are skipped.
 */
final class DemoExchangeRateSeeder extends Seeder
{
    /** US dollars per one unit of each asset (illustrative). */
    private const USD_PRICES = [
        'USD'  => '1',
        'EUR'  => '1.08',
        'GBP'  => '1.27',
        'CHF'  => '1.13',
        'USDC' => '1',
        'USDT' => '1',
        'BTC'  => '90000',
        'ETH'  => '3200',
        'XAU'  => '2600',
    ];

    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Demonstration exchange rates are never seeded in production.');
        }

        $codes = Asset::query()->whereIn('code', array_keys(self::USD_PRICES))->pluck('code')->all();

        foreach ($codes as $from) {
            foreach ($codes as $to) {
                if ($from === $to) {
                    continue;
                }

                ExchangeRate::query()->updateOrCreate(
                    ['from_asset_code' => $from, 'to_asset_code' => $to, 'source' => ExchangeRate::SOURCE_MANUAL],
                    [
                        'rate'       => bcdiv(self::USD_PRICES[$from], self::USD_PRICES[$to], 10),
                        'valid_at'   => now()->subMinute(),
                        'expires_at' => null,
                        'is_active'  => true,
                        'metadata'   => ['demo' => true, 'note' => 'Illustrative demonstration rate, not market data.'],
                    ]
                );
            }
        }
    }
}
