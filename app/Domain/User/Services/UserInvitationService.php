<?php

declare(strict_types=1);

namespace App\Domain\User\Services;

use App\Domain\User\Mail\UserInvitationMail;
use App\Domain\User\Models\UserInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;

class UserInvitationService
{
    /**
     * Send an invitation to a new user.
     */
    public function invite(string $email, string $role, User $inviter, int $expiryHours = 72): UserInvitation
    {
        // Prevent inviting existing users
        if (User::where('email', $email)->exists()) {
            throw new RuntimeException("User {$email} already exists.");
        }

        // Prevent duplicate pending invitations
        $existing = UserInvitation::where('email', $email)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($existing !== null) {
            throw new RuntimeException("A pending invitation for {$email} already exists (expires {$existing->expires_at->format('Y-m-d H:i')}).");
        }

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
     */
    public function accept(string $token, string $name, string $password): User
    {
        $invitation = UserInvitation::where('token', $token)->first();

        if ($invitation === null) {
            throw new RuntimeException('Invalid invitation token.');
        }

        if ($invitation->isAccepted()) {
            throw new RuntimeException('This invitation has already been used.');
        }

        if ($invitation->isExpired()) {
            throw new RuntimeException('This invitation has expired.');
        }

        $user = User::create([
            'name'     => $name,
            'email'    => $invitation->email,
            'password' => Hash::make($password),
        ]);

        // Assign the invited role
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
    }

    /**
     * Resend an existing invitation.
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

        // Refresh expiry
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
}
