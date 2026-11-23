<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Providers\Lepo;

use App\Domain\CardIssuance\Models\CardDepositAddress;
use App\Domain\CardIssuance\Models\CardFundingAccount;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Models\CardTopUp;
use App\Domain\CardIssuance\Providers\CardProviderException;
use App\Infrastructure\Lepo\LepoApiException;
use App\Infrastructure\Lepo\LepoClient;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

/**
 * Finds the USDC deposit that funds a top-up in Lepo's crypto transfers.
 *
 * A deposit counts only when Lepo reports it completed, it went to the
 * top-up's deposit address on the top-up's network, and it is USDC. With
 * the wallet's transaction hash the match is exact; without it, the amount
 * must be within 1% of the top-up (a deposit outside that is picked up as
 * an external deposit instead). One deposit never funds two top-ups.
 */
final class LepoDepositMatcher
{
    private const COMPLETED = ['completed', 'complete', 'success', 'succeeded'];

    /** Amount tolerance when there is no transaction hash: 1%. */
    private const TOLERANCE = '0.01';

    private const PAGE_SIZE = 100;

    private const MAX_PAGES = 20;

    public function __construct(
        private readonly LepoClient $client,
        private readonly LepoTopUps $topups,
    ) {
    }

    public function detect(CardTopUp $topup): bool
    {
        if (! in_array($topup->status, [CardTopUp::STATUS_AWAITING_DEPOSIT, CardTopUp::STATUS_EXPIRED], true)) {
            return false;
        }

        $userId = $this->lepoUserId($topup);
        $window = ['startTime' => ($topup->created_at ?? now())->copy()->subMinutes(5)->getTimestampMs(), 'limit' => 50];

        // The wallet's hash matches exactly; when Lepo finds nothing for it
        // (it may index another hash), the amount within 1% still does. The
        // filter alone is not trusted: a row that names its hash must name
        // ours, and a row without one must also fit the amount.
        if ($topup->tx_hash !== null) {
            $expected = LepoTopUps::number((string) $topup->amount);
            foreach ($this->deposits($userId, ['transactionHash' => $topup->tx_hash] + $window) as $deposit) {
                $sameTransaction = $deposit['hash'] !== null
                    ? $deposit['hash'] === strtolower($topup->tx_hash)
                    : $this->closeEnough($deposit['amount'], $expected);

                if ($sameTransaction && $this->isFor($deposit, $topup->deposit_address, $topup->network) && $this->claim($topup, $deposit)) {
                    return true;
                }
            }
        }

        foreach ($this->deposits($userId, $window) as $deposit) {
            if (
                $this->isFor($deposit, $topup->deposit_address, $topup->network)
                && $this->closeEnough($deposit['amount'], LepoTopUps::number((string) $topup->amount))
                && $this->claim($topup, $deposit)
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Links the deposit with a conditional write, so a top-up cancelled at the
     * same moment, or a deposit another top-up claimed, is never overwritten.
     *
     * @param  array{id: string, amount: numeric-string, network: string|null, address: string, hash: string|null}  $deposit
     */
    private function claim(CardTopUp $topup, array $deposit): bool
    {
        if ($this->linked($deposit['id'])) {
            return false;
        }

        try {
            $changed = CardTopUp::query()->whereKey($topup->id)
                ->whereIn('status', [CardTopUp::STATUS_AWAITING_DEPOSIT, CardTopUp::STATUS_EXPIRED])
                ->whereNull('provider_deposit_id')
                ->update([
                    'status'              => CardTopUp::STATUS_DEPOSIT_RECEIVED,
                    'provider_deposit_id' => $deposit['id'],
                    'received_amount'     => $deposit['amount'],
                    'step_started_at'     => null,
                    'last_error'          => null,
                    'updated_at'          => now(),
                ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        if ($changed !== 1) {
            return false;
        }

        $topup->refresh();
        $this->topups->announce($topup);

        return true;
    }

    /**
     * Completed USDC deposits no top-up has claimed, to any address the
     * cardholder was ever given (a retired address still belongs to them),
     * since their funding account was opened, across all pages.
     *
     * @return list<array{id: string, amount: string, network: string, address: string}>
     */
    public function findUnmatched(Cardholder $cardholder, ?Carbon $since = null): array
    {
        $accounts = CardFundingAccount::query()->where('cardholder_id', $cardholder->id);
        $addresses = CardDepositAddress::query()
            ->whereIn('funding_account_id', (clone $accounts)->select('id'))
            ->where('asset', 'USDC')
            ->get(['network', 'address']);

        $userId = (string) $cardholder->issuer_cardholder_id;
        $opened = (clone $accounts)->min('created_at');
        if ($userId === '' || $addresses->isEmpty()) {
            return [];
        }

        $since ??= ($opened !== null ? Carbon::parse((string) $opened) : now()->subDays(7))->subDay();
        $rows = [];
        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $batch = $this->deposits($userId, ['startTime' => $since->getTimestampMs(), 'limit' => self::PAGE_SIZE, 'page' => $page], $raw);
            $rows = [...$rows, ...$batch];
            if ($raw < self::PAGE_SIZE) {
                break;
            }
        }

        $found = [];
        foreach ($rows as $deposit) {
            foreach ($addresses as $address) {
                if ($this->isFor($deposit, $address->address, $address->network) && ! $this->linked($deposit['id'])) {
                    $found[] = ['id' => $deposit['id'], 'amount' => $deposit['amount'], 'network' => $address->network, 'address' => $address->address];

                    break;
                }
            }
        }

        return $found;
    }

    /**
     * Completed USDC transfers, normalised.
     *
     * @param  array<string, scalar>  $filters
     * @param  int|null  $raw  set to the number of rows Lepo returned (for paging)
     * @param-out int  $raw
     * @return list<array{id: string, amount: numeric-string, network: string|null, address: string, hash: string|null}>
     */
    private function deposits(string $userId, array $filters, ?int &$raw = null): array
    {
        try {
            $rows = $this->client->getCryptoTransactions($userId, $filters)['transfers'] ?? [];
        } catch (LepoApiException $e) {
            throw CardProviderException::providerUnavailable('lepo', $e->lepoCode());
        }
        $raw = is_array($rows) ? count($rows) : 0;

        $deposits = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = $row['id'] ?? null;
            $destination = is_array($row['destination'] ?? null) ? $row['destination'] : [];
            $amount = is_array($row['amount'] ?? null) ? $row['amount'] : [];
            $value = $this->decimal($amount['amount'] ?? null);

            if (
                ! is_scalar($id) || (string) $id === '' || $value === null
                || strtoupper((string) ($amount['currency'] ?? '')) !== 'USDC'
                || ! in_array(strtolower((string) ($row['status'] ?? '')), self::COMPLETED, true)
            ) {
                continue;
            }

            // Lepo's documented row has no hash; read one if it is sent.
            $metadata = is_array($row['metadata'] ?? null) ? $row['metadata'] : [];
            $hash = $row['transactionHash'] ?? $row['txHash'] ?? $row['hash'] ?? $metadata['transaction_hash'] ?? null;

            $deposits[] = [
                'id'      => (string) $id,
                'amount'  => $value,
                'network' => LepoCardOnboarding::NETWORKS[strtolower(trim((string) ($destination['chain'] ?? '')))] ?? null,
                'address' => strtolower(trim((string) ($destination['address'] ?? ''))),
                'hash'    => is_string($hash) && trim($hash) !== '' ? strtolower(trim($hash)) : null,
            ];
        }

        return $deposits;
    }

    /**
     * @param  array{id: string, amount: numeric-string, network: string|null, address: string, hash: string|null}  $deposit
     */
    private function isFor(array $deposit, string $address, string $network): bool
    {
        return $deposit['address'] !== '' && $deposit['address'] === strtolower($address) && $deposit['network'] === $network;
    }

    private function linked(string $depositId): bool
    {
        return CardTopUp::query()->where('provider', 'lepo')->where('provider_deposit_id', $depositId)->exists();
    }

    /**
     * @param  numeric-string  $received
     * @param  numeric-string  $expected
     */
    private function closeEnough(string $received, string $expected): bool
    {
        $allowed = bcmul($expected, self::TOLERANCE, 6);
        $difference = bcsub($received, $expected, 6);
        if (bccomp($difference, '0', 6) === -1) {
            $difference = bcmul($difference, '-1', 6);
        }

        return bccomp($difference, $allowed, 6) <= 0;
    }

    private function lepoUserId(CardTopUp $topup): string
    {
        $holder = $topup->card()->first()?->cardholder()->first();
        $id = $holder instanceof Cardholder ? (string) $holder->issuer_cardholder_id : '';
        if ($id === '') {
            throw CardProviderException::providerUnavailable('lepo', 'NO_USER_ID');
        }

        return $id;
    }

    /**
     * A Lepo amount (JSON number or decimal string) with 6 decimals.
     *
     * @return numeric-string|null
     */
    private function decimal(mixed $value): ?string
    {
        if (is_float($value)) {
            $value = is_finite($value) ? sprintf('%.6F', $value) : null;
        }

        $text = is_string($value) || is_int($value) ? trim((string) $value) : '';

        return preg_match('/^\d+(\.\d+)?$/', $text) === 1 && is_numeric($text) ? bcadd($text, '0', 6) : null;
    }
}
