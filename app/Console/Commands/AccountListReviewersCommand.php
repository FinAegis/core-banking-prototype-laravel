<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\AccountProvisioning\Models\AccountFlag;
use Illuminate\Console\Command;

class AccountListReviewersCommand extends Command
{
    /** @var string */
    protected $signature = 'account:list-reviewers {--json}';

    /** @var string */
    protected $description = 'List all review accounts with flag status + expiry';

    public function handle(): int
    {
        $rows = AccountFlag::where('is_review_account', true)
            ->with('user', 'createdBy')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (AccountFlag $f): array {
                $userEmail = $f->user instanceof \App\Models\User ? $f->user->email : null;
                $creatorEmail = $f->createdBy instanceof \App\Models\User ? $f->createdBy->email : null;

                return [
                    'email'       => $userEmail,
                    'active'      => $f->isActive() ? 'yes' : 'no',
                    'expires_at'  => $f->expires_at?->toDateString() ?? '—',
                    'disabled_at' => $f->disabled_at?->toIso8601String() ?? '—',
                    'operator'    => $creatorEmail ?? '—',
                    'note'        => $f->note ?? '—',
                ];
            });

        if ($this->option('json')) {
            $this->line((string) $rows->toJson(JSON_PRETTY_PRINT));

            return 0;
        }

        if ($rows->isEmpty()) {
            $this->info('No review accounts found.');

            return 0;
        }

        $this->table(['Email', 'Active', 'Expires', 'Disabled', 'Operator', 'Note'], $rows->toArray());

        return 0;
    }
}
