<?php

declare(strict_types=1);

namespace App\Domain\Account\Services;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use Illuminate\Support\Facades\DB;

/**
 * Credits an account balance by an integer minor-unit amount (e.g. cents).
 *
 * Extracted from CreditAccountActivity so the same crediting logic is reused
 * by event-sourcing activities and by inbound payment webhooks (HyperSwitch).
 *
 * Money is held as integer minor units, so addition is exact — there is no
 * float arithmetic here. The read-modify-write is guarded by a row lock inside
 * a transaction on the BALANCE's own connection. Account/AccountBalance use
 * `UsesTenantConnection`, so the transaction is opened on that connection
 * explicitly — never the default connection — to avoid the cross-connection
 * self-deadlock documented in CLAUDE.md.
 */
class AccountCreditService
{
    public function credit(string $accountUuid, int $amountMinor, string $assetCode): void
    {
        $account = Account::where('uuid', $accountUuid)->firstOrFail();

        // Resolve the connection the balance rows live on (tenant in production,
        // the default connection under the testing carve-out).
        $connection = (new AccountBalance())->getConnectionName();

        DB::connection($connection)->transaction(function () use ($account, $amountMinor, $assetCode): void {
            $balance = $account->balances()
                ->where('asset_code', $assetCode)
                ->lockForUpdate()
                ->first();

            if ($balance === null) {
                $account->balances()->create([
                    'asset_code' => $assetCode,
                    'balance'    => $amountMinor,
                ]);

                return;
            }

            $balance->credit($amountMinor);
        });
    }
}
