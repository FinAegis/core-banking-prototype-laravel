<?php

declare(strict_types=1);

namespace App\Domain\User\Services;

use App\Domain\User\Exceptions\RegistrationNotAllowedException;
use App\Domain\User\Models\UserInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
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

    /**
     * Whether the legacy sign-ups that Privy does not verify (email and
     * password, social login) may create an account. They need both
     * REGISTRATION_API_ENABLED and REGISTRATION_PASSWORD_API_ENABLED.
     */
    public function isLegacyOpen(): bool
    {
        return $this->isOpen() && (bool) config('registration.password_api_enabled', false);
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
     * Throw unless the legacy (non-Privy) sign-ups are enabled. Used before
     * request validation, like {@see ensureOpen()}.
     *
     * @throws RegistrationNotAllowedException
     */
    public function ensureLegacyOpen(): void
    {
        if (! $this->isLegacyOpen()) {
            throw RegistrationNotAllowedException::closed();
        }
    }

    /**
     * Admit a new account from a legacy social login: the legacy switch
     * first, then the same rules as {@see admit()}.
     *
     * @throws RegistrationNotAllowedException
     */
    public function admitLegacy(string $email): ?UserInvitation
    {
        $this->ensureLegacyOpen();

        return $this->admit($email);
    }

    /**
     * Whether {@see admit()} would accept a new account for this email
     * address, without throwing or consuming anything. Lets a sign-in flow
     * that may end in account creation stop before it calls the identity
     * provider.
     */
    public function wouldAdmit(string $email): bool
    {
        if (! $this->isOpen()) {
            return false;
        }

        if (! $this->isInviteOnly()) {
            return true;
        }

        return $this->pendingInvitations($email)->isNotEmpty();
    }

    /**
     * Admit a new account for an email address verified by the identity
     * provider (Privy, social login). Returns the invitation that admitted it
     * in invite-only mode, or null when registration is open.
     *
     * @throws RegistrationNotAllowedException
     */
    public function admit(string $email): ?UserInvitation
    {
        $this->ensureOpen();

        if (! $this->isInviteOnly()) {
            return null;
        }

        $invitation = $this->pendingInvitations($email)->first();

        if ($invitation === null) {
            throw RegistrationNotAllowedException::invitationRequired();
        }

        return $invitation;
    }

    /**
     * Admit a password sign-up (legacy switch included). Its email address
     * is only what the caller typed, so in invite-only mode the invitation token (from the
     * invitation link) must match a pending invitation for that email too.
     *
     * @throws RegistrationNotAllowedException
     */
    public function admitWithToken(string $email, ?string $invitationToken): ?UserInvitation
    {
        $this->ensureLegacyOpen();

        if (! $this->isInviteOnly()) {
            return null;
        }

        $token = (string) $invitationToken;

        if ($token !== '') {
            foreach ($this->pendingInvitations($email) as $invitation) {
                if (hash_equals((string) $invitation->token, $token)) {
                    return $invitation;
                }
            }
        }

        throw RegistrationNotAllowedException::invitationRequired();
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

    /**
     * Pending, unexpired invitations for the email address, newest first.
     *
     * @return Collection<int, UserInvitation>
     */
    private function pendingInvitations(string $email): Collection
    {
        $normalized = strtolower(trim($email));

        if ($normalized === '') {
            return new Collection();
        }

        return UserInvitation::query()
            ->whereRaw('LOWER(email) = ?', [$normalized])
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get();
    }
}
