<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Services;

use App\Domain\CardIssuance\Contracts\CardIssuerInterface;
use App\Domain\CardIssuance\Models\Card;
use App\Domain\CardIssuance\ValueObjects\CardTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Syncs card transactions from issuer webhooks and polling.
 *
 * Receives transaction events from Rain/Marqeta webhooks and persists them
 * to the local database. Also supports polling for missed transactions.
 */
class CardTransactionSyncService
{
    public function __construct(
        private readonly CardIssuerInterface $issuer,
    ) {
    }

    /**
     * Process a transaction webhook payload from the card issuer.
     *
     * @param  array<string, mixed>  $payload  Raw webhook payload
     * @return array{synced: bool, transaction_id: string|null}
     */
    public function processWebhook(array $payload): array
    {
        $eventType = (string) ($payload['event_type'] ?? $payload['type'] ?? 'transaction.created');
        $txData = $payload['data'] ?? $payload;

        Log::info('Card transaction webhook received', [
            'event_type' => $eventType,
            'card_id'    => $txData['card_id'] ?? $txData['card_token'] ?? 'unknown',
        ]);

        return match ($eventType) {
            'transaction.created', 'transaction.updated' => $this->syncTransaction($txData),
            'transaction.settled'                        => $this->settleTransaction($txData),
            'transaction.declined'                       => $this->recordDeclinedTransaction($txData),
            'transaction.reversed'                       => $this->reverseTransaction($txData),
            default                                      => ['synced' => false, 'transaction_id' => null],
        };
    }

    /**
     * Poll the issuer API for recent transactions and sync any missing ones.
     *
     * @return array{synced_count: int, card_count: int}
     */
    public function pollAndSync(?string $userId = null): array
    {
        $query = Card::where('status', 'active');
        if ($userId !== null) {
            $query->where('user_id', $userId);
        }

        $cards = $query->get();
        $syncedCount = 0;

        foreach ($cards as $card) {
            try {
                $result = $this->issuer->getTransactions($card->issuer_card_token, 50);

                foreach ($result['transactions'] as $tx) {
                    if ($this->upsertTransaction($card, $tx)) {
                        $syncedCount++;
                    }
                }
            } catch (Throwable $e) {
                Log::warning('Card transaction poll failed', [
                    'card_id' => $card->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        }

        return [
            'synced_count' => $syncedCount,
            'card_count'   => $cards->count(),
        ];
    }

    /**
     * Sync a single transaction from webhook data.
     *
     * @param  array<string, mixed>  $txData
     * @return array{synced: bool, transaction_id: string|null}
     */
    private function syncTransaction(array $txData): array
    {
        $cardToken = (string) ($txData['card_id'] ?? $txData['card_token'] ?? '');
        $card = Card::where('issuer_card_token', $cardToken)->first();

        if ($card === null) {
            Log::warning('Card not found for transaction sync', ['card_token' => $cardToken]);

            return ['synced' => false, 'transaction_id' => null];
        }

        $transactionId = (string) ($txData['id'] ?? $txData['transaction_id'] ?? '');

        DB::table('card_transactions')->updateOrInsert(
            ['external_id' => $transactionId],
            [
                'card_id'           => $card->id,
                'user_id'           => $card->user_id,
                'external_id'       => $transactionId,
                'merchant_name'     => (string) ($txData['merchant_name'] ?? $txData['merchant']['name'] ?? 'Unknown'),
                'merchant_category' => (string) ($txData['merchant_category_code'] ?? $txData['merchant']['mcc'] ?? ''),
                'amount_cents'      => (int) ($txData['amount'] ?? 0),
                'currency'          => (string) ($txData['currency'] ?? 'USD'),
                'status'            => $this->mapTransactionStatus((string) ($txData['status'] ?? 'pending')),
                'transacted_at'     => $txData['created_at'] ?? $txData['timestamp'] ?? now(),
                'updated_at'        => now(),
            ]
        );

        return ['synced' => true, 'transaction_id' => $transactionId];
    }

    /**
     * Mark a transaction as settled.
     *
     * @param  array<string, mixed>  $txData
     * @return array{synced: bool, transaction_id: string|null}
     */
    private function settleTransaction(array $txData): array
    {
        $transactionId = (string) ($txData['id'] ?? $txData['transaction_id'] ?? '');

        $updated = DB::table('card_transactions')
            ->where('external_id', $transactionId)
            ->update([
                'status'       => 'settled',
                'amount_cents' => (int) ($txData['final_amount'] ?? $txData['amount'] ?? 0),
                'updated_at'   => now(),
            ]);

        if ($updated === 0) {
            return $this->syncTransaction($txData);
        }

        return ['synced' => true, 'transaction_id' => $transactionId];
    }

    /**
     * Record a declined transaction.
     *
     * @param  array<string, mixed>  $txData
     * @return array{synced: bool, transaction_id: string|null}
     */
    private function recordDeclinedTransaction(array $txData): array
    {
        $txData['status'] = 'declined';

        return $this->syncTransaction($txData);
    }

    /**
     * Reverse a previously settled transaction.
     *
     * @param  array<string, mixed>  $txData
     * @return array{synced: bool, transaction_id: string|null}
     */
    private function reverseTransaction(array $txData): array
    {
        $transactionId = (string) ($txData['original_transaction_id'] ?? $txData['id'] ?? '');

        DB::table('card_transactions')
            ->where('external_id', $transactionId)
            ->update([
                'status'     => 'reversed',
                'updated_at' => now(),
            ]);

        return ['synced' => true, 'transaction_id' => $transactionId];
    }

    /**
     * Upsert a transaction from API polling.
     */
    private function upsertTransaction(Card $card, CardTransaction $tx): bool
    {
        $exists = DB::table('card_transactions')
            ->where('external_id', $tx->transactionId)
            ->exists();

        if ($exists) {
            return false;
        }

        DB::table('card_transactions')->insert([
            'card_id'           => $card->id,
            'user_id'           => $card->user_id,
            'external_id'       => $tx->transactionId,
            'merchant_name'     => $tx->merchantName,
            'merchant_category' => $tx->merchantCategory,
            'amount_cents'      => $tx->amountCents,
            'currency'          => $tx->currency,
            'status'            => $tx->status,
            'transacted_at'     => $tx->timestamp,
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        return true;
    }

    private function mapTransactionStatus(string $issuerStatus): string
    {
        return match (strtolower($issuerStatus)) {
            'settled', 'completed', 'cleared' => 'settled',
            'declined', 'rejected'            => 'declined',
            'reversed', 'refunded'            => 'reversed',
            default                           => 'pending',
        };
    }
}
