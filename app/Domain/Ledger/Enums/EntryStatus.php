<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

enum EntryStatus: string
{
    case DRAFT = 'draft';
    case POSTED = 'posted';
    case REVERSED = 'reversed';

    /**
     * Returns true if this status represents a terminal (immutable) state.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::POSTED, self::REVERSED => true,
            self::DRAFT                  => false,
        };
    }
}
