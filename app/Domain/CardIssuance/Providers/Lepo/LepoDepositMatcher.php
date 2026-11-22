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

        $filters = $topup->tx_hash !== null
            ? ['transactionHash' => $topup->tx_hash, 'limit' => 10]
            : ['startTime' => ($topup->created_at ?? now())->copy()->subMinutes(5)->getTimestampMs(), 'limit' => 50];

        foreach ($this->deposits($this->lepoUserId($topup), $filters) as $deposit) {
            if (! $this->isFor($deposit, $topup->deposit_address, $topup->network) || $this->linked($deposit['id'])) {
                continue;
            }

            if ($topup->tx_hash === null && ! $this->closeEnough($deposit['amount'], LepoTopUps::number((string) $topup->amount))) {
                continue;
            }

            $topup->forceFill([
                'status'              => CardTopUp::STATUS_DEPOSIT_RECEIVED,
                'provider_deposit_id' => $deposit['id'],
                'received_amount'     => $deposit['amount'],
                'step_started_at'     => null,
                'last_error'          => null,
            ])->save();
            $this->topups->announce($topup);

            return true;
        }

        return false;
    }

    /**
     * Completed USDC deposits of the last 7 days to the cardholder's active
     * addresses that no top-up has claimed.
     *
     * @return list<array{id: string, amount: string, network: string, address: string}>
     */
    public function findUnmatched(Cardholder $cardholder): array
    {
        $addresses = CardDepositAddress::query()
            ->whereIn('funding_account_id', CardFundingAccount::query()->where('cardholder_id', $cardholder->id)->select('id'))
            ->where('asset', 'USDC')
            ->where('is_active', true)
            ->get(['network', 'address']);

        $userId = (string) $cardholder->issuer_cardholder_id;
        if ($userId === '' || $addresses->isEmpty()) {
            return [];
        }

        $found = [];
        foreach ($this->deposits($userId, ['startTime' => now()->subDays(7)->getTimestampMs(), 'limit' => 100]) as $deposit) {
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
     * @return list<array{id: string, amount: numeric-string, network: string|null, address: string}>
     */
    private function deposits(string $userId, array $filters): array
    {
        try {
            $rows = $this->client->getCryptoTransactions($userId, $filters)['transfers'] ?? [];
        } catch (LepoApiException $e) {
            throw CardProviderException::providerUnavailable('lepo', $e->lepoCode());
        }

        $deposits = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = $row['id'] ?? null;
            $destination = is_array($row['destination'] ?? null) ? $row['destination'] : [];
            $amount = is_array($row['amount'] ?? null) ? $row['amount'] : [];
            $value = $this->decimal($amount['amount'] ?? null);

            if (! is_scalar($id) || (string) $id === '' || $value === null
                || strtoupper((string) ($amount['currency'] ?? '')) !== 'USDC'
                || ! in_array(strtolower((string) ($row['status'] ?? '')), self::COMPLETED, true)) {
                continue;
            }

            $deposits[] = [
                'id'      => (string) $id,
                'amount'  => $value,
                'network' => LepoCardOnboarding::NETWORKS[strtolower(trim((string) ($destination['chain'] ?? '')))] ?? null,
                'address' => strtolower(trim((string) ($destination['address'] ?? ''))),
            ];
        }

        return $deposits;
    }

    /**
     * @param  array{id: string, amount: numeric-string, network: string|null, address: string}  $deposit
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
