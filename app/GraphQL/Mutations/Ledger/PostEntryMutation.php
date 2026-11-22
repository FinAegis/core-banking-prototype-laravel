<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Ledger;

use App\Domain\Ledger\Models\JournalEntry;
use App\Domain\Ledger\Services\LedgerService;
use App\Support\DecimalString;
use App\Support\StaffRoles;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

final class PostEntryMutation
{
    public function __construct(
        private readonly LedgerService $ledgerService,
    ) {
    }

    /**
     * Post a new journal entry.
     *
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): JournalEntry
    {
        // The general ledger is kept by platform operators.
        if (! StaffRoles::userHasAny(Auth::user(), StaffRoles::OPERATORS)) {
            throw new AuthorizationException('You are not authorized to manage the general ledger.');
        }

        $inputLines = is_array($args['lines']) ? $args['lines'] : [];

        $lines = [];
        foreach ($inputLines as $line) {
            if (! is_array($line)) {
                continue;
            }

            $entry = [
                'account_code' => (string) ($line['account_code'] ?? ''),
                // A missing or non-numeric amount counts as zero, as before;
                // LedgerService rejects an unbalanced or all-zero entry.
                'debit'  => DecimalString::fromNumeric($line['debit'] ?? null, 4) ?? '0.0000',
                'credit' => DecimalString::fromNumeric($line['credit'] ?? null, 4) ?? '0.0000',
            ];

            if (isset($line['narrative'])) {
                $entry['narrative'] = (string) $line['narrative'];
            }

            $lines[] = $entry;
        }

        return $this->ledgerService->post(
            description: (string) $args['description'],
            lines: $lines,
            sourceDomain: isset($args['source_domain']) ? (string) $args['source_domain'] : null,
        );
    }
}
