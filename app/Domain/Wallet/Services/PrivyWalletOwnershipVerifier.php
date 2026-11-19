<?php

declare(strict_types=1);

namespace App\Domain\Wallet\Services;

use App\Domain\Auth\Services\PrivyJwtVerifier;
use App\Domain\Wallet\Exceptions\AddressOwnershipUnavailableException;
use App\Domain\Wallet\Exceptions\AddressRegistrationRejectedException;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Checks that addresses submitted to POST /api/v1/wallet/addresses are
 * wallets of the caller's own Privy account.
 *
 * The mobile app registers the Privy embedded wallets it reads from
 * `useEmbeddedEthereumWallet()` / `useEmbeddedSolanaWallet()`. Privy lists
 * those on the user record (GET /api/v1/users/{privy_user_id}) as
 * `linked_accounts` entries `{type: wallet, chain_type: ethereum|solana}`;
 * a Privy smart wallet appears as `{type: smart_wallet}`.
 *
 * Mode (`wallet.address_registration.ownership_verification`):
 *  - `enforce`: an address that is not one of the caller's linked wallets is
 *    refused; a caller without a Privy identity is refused; when Privy cannot
 *    be asked (unreachable / no app secret) registration fails closed (503).
 *  - `monitor` (default): the same check runs but only logs a warning on a
 *    mismatch, so operators can confirm real users pass before enforcing.
 *  - `off`: no check.
 */
class PrivyWalletOwnershipVerifier
{
    public const MODE_ENFORCE = 'enforce';

    public const MODE_MONITOR = 'monitor';

    public const MODE_OFF = 'off';

    public function __construct(
        private readonly PrivyJwtVerifier $privy,
    ) {
    }

    public function mode(): string
    {
        $mode = strtolower(trim((string) config('wallet.address_registration.ownership_verification', self::MODE_MONITOR)));

        return in_array($mode, [self::MODE_ENFORCE, self::MODE_MONITOR, self::MODE_OFF], true)
            ? $mode
            : self::MODE_MONITOR;
    }

    /**
     * Verify the addresses the caller is newly claiming. Pass null for a
     * chain whose address the caller already owns.
     *
     * Returns true when Privy confirmed every claimed address is the
     * caller's, false when it did not (monitor mode only — enforce throws),
     * and null when nothing was checked (mode off, or Privy unavailable in
     * monitor mode). In monitor mode this never throws, whatever Privy does.
     *
     * @throws AddressRegistrationRejectedException   enforce mode, address not linked to the caller
     * @throws AddressOwnershipUnavailableException   enforce mode, Privy could not be asked
     */
    public function verify(User $user, ?string $evmAddress, ?string $solanaAddress): ?bool
    {
        $mode = $this->mode();

        if ($mode === self::MODE_OFF || ($evmAddress === null && $solanaAddress === null)) {
            return null;
        }

        $enforce = $mode === self::MODE_ENFORCE;
        $privyUserId = (string) ($user->privy_user_id ?? '');

        if ($privyUserId === '') {
            Log::warning('wallet.addresses: caller has no Privy identity to verify wallet ownership against', [
                'user_uuid' => $user->uuid,
                'mode'      => $mode,
            ]);

            if ($enforce) {
                throw new AddressRegistrationRejectedException();
            }

            return false;
        }

        try {
            $linkedAccounts = $this->privy->fetchLinkedAccounts($privyUserId);
        } catch (Throwable) {
            $linkedAccounts = null;
        }

        if ($linkedAccounts === null) {
            Log::warning('wallet.addresses: Privy linked accounts unavailable, wallet ownership not verified', [
                'user_uuid' => $user->uuid,
                'mode'      => $mode,
            ]);

            if ($enforce) {
                throw new AddressOwnershipUnavailableException();
            }

            return null;
        }

        $unlinked = [];

        if ($evmAddress !== null && ! $this->isLinkedEvm($linkedAccounts, $evmAddress)) {
            $unlinked['evm'] = $evmAddress;
        }

        if ($solanaAddress !== null && ! $this->isLinkedSolana($linkedAccounts, $solanaAddress)) {
            $unlinked['solana'] = $solanaAddress;
        }

        if ($unlinked === []) {
            Log::info('wallet.addresses: wallet ownership verified against Privy', [
                'user_uuid' => $user->uuid,
                'mode'      => $mode,
            ]);

            return true;
        }

        // Enough context to tell a real mismatch from a Privy response shape
        // this check does not understand yet, before switching to enforce.
        Log::warning('wallet.addresses: submitted address is not a wallet of the caller\'s Privy account', [
            'user_uuid'      => $user->uuid,
            'privy_user_id'  => $privyUserId,
            'mode'           => $mode,
            'unlinked'       => $unlinked,
            'claimed'        => ['evm' => $evmAddress, 'solana' => $solanaAddress],
            'linked_wallets' => $this->walletSummary($linkedAccounts),
            'linked_types'   => $this->typeCounts($linkedAccounts),
        ]);

        if ($enforce) {
            throw new AddressRegistrationRejectedException();
        }

        return false;
    }

    /**
     * The wallet-like linked accounts (public addresses and their kind) for
     * the mismatch log; at most 10 entries.
     *
     * @param  array<int, array<string, mixed>> $linkedAccounts
     * @return list<array<string, string>>
     */
    private function walletSummary(array $linkedAccounts): array
    {
        $summary = [];

        foreach ($linkedAccounts as $account) {
            $type = $account['type'] ?? null;

            if (! is_string($type) || ! str_contains($type, 'wallet')) {
                continue;
            }

            $entry = [];
            foreach (['type', 'chain_type', 'address', 'wallet_client_type', 'connector_type', 'smart_wallet_type'] as $field) {
                if (is_string($account[$field] ?? null)) {
                    $entry[$field] = $account[$field];
                }
            }

            $summary[] = $entry;

            if (count($summary) >= 10) {
                break;
            }
        }

        return $summary;
    }

    /**
     * @param  array<int, array<string, mixed>> $linkedAccounts
     * @return array<string, int>
     */
    private function typeCounts(array $linkedAccounts): array
    {
        $counts = [];

        foreach ($linkedAccounts as $account) {
            $type = is_string($account['type'] ?? null) ? $account['type'] : 'unknown';
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }

        return $counts;
    }

    /**
     * @param array<int, array<string, mixed>> $linkedAccounts
     */
    private function isLinkedEvm(array $linkedAccounts, string $address): bool
    {
        $address = strtolower($address);

        foreach ($linkedAccounts as $account) {
            $type = $account['type'] ?? null;
            $chainType = $account['chain_type'] ?? null;
            $linked = $account['address'] ?? null;

            $isEvmWallet = ($type === 'wallet' && $chainType === 'ethereum') || $type === 'smart_wallet';

            if ($isEvmWallet && is_string($linked) && strtolower($linked) === $address) {
                return true;
            }
        }

        return false;
    }

    /**
     * Solana addresses are case-sensitive — compared verbatim.
     *
     * @param array<int, array<string, mixed>> $linkedAccounts
     */
    private function isLinkedSolana(array $linkedAccounts, string $address): bool
    {
        foreach ($linkedAccounts as $account) {
            if (
                ($account['type'] ?? null) === 'wallet'
                && ($account['chain_type'] ?? null) === 'solana'
                && ($account['address'] ?? null) === $address
            ) {
                return true;
            }
        }

        return false;
    }
}
