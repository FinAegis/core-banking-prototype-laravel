<?php

declare(strict_types=1);

namespace App\Domain\Relayer\Services;

use App\Domain\Account\Models\BlockchainAddress;
use App\Domain\Relayer\Enums\SupportedNetwork;
use App\Domain\Relayer\Exceptions\SponsorshipRequestRejectedException;
use App\Domain\Relayer\Models\SmartAccount;
use App\Domain\Wallet\Constants\EvmTokens;
use App\Domain\Wallet\Services\Send\Erc20Transfer;
use App\Domain\Wallet\Services\Send\UserOpCallDataBuilder;
use App\Domain\Wallet\Services\SponsorshipBudget;
use App\Models\User;
use Throwable;

/**
 * Sponsorship policy shared by the generic sponsorship entry points: REST
 * POST /api/v1/relayer/sponsor and the GraphQL sponsorTransaction mutation.
 *
 * Both are closed unless `relayer.generic_sponsor_enabled`
 * (RELAYER_GENERIC_SPONSOR_ENABLED) is on. When on, a request is sponsored
 * only if:
 *  - the network is in WALLET_SEND_EVM_NETWORKS (wallet.evm.enabled_networks)
 *    and is a supported relayer network;
 *  - the sender is a smart account (relayer smart_accounts row) or an active
 *    wallet address (blockchain_addresses row) registered to the caller on
 *    that network;
 *  - the calldata is exactly the wallet-send pattern:
 *    execute(<USDC|USDT contract on that network>, 0, transfer(to, amount))
 *    with amount > 0;
 *  - an init code, if any, equals the deployment data of the caller's own
 *    undeployed smart account.
 *
 * Both entry points call {@see admit()}, which also consumes one unit of the
 * daily sponsorship budget shared with the wallet send
 * ({@see SponsorshipBudget}: per-user and global counts, optional USD
 * budget). An exhausted budget is refused with HTTP 429 / a GraphQL error.
 */
class SponsorshipRequestValidator
{
    public function __construct(
        private readonly SmartAccountService $smartAccountService,
        private readonly SponsorshipBudget $budget,
    ) {
    }

    public function isEnabled(): bool
    {
        return (bool) config('relayer.generic_sponsor_enabled', false);
    }

    /**
     * @throws SponsorshipRequestRejectedException
     */
    public function assertEnabled(): void
    {
        if (! $this->isEnabled()) {
            throw SponsorshipRequestRejectedException::disabled();
        }
    }

    /**
     * Validate a sponsorship request and, when it passes, consume one unit of
     * the shared daily sponsorship budget. Returns the network the request
     * is bound to.
     *
     * @throws SponsorshipRequestRejectedException
     */
    public function admit(
        User $user,
        string $sender,
        string $callData,
        string $networkKey,
        ?string $initCode = null,
    ): SupportedNetwork {
        $network = $this->validate($user, $sender, $callData, $networkKey, $initCode);

        $denied = $this->budget->consume($user->id, 'relayer.sponsor');

        if ($denied === SponsorshipBudget::DENIED_USER_LIMIT) {
            throw SponsorshipRequestRejectedException::dailyLimitReached();
        }

        if ($denied !== null) {
            throw SponsorshipRequestRejectedException::temporarilyUnavailable();
        }

        return $network;
    }

    /**
     * Validate a sponsorship request against the policy and return the
     * network it is bound to. Does not touch the sponsorship budget.
     *
     * @throws SponsorshipRequestRejectedException
     */
    public function validate(
        User $user,
        string $sender,
        string $callData,
        string $networkKey,
        ?string $initCode = null,
    ): SupportedNetwork {
        $this->assertEnabled();

        $networkKey = strtolower(trim($networkKey));
        $network = $this->resolveNetwork($networkKey);

        if (preg_match('/^0x[a-fA-F0-9]{40}$/', $sender) !== 1) {
            throw SponsorshipRequestRejectedException::senderNotOwned();
        }

        $smartAccount = $this->findOwnedSmartAccount($user, $sender, $networkKey);

        if ($smartAccount === null && ! $this->ownsWalletAddress($user, $sender, $networkKey)) {
            throw SponsorshipRequestRejectedException::senderNotOwned();
        }

        $this->assertAllowedCallData($callData, $networkKey);

        if ($initCode !== null && $initCode !== '' && strtolower($initCode) !== '0x') {
            $this->assertOwnInitCode($smartAccount, $initCode, $networkKey);
        }

        return $network;
    }

