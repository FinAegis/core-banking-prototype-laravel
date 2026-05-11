<?php

/**
 * cards:purge-expired-deposits — Plan B Slice 5 cron.
 *
 * Daily cleanup of card_waitlist_deposits rows whose Stripe Checkout session
 * was created >24h ago and is still in `pending_payment`. Stripe auto-expires
 * sessions after 24h by default; this command:
 *
 *   1. Lists candidate rows (status=pending_payment AND created_at < now-24h).
 *   2. For each, verifies with Stripe whether the session actually expired,
 *      completed late (webhook delayed), or is still alive.
 *   3. Transitions our DB row to match: expired | paid (if delayed completion)
 *      | leaves alone (still alive).
 *   4. Caps at config('cards.purge_per_run_cap'); warns on cap hit (backlog).
 *
 * @see docs/superpowers/specs/2026-05-10-slice-5-card-waitlist-deposit-design.md §5.8
 */

declare(strict_types=1);

namespace App\Domain\CardIssuance\Console\Commands;

use App\Domain\CardIssuance\Models\CardWaitlistDeposit;
use App\Domain\CardIssuance\Services\WaitlistDepositService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Throwable;

final class PurgeExpiredDepositsCommand extends Command
{
    /** @var string */
    protected $signature = 'cards:purge-expired-deposits {--cap= : Override the per-run cap}';

    /** @var string */
    protected $description = 'Mark Stripe Checkout sessions older than 24h as expired and sync any delayed completions.';

    public function __construct(
        private readonly WaitlistDepositService $service,
    ) {
        parent::__construct();
    }

    /**
     * Resolve Stripe lazily — keeps Artisan boot from eagerly instantiating
     * StripeClient via the AppServiceProvider binding (which errors when
     * cashier.secret is empty, e.g. CI tests).
     */
    private function stripe(): StripeClient
    {
        return app(StripeClient::class);
    }

    public function handle(): int
    {
        $capOption = $this->option('cap');
        $cap = is_numeric($capOption)
            ? max(1, (int) $capOption)
            : (int) config('cards.purge_per_run_cap', 1000);

        $ttlHours = (int) config('cards.session_ttl_hours', 24);
        $cutoff = now()->subHours($ttlHours);

        /** @var array<int, CardWaitlistDeposit> $candidates */
        $candidates = CardWaitlistDeposit::query()
            ->where('status', CardWaitlistDeposit::STATUS_PENDING_PAYMENT)
            ->where('created_at', '<', $cutoff)
            ->orderBy('created_at')
            ->limit($cap)
            ->get()
            ->all();

        $expired = 0;
        $delayedCompletion = 0;
        $stillAlive = 0;
        $errors = 0;

        foreach ($candidates as $deposit) {
            $sessionId = $deposit->stripe_checkout_session_id;
            if (! is_string($sessionId) || $sessionId === '') {
                // No session id (created locally but Stripe call failed) —
                // mark as expired so the user can retry.
                $deposit->update([
                    'status'     => CardWaitlistDeposit::STATUS_EXPIRED,
                    'expired_at' => now(),
                ]);
                $expired++;
                continue;
            }

            try {
                $session = $this->stripe()->checkout->sessions->retrieve($sessionId);
                $status = (string) ($session->status ?? '');
                $paymentStatus = (string) ($session->payment_status ?? '');

                if ($status === 'complete' && $paymentStatus === 'paid') {
                    // Webhook was delayed — replay the transition here. We
                    // don't dedup against processed_webhook_events because
                    // this isn't a webhook delivery; just hit the same
                    // service method idempotently.
                    $this->service->applyCheckoutCompleted(
                        $session->toArray(),
                        'cron_purge:' . $sessionId,
                    );
                    $delayedCompletion++;
                } elseif ($status === 'expired') {
                    $deposit->update([
                        'status'     => CardWaitlistDeposit::STATUS_EXPIRED,
                        'expired_at' => now(),
                    ]);
                    $expired++;
                } else {
                    // 'open' (still alive) or any unexpected status — leave
                    // the deposit alone; Stripe will auto-expire on its next
                    // tick and the following cron run will pick it up.
                    $stillAlive++;
                }
            } catch (Throwable $e) {
                $errors++;
                Log::warning('cards.deposit.purge.stripe_retrieve_failed', [
                    'deposit_id' => $deposit->id,
                    'session_id' => $sessionId,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        $hitCap = count($candidates) === $cap;
        if ($hitCap) {
            Log::warning('cards.deposit.purge.cap_hit', [
                'cap'        => $cap,
                'expired'    => $expired,
                'delayed_ok' => $delayedCompletion,
            ]);
        }

        $this->components->info(sprintf(
            'Purged %d expired / %d delayed-completion / %d still-alive / %d errors (cap=%d%s)',
            $expired,
            $delayedCompletion,
            $stillAlive,
            $errors,
            $cap,
            $hitCap ? ', cap hit' : '',
        ));

        return self::SUCCESS;
    }
}
