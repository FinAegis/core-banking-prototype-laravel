<?php

declare(strict_types=1);

namespace App\Domain\AccountProvisioning\Seeders;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Privacy\Models\ShieldedBalance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Seeds USDC balances for a review/demo account on Polygon.
 *
 * Writes directly to the balance tables — this is a review-account shortcut
 * that bypasses the event-sourcing / ledger pipeline but leaves production
 * paths intact. All financial values go through bcmath; all writes are wrapped
 * in a single DB::transaction().
 */
class BalanceSeeder
{
    /**
     * @param numeric-string $unshieldedUsdc decimal string, e.g. '25.00'
     * @param numeric-string $shieldedUsdc   decimal string, e.g. '10.00'
     */
    public function seed(User $user, string $unshieldedUsdc, string $shieldedUsdc): void
    {
        if (! is_numeric($unshieldedUsdc) || ! is_numeric($shieldedUsdc)) {
            throw new RuntimeException('BalanceSeeder requires numeric decimal strings.');
        }

        DB::transaction(function () use ($user, $unshieldedUsdc, $shieldedUsdc): void {
            $this->setUnshieldedBalance($user, 'USDC', 'polygon', $unshieldedUsdc);
            $this->setShieldedBalance($user, 'USDC', 'polygon', $shieldedUsdc);
        });
    }

    /**
     * Upsert a row in `token_balances` keyed on (address, chain, token_address).
     *
     * Review-account shortcut — sets the target balance directly rather than going
     * through the full ledger domain (event-sourced AssetTransactionAggregate).
     *
     * @param numeric-string $target
     */
    private function setUnshieldedBalance(User $user, string $symbol, string $chain, string $target): void
    {
        $normalized = bcadd($target, '0', 6); // USDC native decimals = 6

        /** @var BlockchainAddress|null $wallet */
        $wallet = BlockchainAddress::where('user_uuid', $user->uuid)
            ->where('chain', $chain)
            ->where('is_active', true)
            ->first();

        if (! $wallet instanceof BlockchainAddress) {
            throw new RuntimeException(
                "BalanceSeeder requires a {$chain} BlockchainAddress for user {$user->id}; run WalletSeeder first."
            );
        }

        $tokenAddress = (string) config(
            'supported_tokens.tokens.' . $symbol . '.' . $chain,
            '0x0000000000000000000000000000000000000000'
        );

        $existing = DB::table('token_balances')
            ->where('address', $wallet->address)
            ->where('chain', $chain)
            ->where('token_address', $tokenAddress)
            ->first();

        $payload = [
            'wallet_id'  => 'review-' . $user->id,
            'symbol'     => $symbol,
            'name'       => $symbol,
            'decimals'   => 6,
            'balance'    => $normalized,
            'value_usd'  => $normalized,
            'updated_at' => now(),
        ];

        if ($existing === null) {
            DB::table('token_balances')->insert(array_merge($payload, [
                'address'       => $wallet->address,
                'chain'         => $chain,
                'token_address' => $tokenAddress,
                'created_at'    => now(),
            ]));
        } else {
            DB::table('token_balances')
                ->where('address', $wallet->address)
                ->where('chain', $chain)
                ->where('token_address', $tokenAddress)
                ->update($payload);
        }
    }

    /**
     * Upsert a row in `shielded_balances` keyed on (user_id, token, network).
     *
     * Review-account shortcut — bypasses the RAILGUN bridge sync pipeline.
     *
     * @param numeric-string $target
     */
    private function setShieldedBalance(User $user, string $token, string $network, string $target): void
    {
        $normalized = bcadd($target, '0', 6);

        ShieldedBalance::updateOrCreate(
            [
                'user_id' => $user->id,
                'token'   => $token,
                'network' => $network,
            ],
            [
                'railgun_address' => 'review:' . $user->uuid,
                'balance'         => $normalized,
                'last_synced_at'  => now(),
            ]
        );
    }
}
