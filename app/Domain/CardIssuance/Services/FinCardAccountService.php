<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Services;

use App\Domain\CardIssuance\Events\Broadcast\FinCardAccountFunded;
use App\Domain\CardIssuance\Models\FinCardAccount;
use App\Domain\CardIssuance\Models\FinCardDepositAddress;
use App\Infrastructure\FinCard\FinCardClient;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * FinCard funding: per-user account provisioning, crypto deposit addresses, and
 * applying wallet funding webhooks to the mirrored balance.
 *
 * The local `fincard_accounts.balance_cents` is a CACHE — FinCard holds the
 * authoritative USD funds. It is updated from wallet webhooks (deduped once at
 * the controller via processed_webhook_events) and can be reconciled from
 * FinCard via syncBalance(). All amounts are integer minor units; conversions
 * use bcmath (never float). Balance mutations lock the row inside a
 * tenant-connection transaction and run OUTSIDE the webhook controller's
 * default-connection dedupe (cross-connection transactions self-deadlock).
 *
 * @see docs/superpowers/specs/2026-07-06-fincard-card-issuing-design.md §6
 */
final class FinCardAccountService
{
    public function __construct(
        private readonly FinCardClient $client,
    ) {
    }

    public function existingAccount(User $user): ?FinCardAccount
    {
        return FinCardAccount::query()->where('user_id', $user->id)->first();
    }

    /**
     * The user's FinCard account, creating it on FinCard on first use.
     *
     * @param  array<string, mixed>  $context
     */
    public function getOrCreateAccount(User $user, array $context = []): FinCardAccount
    {
        $existing = $this->existingAccount($user);
        if ($existing instanceof FinCardAccount) {
            return $existing;
        }

        $response = $this->client->createAccount("Zelta user {$user->id}", 'USD', $context);
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];

        $account = new FinCardAccount();
        $account->user_id = (string) $user->id;
        $account->fincard_account_id = (string) ($data['accountId'] ?? $data['id'] ?? '');
        $account->currency = 'USD';
        $account->balance_cents = 0;
        $account->status = 'active';
        $account->save();

        return $account;
    }

    /**
     * The user's deposit address for a coin, creating it on FinCard on first use.
     *
     * @param  array<string, mixed>  $context
     */
    public function getOrCreateDepositAddress(User $user, string $coinKey, array $context = []): FinCardDepositAddress
    {
        $existing = FinCardDepositAddress::query()
            ->where('user_id', $user->id)
            ->where('coin_key', $coinKey)
            ->first();
        if ($existing instanceof FinCardDepositAddress) {
            return $existing;
        }

        $account = $this->getOrCreateAccount($user, $context);
        $response = $this->client->createDepositAddress($coinKey, $account->fincard_account_id, $context);
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];

        $address = new FinCardDepositAddress();
        $address->user_id = (string) $user->id;
        $address->fincard_account_id = $account->fincard_account_id;
        $address->coin_key = $coinKey;
        $address->chain = $this->stringOrNull($data['chain'] ?? null);
        $address->address = (string) ($data['address'] ?? '');
        $address->min_deposit_cents = isset($data['minDepositAmount']) ? $this->toCents((string) $data['minDepositAmount']) : null;
        $address->confirmations = isset($data['confirmations']) ? (int) $data['confirmations'] : null;
        $address->save();

        return $address;
    }

    /**
     * Reconcile the mirrored balance from FinCard's authoritative account query.
     *
     * @param  array<string, mixed>  $context
     */
    public function syncBalance(FinCardAccount $account, array $context = []): FinCardAccount
    {
        $response = $this->client->queryAccount($account->fincard_account_id, $context);
        $data = is_array($response['data'] ?? null) ? $response['data'] : [];

        if (isset($data['balance']) && is_numeric((string) $data['balance'])) {
            $account->balance_cents = $this->toCents((string) $data['balance']);
            $account->save();
        }

        return $account;
    }

    /**
     * Apply a wallet funding webhook (DEPOSIT credit / WITHDRAW debit) to the
     * mirrored balance. Idempotency is guaranteed by the controller's
     * processed_webhook_events dedupe, so a delta is applied at most once.
     *
     * @param  array<string, mixed>  $payload
     */
    public function applyFundingWebhook(string $eventType, array $payload): void
    {
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $accountId = (string) ($data['accountId'] ?? $data['account_id'] ?? '');
        if ($accountId === '') {
            Log::warning('FinCard funding webhook without accountId', ['event' => $eventType]);

            return;
        }

        $isDeposit = strtoupper($eventType) === 'DEPOSIT';
        $amountCents = $this->toCents((string) ($data['amount'] ?? '0'));
        // Prefer an authoritative balance if FinCard includes it; else apply the delta.
        $authoritativeBalance = isset($data['balance']) && is_numeric((string) $data['balance'])
            ? $this->toCents((string) $data['balance'])
            : null;

        $connection = (new FinCardAccount())->getConnectionName();

        $account = DB::connection($connection)->transaction(function () use ($accountId, $isDeposit, $amountCents, $authoritativeBalance): ?FinCardAccount {
            $account = FinCardAccount::query()->where('fincard_account_id', $accountId)->lockForUpdate()->first();
            if (! $account instanceof FinCardAccount) {
                return null;
            }

            if ($authoritativeBalance !== null) {
                $account->balance_cents = $authoritativeBalance;
            } elseif ($isDeposit) {
                $account->balance_cents += $amountCents;
            } else {
                $account->balance_cents = max(0, $account->balance_cents - $amountCents);
            }

            $account->save();

            return $account;
        });

        if (! $account instanceof FinCardAccount) {
            Log::warning('FinCard funding webhook for unknown account', ['account_id' => $accountId, 'event' => $eventType]);

            return;
        }

        FinCardAccountFunded::dispatch(
            (int) $account->user_id,
            $account->fincard_account_id,
            $account->balance_cents,
            $isDeposit ? $amountCents : null,
            $this->stringOrNull($data['coinKey'] ?? null),
        );
    }

    /**
     * Convert a decimal amount string to integer minor units via bcmath.
     */
    private function toCents(string $amount): int
    {
        if (! is_numeric($amount)) {
            return 0;
        }

        return (int) bcmul(bcadd($amount, '0', 2), '100', 0);
    }

    private function stringOrNull(mixed $value): ?string
    {
        return $value === null || $value === '' ? null : (string) $value;
    }
}
