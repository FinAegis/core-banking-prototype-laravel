<?php

namespace App\Domain\Account\Repositories;

use App\Domain\Account\DataObjects\Account as AccountDTO;
use App\Domain\Account\Models\Account;
use Illuminate\Support\LazyCollection;
use InvalidArgumentException;

final class AccountRepository
{
    public function __construct(
        protected Account $account
    ) {
    }

    public function create(AccountDTO $account): Account
    {
        return $this->account->create($account->toArray());
    }

    /**
     * Write the account row unless a row with its uuid exists, and return the
     * stored row. An existing row is left as it is: the endpoint that opens the
     * account writes it while the create-account workflow waits on the queue,
     * and a queue can deliver AccountCreated twice.
     */
    public function createOrFirst(AccountDTO $account): Account
    {
        $uuid = $account->getUuid();

        if ($uuid === null || $uuid === '') {
            throw new InvalidArgumentException('An account row needs the uuid of its aggregate.');
        }

        return $this->account->newQuery()
            ->withoutGlobalScopes()
            ->createOrFirst(['uuid' => $uuid], $account->toArray());
    }

    public function findByUuid(string $uuid): Account
    {
        return $this->account->where('uuid', $uuid)->firstOrFail();
    }

    public function getAllByCursor(): LazyCollection
    {
        return $this->account->cursor();
    }
}
