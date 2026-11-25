<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Console\Commands;

use App\Domain\Account\Models\BlockchainTransaction;
use App\Domain\CardIssuance\Models\CardWithdrawal;
use App\Domain\CardIssuance\Models\CardWithdrawalEvent;
use App\Domain\CardIssuance\Providers\Lepo\LepoWithdrawals;
use App\Infrastructure\Lepo\LepoApiException;
use App\Infrastructure\Lepo\LepoClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Support view of one withdrawal: the record, its audit trail, and a live
 * read-only view at Lepo (card balance, USD and USDC balances, matching
 * transactions) plus inbound transfers our wallet mirror saw on the pinned
 * address. Prints no verification token and never a code (we never have it).
 */
final class CardsInspectWithdrawalCommand extends Command
{
    protected $signature = 'cards:inspect-withdrawal {withdrawal : The withdrawal id} {--no-live : Skip the reads at Lepo}';

    protected $description = 'Print a card withdrawal, its audit trail and the live reads at Lepo (no secrets)';

    public function handle(LepoClient $client, LepoWithdrawals $withdrawals): int
    {
        $withdrawal = CardWithdrawal::query()->find((string) $this->argument('withdrawal'));
        if (! $withdrawal instanceof CardWithdrawal) {
            $this->error('No withdrawal with that id.');

            return self::FAILURE;
        }

        $fields = [
            'id', 'user_id', 'card_id', 'status', 'last_error', 'amount', 'withdraw_all', 'close_card', 'card_closed', 'network', 'destination_address', 'destination_address_uuid',
            'card_balance_before_cents', 'unloaded_cents', 'unload_fee_cents', 'conversion_fee_cents', 'usdc_balance_before', 'usdc_amount', 'withdrawal_fee', 'withdrawal_amount',
            'provider_actual_amount', 'received_amount', 'tx_hash', 'provider_unload_id', 'provider_trade_id', 'provider_withdrawal_id', 'provider_transfer_id', 'provider_status',
            'unload_key', 'exchange_key', 'withdrawal_key', 'confirm_key', 'withdrawal_attempts', 'step_started_at', 'unload_requested_at', 'unloaded_at', 'exchange_requested_at',
            'exchanged_at', 'withdrawal_requested_at', 'code_sent_at', 'code_expires_at', 'code_sent_to', 'code_attempts', 'code_attempts_left', 'code_resends', 'last_resend_at',
            'confirm_requested_at', 'confirmed_at', 'resumed_at', 'card_close_attempted_at', 'created_at', 'completed_at',
        ];
        $rows = array_map(fn (string $field): array => [$field, $this->show($withdrawal->getAttribute($field))], $fields);
        $rows[] = ['verification_token', $withdrawal->provider_verification_token === null ? '-' : '(set, not shown)'];
        $this->table(['Field', 'Value'], $rows);

        $this->table(
            ['At', 'Actor', 'Action', 'HTTP', 'From', 'To', 'Note'],
            CardWithdrawalEvent::query()->where('withdrawal_id', $withdrawal->id)->orderBy('id')->get()
                ->map(fn (CardWithdrawalEvent $event): array => [(string) $event->at, $event->actor, $event->action, (string) ($event->partner_http_status ?? '-'),
                    $event->from_status ?? '-', $event->to_status ?? '-', $event->note ?? '-'])->all(),
        );

        $this->table(
            ['Inbound transfer', 'Amount', 'Status', 'Seen'],
            BlockchainTransaction::query()->where('address_uuid', $withdrawal->destination_address_uuid)->where('chain', $withdrawal->network)->where('type', 'receive')
                ->where('created_at', '>=', $withdrawal->created_at ?? now()->subDay())->orderBy('created_at')->get()
                ->map(fn (BlockchainTransaction $tx): array => [$tx->tx_hash, (string) $tx->amount, $tx->status, (string) $tx->created_at])->all(),
        );

        if ((bool) $this->option('no-live')) {
            return self::SUCCESS;
        }

        try {
            $card = $withdrawal->card()->firstOrFail();
            $userId = $withdrawals->lepoUserId($card);
        } catch (Throwable) {
            $this->warn('Live reads skipped: no Lepo user for this card.');

            return self::SUCCESS;
        }

        $this->live('Card balance', fn (): array => [is_array($balance = $client->getCard((string) $card->issuer_card_token, $userId)['balance'] ?? null) ? $balance : []]);
        $this->live('Balances at Lepo', fn (): array => array_values(array_filter(
            is_array($assets = $client->getAssets($userId)['assets'] ?? null) ? $assets : [],
            fn ($asset): bool => is_array($asset) && in_array(strtoupper((string) ($asset['currency'] ?? '')), ['USD', 'USDC'], true),
        )));
        $this->live('Lepo transactions since the start', fn (): array => is_array($data = $client->getTransactions(
            $userId,
            ['card_unload', 'quantum_to_crypto_exchange', 'exchange', 'crypto_withdrawal'],
            ($withdrawal->created_at ?? now())->copy()->subMinutes(5)->toIso8601String(),
        )['data'] ?? null) ? $data : []);
        $this->live('Lepo crypto transfers since the start', fn (): array => is_array($transfers = $client->getCryptoTransactions($userId, [
            'startTime' => (string) ((($withdrawal->created_at ?? now())->getTimestamp() - 300) * 1000),
            'limit'     => 50,
        ])['transfers'] ?? null) ? $transfers : []);

        return self::SUCCESS;
    }

    /**
     * @param  callable(): array<mixed>  $read
     */
    private function live(string $title, callable $read): void
    {
        $this->line("<info>{$title}</info>");
        try {
            $rows = $read();
        } catch (LepoApiException $e) {
            $this->warn('  unreadable: ' . $e->getMessage());

            return;
        }

        if ($rows === []) {
            $this->line('  none');

            return;
        }

        foreach ($rows as $row) {
            $this->line('  ' . (string) json_encode($this->safe($row), JSON_UNESCAPED_SLASHES));
        }
    }

    /**
     * Only amounts, ids, types, statuses and dates: no personal data.
     */
    private function safe(mixed $row): mixed
    {
        if (! is_array($row)) {
            return $row;
        }

        $keep = ['id', 'type', 'status', 'amount', 'currency', 'feeAmount', 'transactionDate', 'cardId', 'tradeId', 'externalTransactionId', 'referenceId',
            'available', 'pending', 'frozen', 'availableBalance', 'balance', 'assetType', 'balanceType', 'destination', 'address', 'chain', 'createTime', 'completeTime'];

        $out = [];
        foreach ($row as $key => $value) {
            if (is_string($key) && in_array($key, $keep, true)) {
                $out[$key] = is_array($value) ? $this->safe($value) : $value;
            }
        }

        return $out;
    }

    private function show(mixed $value): string
    {
        return match (true) {
            $value === null  => '-',
            is_bool($value)  => $value ? 'yes' : 'no',
            is_array($value) => (string) json_encode($value),
            default          => (string) $value,
        };
    }
}
