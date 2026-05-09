<?php

/**
 * ProjectRevenueOutbox — Plan B Backend-Q3 outbox worker stub.
 *
 * Reads `pending` rows from revenue_outbox_events, projects them into
 * revenue_events, marks delivered. Idempotent on (source_type, source_event_id);
 * a re-run on a delivered row is a no-op.
 *
 * This is the SLICE-1 STUB. Slice 4 wires the full delayed-job infrastructure
 * (Backend-Q8 cue dispatch architecture). For now we expose handle() as the
 * worker entry point, run from an artisan command and from the webhook
 * handler's same-request projection-flush.
 *
 * @see docs/adr/0002-revenue-projection-dual-upstream.md
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Jobs;

use App\Domain\Subscription\Models\RevenueEvent;
use App\Domain\Subscription\Models\RevenueOutboxEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

final class ProjectRevenueOutbox implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $backoff = 30;

    public function __construct(public readonly ?int $rowId = null)
    {
    }

    public function handle(): void
    {
        if ($this->rowId !== null) {
            $this->processRow($this->rowId);

            return;
        }

        // Sweep mode: process all pending rows. Slice 1 stub keeps this simple
        // (sequential, single-process). Slice 4 will fan out via per-row dispatch.
        $maxAttempts = (int) config('subscription.outbox.max_attempts', 5);

        RevenueOutboxEvent::query()
            ->where('status', RevenueOutboxEvent::STATUS_PENDING)
            ->where('attempts', '<', $maxAttempts)
            ->orderBy('created_at')
            ->chunkById(100, function ($rows): void {
                foreach ($rows as $row) {
                    $this->processRow((int) $row->id);
                }
            });
    }

    private function processRow(int $rowId): void
    {
        /** @var RevenueOutboxEvent|null $row */
        $row = RevenueOutboxEvent::query()->lockForUpdate()->find($rowId);

        if ($row === null || $row->status === RevenueOutboxEvent::STATUS_DELIVERED) {
            return;
        }

        $row->forceFill(['attempts' => $row->attempts + 1])->save();

        try {
            $eventType = $this->mapOutboxKindToRevenueEvent($row->source_type, $row->event_kind);

            if ($eventType === null) {
                // Unmapped event kind — treat as delivered (no-op) so we don't
                // retry forever. Log so ops can backfill if needed.
                Log::info('revenue_outbox.unmapped_event', [
                    'source_type' => $row->source_type,
                    'event_kind'  => $row->event_kind,
                ]);

                $row->forceFill([
                    'status'       => RevenueOutboxEvent::STATUS_DELIVERED,
                    'delivered_at' => now(),
                ])->save();

                return;
            }

            $payload = $row->payload;
            $userId = isset($payload['userId']) && is_int($payload['userId']) ? $payload['userId'] : null;
            $aggregateId = isset($payload['aggregateId']) ? (string) $payload['aggregateId'] : null;
            $amount = isset($payload['amount']) ? (int) $payload['amount'] : 0;
            $decimals = isset($payload['decimals']) ? (int) $payload['decimals'] : 2;
            $denomination = isset($payload['denomination']) ? (string) $payload['denomination'] : 'EUR';
            $emittedAt = isset($payload['emittedAt']) ? (string) $payload['emittedAt'] : now()->toIso8601String();

            // Idempotent insert. uniq_revenue_event_source covers
            // (source_type, source_event_id, event_type).
            RevenueEvent::query()->updateOrCreate(
                [
                    'source_type'     => $row->source_type,
                    'source_event_id' => $row->source_event_id,
                    'event_type'      => $eventType,
                ],
                [
                    'user_id'      => $userId,
                    'user_id_hash' => $userId !== null
                        ? hash_hmac('sha256', (string) $userId, (string) config('app.key'))
                        : null,
                    'aggregate_id'        => $aggregateId,
                    'amount'              => $amount,
                    'amount_decimals'     => $decimals,
                    'amount_denomination' => $denomination,
                    'payload'             => $payload,
                    'emitted_at'          => $emittedAt,
                ]
            );

            $row->forceFill([
                'status'       => RevenueOutboxEvent::STATUS_DELIVERED,
                'delivered_at' => now(),
            ])->save();
        } catch (Throwable $e) {
            Log::error('revenue_outbox.project_failed', [
                'row_id'   => $rowId,
                'error'    => $e->getMessage(),
                'attempts' => $row->attempts,
            ]);

            if ($row->attempts >= (int) config('subscription.outbox.max_attempts', 5)) {
                $row->forceFill([
                    'status'        => RevenueOutboxEvent::STATUS_FAILED,
                    'failed_reason' => $e->getMessage(),
                ])->save();
            }
        }
    }

    private function mapOutboxKindToRevenueEvent(string $sourceType, string $eventKind): ?string
    {
        if ($sourceType !== RevenueOutboxEvent::SOURCE_STRIPE) {
            return null;
        }

        return match ($eventKind) {
            'customer.subscription.created' => RevenueEvent::TYPE_SUBSCRIPTION_INITIAL,
            'invoice.payment_succeeded'     => RevenueEvent::TYPE_SUBSCRIPTION_RENEWAL,
            'charge.refunded'               => RevenueEvent::TYPE_REFUND,
            default                         => null,
        };
    }
}
