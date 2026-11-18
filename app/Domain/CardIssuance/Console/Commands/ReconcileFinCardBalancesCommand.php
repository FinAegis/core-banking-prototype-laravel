<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Console\Commands;

use App\Domain\CardIssuance\Models\FinCardAccount;
use App\Domain\CardIssuance\Services\FinCardAccountService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reconcile cached FinCard account balances against FinCard's authoritative
 * figure. The mirror (`fincard_accounts.balance_cents`) is normally kept current
 * by wallet webhooks; this catches drift from a missed/failed webhook. Runs only
 * when FinCard is the active issuer.
 *
 * @see docs/superpowers/specs/2026-07-06-fincard-card-issuing-design.md §6.3
 */
final class ReconcileFinCardBalancesCommand extends Command
{
    protected $signature = 'fincard:reconcile-balances {--limit=500 : Max accounts to reconcile per run}';

    protected $description = 'Reconcile cached FinCard account balances against FinCard (drift from missed webhooks).';

    public function __construct(private readonly FinCardAccountService $accounts)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        if (config('cardissuance.default_issuer') !== 'fincard') {
            $this->info('CARD_ISSUER is not fincard — nothing to reconcile.');

            return self::SUCCESS;
        }

        $limit = max((int) $this->option('limit'), 1);
        $drift = 0;
        $errors = 0;
        $checked = 0;

        FinCardAccount::query()
            ->where('status', 'active')
            ->limit($limit)
            ->get()
            ->each(function (FinCardAccount $account) use (&$drift, &$errors, &$checked): void {
                $checked++;
                $before = (int) $account->balance_cents;

                try {
                    $this->accounts->syncBalance($account);
                } catch (Throwable $e) {
                    $errors++;
                    Log::error('FinCard balance reconcile failed', [
                        'account_id' => $account->fincard_account_id,
                        'msg'        => $e->getMessage(),
                    ]);

                    return;
                }

                if ((int) $account->balance_cents !== $before) {
                    $drift++;
                    Log::warning('FinCard account balance drift reconciled', [
                        'account_id' => $account->fincard_account_id,
                        'from_cents' => $before,
                        'to_cents'   => (int) $account->balance_cents,
                    ]);
                }
            });

        $this->info(sprintf('Reconciled %d account(s): %d drift-corrected, %d error(s).', $checked, $drift, $errors));

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
