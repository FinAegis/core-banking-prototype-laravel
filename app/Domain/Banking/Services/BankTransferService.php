<?php

declare(strict_types=1);

namespace App\Domain\Banking\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Complete inter-bank transfer workflow with status tracking.
 *
 * Manages the full lifecycle: initiate -> pending -> processing -> completed/failed.
 * Uses a state machine pattern with polling-based status updates.
 */
class BankTransferService
{
    private const STATUS_INITIATED = 'initiated';

    private const STATUS_PENDING = 'pending';

    private const STATUS_PROCESSING = 'processing';

    private const STATUS_COMPLETED = 'completed';

    private const STATUS_FAILED = 'failed';

    private const STATUS_CANCELLED = 'cancelled';

    private const CACHE_PREFIX = 'bank_transfer:';

    private const CACHE_TTL = 3600; // 1 hour

    /**
     * Initiate a new bank transfer.
     *
     * @param  array<string, mixed>  $params
     * @return array{transfer_id: string, status: string, reference: string, estimated_completion: string}
     */
    public function initiate(array $params): array
    {
        $transferId = 'bt_' . Str::uuid()->toString();
        $reference = $params['reference'] ?? strtoupper(Str::random(12));

        $transfer = [
            'id'              => $transferId,
            'from_account_id' => (string) ($params['from_account_id'] ?? ''),
            'to_account_id'   => (string) ($params['to_account_id'] ?? ''),
            'to_iban'         => (string) ($params['to_iban'] ?? ''),
            'to_bank_code'    => (string) ($params['to_bank_code'] ?? ''),
            'amount'          => (float) ($params['amount'] ?? 0),
            'currency'        => (string) ($params['currency'] ?? 'EUR'),
            'type'            => $this->determineTransferType($params),
            'reference'       => $reference,
            'description'     => (string) ($params['description'] ?? ''),
            'status'          => self::STATUS_INITIATED,
            'status_history'  => [
                ['status' => self::STATUS_INITIATED, 'at' => now()->toIso8601String(), 'note' => 'Transfer initiated'],
            ],
            'created_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
        ];

        DB::table('bank_transfers')->insert([
            'id'              => $transferId,
            'user_uuid'       => $params['user_uuid'] ?? '',
            'from_bank_code'  => $params['from_bank_code'] ?? '',
            'from_account_id' => $transfer['from_account_id'],
            'to_bank_code'    => $transfer['to_bank_code'],
            'to_account_id'   => $transfer['to_account_id'],
            'amount'          => $transfer['amount'],
            'currency'        => $transfer['currency'],
            'type'            => $transfer['type'],
            'status'          => self::STATUS_INITIATED,
            'metadata'        => json_encode([
                'reference'      => $reference,
                'description'    => $transfer['description'],
                'to_iban'        => $transfer['to_iban'],
                'status_history' => $transfer['status_history'],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Cache for fast status lookups
        Cache::put(self::CACHE_PREFIX . $transferId, $transfer, self::CACHE_TTL);

        Log::info('Bank transfer initiated', [
            'transfer_id' => $transferId,
            'amount'      => $transfer['amount'],
            'currency'    => $transfer['currency'],
            'type'        => $transfer['type'],
        ]);

        return [
            'transfer_id'          => $transferId,
            'status'               => self::STATUS_INITIATED,
            'reference'            => $reference,
            'estimated_completion' => $this->estimateCompletion($transfer['type']),
        ];
    }

    /**
     * Get the current status of a transfer.
     *
     * @return array{transfer_id: string, status: string, amount: float, currency: string, reference: string, type: string, status_history: array<array<string, string>>, created_at: string, updated_at: string}
     */
    public function getStatus(string $transferId): array
    {
        // Try cache first
        $cached = Cache::get(self::CACHE_PREFIX . $transferId);
        if (is_array($cached)) {
            return [
                'transfer_id'    => $transferId,
                'status'         => (string) $cached['status'],
                'amount'         => (float) $cached['amount'],
                'currency'       => (string) $cached['currency'],
                'reference'      => (string) $cached['reference'],
                'type'           => (string) $cached['type'],
                'status_history' => (array) $cached['status_history'],
                'created_at'     => (string) $cached['created_at'],
                'updated_at'     => (string) $cached['updated_at'],
            ];
        }

        // Fallback to database
        $record = DB::table('bank_transfers')->where('id', $transferId)->first();

        if ($record === null) {
            return [
                'transfer_id'    => $transferId,
                'status'         => 'not_found',
                'amount'         => 0,
                'currency'       => '',
                'reference'      => '',
                'type'           => '',
                'status_history' => [],
                'created_at'     => '',
                'updated_at'     => '',
            ];
        }

        /** @var array<string, mixed> $metadata */
        $metadata = json_decode((string) $record->metadata, true) ?? [];

        return [
            'transfer_id'    => $transferId,
            'status'         => (string) $record->status,
            'amount'         => (float) $record->amount,
            'currency'       => (string) $record->currency,
            'reference'      => (string) ($metadata['reference'] ?? ''),
            'type'           => (string) $record->type,
            'status_history' => (array) ($metadata['status_history'] ?? []),
            'created_at'     => (string) $record->created_at,
            'updated_at'     => (string) $record->updated_at,
        ];
    }

    /**
     * Advance a transfer to the next status.
     */
    public function advanceStatus(string $transferId, string $newStatus, string $note = ''): bool
    {
        $allowedTransitions = [
            self::STATUS_INITIATED  => [self::STATUS_PENDING, self::STATUS_FAILED, self::STATUS_CANCELLED],
            self::STATUS_PENDING    => [self::STATUS_PROCESSING, self::STATUS_FAILED, self::STATUS_CANCELLED],
            self::STATUS_PROCESSING => [self::STATUS_COMPLETED, self::STATUS_FAILED],
        ];

        $current = $this->getStatus($transferId);

        if ($current['status'] === 'not_found') {
            return false;
        }

        $allowed = $allowedTransitions[$current['status']] ?? [];
        if (! in_array($newStatus, $allowed, true)) {
            Log::warning('Invalid transfer status transition', [
                'transfer_id' => $transferId,
                'from'        => $current['status'],
                'to'          => $newStatus,
            ]);

            return false;
        }

        $statusHistory = $current['status_history'];
        $statusHistory[] = [
            'status' => $newStatus,
            'at'     => now()->toIso8601String(),
            'note'   => $note ?: "Status changed to {$newStatus}",
        ];

        DB::table('bank_transfers')
            ->where('id', $transferId)
            ->update([
                'status'   => $newStatus,
                'metadata' => json_encode(array_merge(
                    json_decode((string) (DB::table('bank_transfers')->where('id', $transferId)->value('metadata') ?? '{}'), true) ?? [],
                    ['status_history' => $statusHistory]
                )),
                'updated_at' => now(),
            ]);

        // Update cache
        $cached = Cache::get(self::CACHE_PREFIX . $transferId);
        if (is_array($cached)) {
            $cached['status'] = $newStatus;
            $cached['status_history'] = $statusHistory;
            $cached['updated_at'] = now()->toIso8601String();
            Cache::put(self::CACHE_PREFIX . $transferId, $cached, self::CACHE_TTL);
        }

        Log::info('Bank transfer status advanced', [
            'transfer_id' => $transferId,
            'from'        => $current['status'],
            'to'          => $newStatus,
        ]);

        return true;
    }

    /**
     * Cancel a transfer (only if not yet processing).
     */
    public function cancel(string $transferId, string $reason = ''): bool
    {
        return $this->advanceStatus($transferId, self::STATUS_CANCELLED, $reason ?: 'Cancelled by user');
    }

    /**
     * List transfers for a user with optional status filter.
     *
     * @return array<array<string, mixed>>
     */
    public function listForUser(string $userUuid, ?string $status = null, int $limit = 20): array
    {
        $query = DB::table('bank_transfers')
            ->where('user_uuid', $userUuid)
            ->orderByDesc('created_at')
            ->limit($limit);

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query->get()->map(function ($record) {
            /** @var array<string, mixed> $metadata */
            $metadata = json_decode((string) $record->metadata, true) ?? [];

            return [
                'transfer_id' => $record->id,
                'status'      => $record->status,
                'amount'      => (float) $record->amount,
                'currency'    => $record->currency,
                'type'        => $record->type,
                'reference'   => $metadata['reference'] ?? '',
                'created_at'  => $record->created_at,
            ];
        })->toArray();
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function determineTransferType(array $params): string
    {
        $currency = strtoupper((string) ($params['currency'] ?? 'EUR'));
        $toIban = (string) ($params['to_iban'] ?? '');

        // SEPA for EUR within EU
        if ($currency === 'EUR' && $toIban !== '' && strlen($toIban) >= 15) {
            $amount = (float) ($params['amount'] ?? 0);

            return $amount <= 100000 ? 'SEPA_INSTANT' : 'SEPA';
        }

        // SWIFT for international
        return 'SWIFT';
    }

    private function estimateCompletion(string $type): string
    {
        return match ($type) {
            'SEPA_INSTANT' => now()->addMinutes(10)->toIso8601String(),
            'SEPA'         => now()->addHours(4)->toIso8601String(),
            'SWIFT'        => now()->addDays(2)->toIso8601String(),
            default        => now()->addDay()->toIso8601String(),
        };
    }
}