    /**
     * @throws SponsorshipRequestRejectedException
     */
    private function resolveNetwork(string $networkKey): SupportedNetwork
    {
        /** @var array<int, mixed> $enabled */
        $enabled = (array) config('wallet.evm.enabled_networks', []);
        $enabled = array_map(static fn (mixed $n): string => strtolower(trim((string) $n)), $enabled);

        $network = SupportedNetwork::tryFrom($networkKey);

        if ($network === null || ! in_array($networkKey, $enabled, true) || ! isset(EvmTokens::NETWORK_CHAIN_IDS[$networkKey])) {
            throw SponsorshipRequestRejectedException::networkNotAllowed($networkKey);
        }

        return $network;
    }

    private function findOwnedSmartAccount(User $user, string $sender, string $networkKey): ?SmartAccount
    {
        /** @var SmartAccount|null $account */
        $account = SmartAccount::query()
            ->where('user_id', $user->id)
            ->where('account_address', strtolower($sender))
            ->where('network', $networkKey)
            ->first();

        return $account;
    }

    private function ownsWalletAddress(User $user, string $sender, string $networkKey): bool
    {
        return BlockchainAddress::query()
            ->where('user_uuid', $user->uuid)
            ->where('chain', $networkKey)
            ->where('is_active', true)
            ->whereRaw('LOWER(address) = ?', [strtolower($sender)])
            ->exists();
    }

    /**
     * Require calldata to be exactly
     * execute(token, 0, transfer(to, amount)) for an allow-listed stablecoin
     * on the network, re-encoded byte for byte by the same builders the
     * wallet send uses.
     *
     * @throws SponsorshipRequestRejectedException
     */
    private function assertAllowedCallData(string $callData, string $networkKey): void
    {
        $hex = strtolower(trim($callData));

        // selector (4) + target, value, offset, length (4 x 32) + transfer
        // calldata (68) padded to 96 bytes = 228 bytes.
        if (preg_match('/^0x[0-9a-f]{456}$/', $hex) !== 1) {
            throw SponsorshipRequestRejectedException::callDataNotAllowed();
        }

        $body = substr($hex, 2);

        if ('0x' . substr($body, 0, 8) !== UserOpCallDataBuilder::EXECUTE_SELECTOR) {
            throw SponsorshipRequestRejectedException::callDataNotAllowed();
        }

        $targetWord = substr($body, 8, 64);
        $target = '0x' . substr($targetWord, 24);

        $allowedTargets = array_values(array_filter([
            EvmTokens::contractFor('USDC', $networkKey),
            EvmTokens::contractFor('USDT', $networkKey),
        ]));

        if (! str_starts_with($targetWord, str_repeat('0', 24)) || ! in_array($target, $allowedTargets, true)) {
            throw SponsorshipRequestRejectedException::callDataNotAllowed();
        }

        // Inner bytes start after selector + 4 head words.
        $inner = substr($body, 8 + 64 * 4, 136);
        $recipientWord = substr($inner, 8, 64);
        $amountWord = substr($inner, 72, 64);

        if (! str_starts_with($recipientWord, str_repeat('0', 24))) {
            throw SponsorshipRequestRejectedException::callDataNotAllowed();
        }

        $recipient = '0x' . substr($recipientWord, 24);
        $amount = self::hexToDecimal($amountWord);

        if ($amount === '0') {
            throw SponsorshipRequestRejectedException::callDataNotAllowed();
        }

        try {
            $expected = UserOpCallDataBuilder::buildExecute(
                $target,
                Erc20Transfer::encodeTransferCallData($recipient, $amount),
            );
        } catch (Throwable) {
            throw SponsorshipRequestRejectedException::callDataNotAllowed();
        }

        if (! hash_equals($expected, $hex)) {
            throw SponsorshipRequestRejectedException::callDataNotAllowed();
        }
    }

    /**
     * @throws SponsorshipRequestRejectedException
     */
    private function assertOwnInitCode(?SmartAccount $account, string $initCode, string $networkKey): void
    {
        if ($account === null || $account->deployed) {
            throw SponsorshipRequestRejectedException::initCodeNotAllowed();
        }

        try {
            $expected = $this->smartAccountService->getInitCode((string) $account->owner_address, $networkKey);
        } catch (Throwable) {
            throw SponsorshipRequestRejectedException::initCodeNotAllowed();
        }

        $normalize = static function (string $code): string {
            $code = strtolower(trim($code));

            return str_starts_with($code, '0x') ? substr($code, 2) : $code;
        };

        if ($expected === '' || ! hash_equals($normalize($expected), $normalize($initCode))) {
            throw SponsorshipRequestRejectedException::initCodeNotAllowed();
        }
    }

    /**
     * Unsigned 256-bit hex word to a decimal string (bcmath).
     */
    private static function hexToDecimal(string $hex): string
    {
        $decimal = '0';

        foreach (str_split(ltrim($hex, '0')) as $digit) {
            $decimal = bcadd(bcmul($decimal, '16', 0), (string) intval($digit, 16), 0);
        }

        return $decimal;
    }
}
