<?php

declare(strict_types=1);

namespace App\Domain\User\Services;

use App\Domain\User\Mail\UserInvitationMail;
use App\Domain\User\Models\UserInvitation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;

class UserInvitationService
{
    private const ALLOWED_ROLES = ['private', 'admin', 'super_admin'];

    /**
     * Send an invitation to a new user.
     */
    public function invite(string $email, string $role, User $inviter, int $expiryHours = 72): UserInvitation
    {
        $this->validateRole($role);

        if (User::where('email', $email)->exists()) {
            throw new RuntimeException("User {$email} already exists.");
        }

        // Expire any previous pending invitations for this email
        UserInvitation::where('email', $email)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->update(['expires_at' => now()]);

        $invitation = UserInvitation::create([
            'email'      => $email,
            'token'      => Str::random(64),
            'role'       => $role,
            'invited_by' => $inviter->id,
            'expires_at' => now()->addHours($expiryHours),
        ]);

        Mail::to($email)->queue(new UserInvitationMail($invitation, $inviter->name));

        Log::info('User invitation sent', [
            'email'      => $email,
            'role'       => $role,
            'invited_by' => $inviter->id,
            'expires_at' => $invitation->expires_at->toIso8601String(),
        ]);

        return $invitation;
    }

    /**
     * Accept an invitation and create the user account.
     *
     * Atomic: locks the invitation row, creates user + role, marks accepted.
     */
    public function accept(string $token, string $name, string $password): User
    {
        return DB::transaction(function () use ($token, $name, $password): User {
            $invitation = UserInvitation::where('token', $token)
                ->lockForUpdate()
                ->first();

            if ($invitation === null) {
                throw new RuntimeException('Invalid invitation token.');
            }

            if ($invitation->isAccepted()) {
                throw new RuntimeException('This invitation has already been used.');
            }

            if ($invitation->isExpired()) {
                throw new RuntimeException('This invitation has expired.');
            }

            $this->validateRole($invitation->role);

            $user = User::create([
                'name'     => $name,
                'email'    => $invitation->email,
                'password' => Hash::make($password),
            ]);

            Role::firstOrCreate(['name' => $invitation->role, 'guard_name' => 'web']);
            $user->assignRole($invitation->role);

            $invitation->update(['accepted_at' => now()]);

            Log::info('User invitation accepted', [
                'user_id'       => $user->id,
                'email'         => $user->email,
                'role'          => $invitation->role,
                'invitation_id' => $invitation->id,
            ]);

            return $user;
        });
    }

    /**
     * Resend an existing invitation (refreshes token + expiry).
     */
    public function resend(string $invitationId, User $inviter): UserInvitation
    {
        /** @var UserInvitation|null $invitation */
        $invitation = UserInvitation::find($invitationId);

        if ($invitation === null) {
            throw new RuntimeException('Invitation not found.');
        }

        if ($invitation->isAccepted()) {
            throw new RuntimeException('Cannot resend — invitation already accepted.');
        }

        $invitation->update([
            'expires_at' => now()->addHours(72),
            'token'      => Str::random(64),
        ]);

        Mail::to($invitation->email)->queue(new UserInvitationMail($invitation, $inviter->name));

        Log::info('User invitation resent', [
            'email'         => $invitation->email,
            'invitation_id' => $invitation->id,
        ]);

        return $invitation;
    }

    /**
     * Revoke a pending invitation.
     */
    public function revoke(string $invitationId): bool
    {
        /** @var UserInvitation|null $invitation */
        $invitation = UserInvitation::find($invitationId);

        if ($invitation === null) {
            return false;
        }

        $invitation->update(['expires_at' => now()]);

        Log::info('User invitation revoked', [
            'email'         => $invitation->email,
            'invitation_id' => $invitation->id,
        ]);

        return true;
    }

    private function validateRole(string $role): void
    {
        if (! in_array($role, self::ALLOWED_ROLES, true)) {
            throw new RuntimeException("Invalid role: {$role}. Allowed: " . implode(', ', self::ALLOWED_ROLES));
        }
    }
}
