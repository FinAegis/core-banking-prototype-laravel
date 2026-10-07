<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Ledger;

use App\Domain\Ledger\Models\JournalEntry;
use App\Domain\Ledger\Services\LedgerService;
use App\Support\StaffRoles;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

final class ReverseEntryMutation
{
    public function __construct(
        private readonly LedgerService $ledgerService,
    ) {
    }

    /**
     * Reverse a posted journal entry.
     *
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): JournalEntry
    {
        // The general ledger is kept by platform operators.
        if (! StaffRoles::userHasAny(Auth::user(), StaffRoles::OPERATORS)) {
            throw new AuthorizationException('You are not authorized to manage the general ledger.');
        }

        return $this->ledgerService->reverse(
            entryId: (string) $args['id'],
            reason: (string) $args['reason'],
        );
    }
}
