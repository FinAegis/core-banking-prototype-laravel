<?php

namespace App\Domain\Account\Repositories;

use App\Models\Account;
use \App\Domain\Account\DataObjects\Account as AccountDTO;

final class AccountRepository
{
    public function __construct(
        protected Account $account
    ) {}

    /**
     * @param AccountDTO $account
     *
     * @return Account
     */
    public function create(AccountDTO $account): Account
    {
        return $this->account->create($account->toArray());
    }

    /**
     * @param string $uuid
     *
     * @return Account
     */
    public function findByUuid(string $uuid): Account
    {
        return $this->account->where('uuid', $uuid)->first();
    }
}