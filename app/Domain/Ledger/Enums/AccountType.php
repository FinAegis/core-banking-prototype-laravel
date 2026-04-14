<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Enums;

enum AccountType: string
{
    case ASSET = 'asset';
    case LIABILITY = 'liability';
    case EQUITY = 'equity';
    case REVENUE = 'revenue';
    case EXPENSE = 'expense';

    /**
     * Returns the normal balance side for this account type.
     *
     * Assets and expenses increase with debits (debit normal).
     * Liabilities, equity, and revenue increase with credits (credit normal).
     */
    public function normalBalance(): string
    {
        return match ($this) {
            self::ASSET, self::EXPENSE                   => 'debit',
            self::LIABILITY, self::EQUITY, self::REVENUE => 'credit',
        };
    }
}
