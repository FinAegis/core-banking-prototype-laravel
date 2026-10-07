<?php

declare(strict_types=1);

namespace App\Domain\User\Services;

use App\Domain\User\Exceptions\RegistrationNotAllowedException;
use App\Domain\User\Models\UserInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Decides whether an API sign-up path may create a NEW user account
 * (config/registration.php). Callers check before creating the user and
 * report the created user back through {@see accepted()} so an invitation
 * used for invite-only admission is consumed.
 */
class ApiRegistrationGate
{
    public function isOpen(): bool
    {
        return (bool) config('registration.api_enabled', false);
    }

    public function isInviteOnly(): bool
    {
        return (bool) config('registration.api_invite_only', false);
    }

    /**
     * Throw unless API registration is enabled at all. Used before request
     * validation so a closed endpoint does not reveal which emails exist.
     *
     * @throws RegistrationNotAllowedException
     */
    public function ensureOpen(): void
    {
        if (! $this->isOpen()) {
            throw RegistrationNotAllowedException::closed();
        }
    }

    /**
     * Admit a new account for the given email. Returns the invitation that
     * admitted it in invite-only mode, or null when registration is open.
     *
     * @throws RegistrationNotAllowedException
     */
    public function admit(string $email): ?UserInvitation
    {
        $this->ensureOpen();

        if (! $this->isInviteOnly()) {
            return null;
        }

        $invitation = $this->findPendingInvitation($email);

        if ($invitation === null) {
            throw RegistrationNotAllowedException::invitationRequired();
        }

        return $invitation;
    }

    /**
     * Mark the admitting invitation (if any) as used by the new user.
     */
    public function accepted(?UserInvitation $invitation, User $user): void
    {
        if ($invitation === null) {
            return;
        }

        $invitation->update(['accepted_at' => now()]);

        Log::info('API registration admitted by invitation', [
            'user_id'       => $user->id,
            'invitation_id' => $invitation->id,
        ]);
    }

    private function findPendingInvitation(string $email): ?UserInvitation
    {
        $normalized = strtolower(trim($email));

        if ($normalized === '') {
            return null;
        }

        /** @var UserInvitation|null $invitation */
        $invitation = UserInvitation::query()
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->first();

        return $invitation;
    }
}
