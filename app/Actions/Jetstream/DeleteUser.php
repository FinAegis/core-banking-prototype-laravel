<?php

namespace App\Actions\Jetstream;

use App\Domain\Compliance\Services\AccountClosureService;
use App\Models\User;
use Laravel\Jetstream\Contracts\DeletesUsers;

class DeleteUser implements DeletesUsers
{
    /**
     * Create a new action instance.
     */
    public function __construct(protected AccountClosureService $accountClosure)
    {
    }

    /**
     * Delete the given user from the web profile page, the same way the app
     * and the GDPR endpoint do: the personal data is erased through
     * GdprService, the records the law requires are kept and every sign-in
     * ends afterwards. The user row stays as an anonymous entry, and so do
     * the user's teams, because accounts and their records point at them.
     */
    public function delete(User $user): void
    {
        $this->accountClosure->close($user, AccountClosureService::SOURCE_WEB);
    }
}
