<?php

/**
 * CueRepository — single write path for all cue dispatch strategies (Plan B Backend-Q8).
 *
 * All three dispatch patterns (delayed job, aggregate-condition cron, direct webhook
 * insert) call createIdempotent(). The underlying INSERT ... ON DUPLICATE KEY UPDATE
 * makes every call a no-op on the second attempt — idempotency is structural, not
 * guarded.
 *
 * Constructor-injected everywhere. Never resolved via app() in hot paths.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-4-cue-queue-design.md §5.2
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Services;

use App\Domain\Subscription\Models\Cue;
use App\Domain\Subscription\Services\Preconditions\ExportReadyPrecondition;
use App\Domain\Subscription\Services\Preconditions\FamilySharingPrecondition;
use App\Domain\Subscription\Services\Preconditions\GracePeriodPrecondition;
use App\Domain\Subscription\Services\Preconditions\KycRequiredPrecondition;
use App\Domain\Subscription\Services\Preconditions\PaymentFailedPrecondition;
use App\Domain\Subscription\Services\Preconditions\ProTrialReminderPrecondition;
use App\Domain\Subscription\Services\Preconditions\TrialEndingPrecondition;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CueRepository
{
    /**
     * Kind registry: priority + render window seconds + precondition class.
     * render_window_seconds = null means no automatic expiry offset (precondition-reaped only).
     *
     * @var array<string, array{priority: string, render_window_seconds: int|null, precondition: class-string<CuePreconditionInterface>|null}>
     */
    private const KIND_REGISTRY = [
        'trial_ending_2d' => [
            'priority'              => 'high',
            'render_window_seconds' => 172_800,
            'precondition'          => TrialEndingPrecondition::class,
        ],
        'trial_ending_1d' => [
            'priority'              => 'high',
            'render_window_seconds' => 86_400,
            'precondition'          => TrialEndingPrecondition::class,
        ],
        'trial_ending_1h' => [
            'priority'              => 'high',
            'render_window_seconds' => 3_600,
            'precondition'          => TrialEndingPrecondition::class,
        ],
        'payment_failed' => [
            'priority'              => 'critical',
            'render_window_seconds' => 604_800,
            'precondition'          => PaymentFailedPrecondition::class,
        ],
        'subscription_canceled_external' => [
            'priority'              => 'normal',
            'render_window_seconds' => 604_800,
            'precondition'          => null,
        ],
        'refund_processed' => [
            'priority'              => 'high',
            'render_window_seconds' => 604_800,
            'precondition'          => null,
        ],
        'grace_period_started' => [
            'priority' => 'high',
            // ~16 day Apple retry window in seconds.
            'render_window_seconds' => 1_382_400,
            'precondition'          => GracePeriodPrecondition::class,
        ],
        'kyc_required' => [
            'priority'              => 'critical',
            'render_window_seconds' => 2_592_000,
            'precondition'          => KycRequiredPrecondition::class,
        ],
        'family_sharing_unsupported' => [
            'priority'              => 'normal',
            'render_window_seconds' => null,
            'precondition'          => FamilySharingPrecondition::class,
        ],
        'export_ready' => [
            'priority'              => 'normal',
            'render_window_seconds' => 604_800,
            'precondition'          => ExportReadyPrecondition::class,
        ],
        'pro_trial_reminder_d1' => [
            'priority'              => 'normal',
            'render_window_seconds' => 604_800,
            'precondition'          => ProTrialReminderPrecondition::class,
        ],
    ];

    /**
     * Idempotent cue creation.
     *
     * The idempotency_key is derived from {user_id}:{kind}:{occurrenceWindowStartIso8601}.
     * On a duplicate (same user+key), insertOrIgnore() silently skips the insert (MySQL
     * INSERT IGNORE / SQLite INSERT OR IGNORE). We then fetch the existing or new row.
     *
     * @param  array<string, mixed>  $payload
     */
    public function createIdempotent(
        User $user,
        string $kind,
        array $payload,
        string $occurrenceWindowStartIso8601,
    ): Cue {
        $config = self::KIND_REGISTRY[$kind] ?? null;
        if ($config === null) {
            throw new InvalidArgumentException(
                sprintf('Unknown cue kind "%s". Register it in CueRepository::KIND_REGISTRY.', $kind),
            );
        }

        $idempotencyKey = hash('sha256', sprintf('%s:%s:%s', $user->id, $kind, $occurrenceWindowStartIso8601));

        $priority = $config['priority'];
        $now = now();
        $dueAt = $now;
        $expiresAt = $config['render_window_seconds'] !== null
            ? $now->copy()->addSeconds($config['render_window_seconds'])
            : $now->copy()->addYears(1); // Far future for precondition-reaped-only kinds.

        /** @var Cue $cue */
        $cue = DB::transaction(function () use ($user, $kind, $priority, $dueAt, $expiresAt, $payload, $idempotencyKey): Cue {
            // insertOrIgnore skips on duplicate key (cross-database: MySQL INSERT IGNORE /
            // SQLite INSERT OR IGNORE). The unique index on (user_id, idempotency_key) is
            // the structural dedup guard — no try/catch needed.
            DB::table('cues')->insertOrIgnore([
                'id'              => (string) \Illuminate\Support\Str::uuid(),
                'user_id'         => $user->id,
                'kind'            => $kind,
                'priority'        => $priority,
                'due_at'          => $dueAt->toDateTimeString(),
                'expires_at'      => $expiresAt->toDateTimeString(),
                'payload'         => (string) json_encode($payload, JSON_THROW_ON_ERROR),
                'idempotency_key' => $idempotencyKey,
                'created_at'      => now()->toDateTimeString(),
            ]);

            /** @var Cue $row */
            $row = Cue::query()
                ->where('user_id', $user->id)
                ->where('idempotency_key', $idempotencyKey)
                ->firstOrFail();

            return $row;
        });

        return $cue;
    }

    /**
     * Return the precondition class for a given cue kind, or null if none.
     *
     * @return class-string<CuePreconditionInterface>|null
     */
    public function preconditionClassFor(string $kind): ?string
    {
        $config = self::KIND_REGISTRY[$kind] ?? null;
        if ($config === null) {
            return null;
        }

        /** @var class-string<CuePreconditionInterface>|null $class */
        $class = $config['precondition'];

        return $class;
    }
}
