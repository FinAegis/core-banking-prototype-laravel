<?php

namespace App\Listeners;

use App\Domain\Account\DataObjects\CreateAccount;
use App\Domain\Account\Services\AccountService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Log;

class CreateAccountForNewUser
{
    public function __construct(
        private AccountService $accountService
    ) {}

    /**
     * Handle the event.
     */
    public function handle(Registered $event): void
    {
        try {
            // Create a default personal account for the new user
            $this->accountService->createAccount(
                new CreateAccount(
                    userUuid: $event->user->uuid,
                    accountName: $event->user->name . "'s Account",
                    accountType: 'personal'
                )
            );
            
            Log::info('Created default account for new user', [
                'user_uuid' => $event->user->uuid,
                'user_email' => $event->user->email
            ]);
        } catch (\Exception $e) {
            // Log the error but don't prevent user registration
            Log::error('Failed to create account for new user', [
                'user_uuid' => $event->user->uuid,
                'error' => $e->getMessage()
            ]);
        }
    }
}