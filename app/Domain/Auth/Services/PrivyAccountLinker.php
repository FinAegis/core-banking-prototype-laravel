<?php

declare(strict_types=1);

namespace App\Domain\Auth\Services;

use App\Domain\Compliance\Models\AuditLog;
use App\Domain\Mobile\Services\MobileDeviceService;
use App\Domain\User\Exceptions\RegistrationNotAllowedException;
use App\Domain\User\Services\ApiRegistrationGate;
use App\Models\ApiKey;
use App\Models\User;
use App\Support\UserSignIns;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Finds, links or creates the user behind a Privy email sign-in. The mobile
 * Privy login and the web email-code login both use it.
 *
 * Privy proves that the person controls the inbox, which is what a password
 * reset relies on too. So an account created before Privy, with the same
 * email address, is linked to the Privy identity instead of being refused.
 * If that account's email was never verified, someone else may have
 * registered the address first, so every earlier way into the account ends
 * when it is linked.
 */
final class PrivyAccountLinker
{
    public const FLOW_MOBILE = 'mobile';

    public const FLOW_WEB = 'web';

    public const AUDIT_ACTION = 'auth.privy_identity_linked';

    /**
     * Erased accounts get `deleted-<uuid>@anonymized.local` addresses.
     */
    private const ANONYMIZED_EMAIL_SUFFIX = '@anonymized.local';

    public function __construct(
        private readonly ApiRegistrationGate $registrationGate,
        private readonly MobileDeviceService $devices,
    ) {
    }

    /**
     * Returns the user for this Privy identity, or null when an account with
     * this email address is already linked to a different Privy identity
     * (or the address is one given to an erased account). Only a brand-new
     * account goes through the registration switch.
     *
     * @throws RegistrationNotAllowedException when a new account is not admitted
     */
    public function resolve(
        string $privyUserId,
        string $email,
        string $flow,
        ?string $name = null,
        ?string $timezone = null,
    ): ?User {
        $email = strtolower(trim($email));

        $user = $this->findByPrivyId($privyUserId);
        if ($user instanceof User) {
            return $user;
        }

        // An erased account's address is never matched or claimed.
        if (str_ends_with($email, self::ANONYMIZED_EMAIL_SUFFIX)) {
            return null;
        }

        $matches = $this->findByEmail($email);
        if ($matches->isNotEmpty()) {
            return $this->linkSingleMatch($matches, $privyUserId, $email, $flow);
        }

        try {
            return $this->create($privyUserId, $email, $name, $timezone);
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent first sign-in for the same identity or address
            // inserted its row first. Use that row instead of failing.
            $user = $this->findByPrivyId($privyUserId);
            if ($user instanceof User) {
                return $user;
            }

            $matches = $this->findByEmail($email);
            if ($matches->isNotEmpty()) {
                return $this->linkSingleMatch($matches, $privyUserId, $email, $flow);
            }

            throw $e;
        }
    }

    private function findByPrivyId(string $privyUserId): ?User
    {
        return User::query()->where('privy_user_id', $privyUserId)->first();
    }

    /**
     * Accounts whose email address matches, ignoring letter case. Erased
     * accounts never match.
     *
     * @return Collection<int, User>
     */
    private function findByEmail(string $email): Collection
    {
        if ($email === '') {
            return new Collection();
        }

        return User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('email', 'not like', '%' . self::ANONYMIZED_EMAIL_SUFFIX)
            ->limit(2)
            ->get();
    }

    /**
     * @param Collection<int, User> $matches
     */
    private function linkSingleMatch(Collection $matches, string $privyUserId, string $email, string $flow): ?User
    {
        $existing = $matches->first();

        if ($matches->count() !== 1 || ! $existing instanceof User) {
            // Two accounts differ only in letter case. Neither is linked.
            Log::warning('Privy sign-in matched more than one account by email', ['flow' => $flow]);

            return null;
        }

        return $this->link($existing, $privyUserId, $email, $flow);
    }

    /**
     * Link the Privy identity to an existing account, under a row lock so a
     * concurrent first sign-in cannot link it twice.
     */
    private function link(User $existing, string $privyUserId, string $email, string $flow): ?User
    {
        /** @var array{user: User|null, linked: bool, wasEmailVerified: bool} $result */
        $result = DB::transaction(function () use ($existing, $privyUserId, $email): array {
            $user = User::query()->whereKey($existing->getKey())->lockForUpdate()->first();

            // The account changed its address or was erased in the meantime.
            if (! $user instanceof User || strtolower((string) $user->email) !== $email) {
                return ['user' => null, 'linked' => false, 'wasEmailVerified' => false];
            }

            $linkedIdentity = (string) $user->privy_user_id;

            // A concurrent sign-in for the same identity linked it first.
            if ($linkedIdentity === $privyUserId) {
                return ['user' => $user, 'linked' => false, 'wasEmailVerified' => true];
            }

            // Already linked to a different Privy identity: keep refusing.
            if ($linkedIdentity !== '') {
                return ['user' => null, 'linked' => false, 'wasEmailVerified' => false];
            }

            $wasEmailVerified = $user->email_verified_at !== null;

            $user->forceFill([
                'privy_user_id'   => $privyUserId,
                'privy_linked_at' => now(),
            ]);

            if (! $wasEmailVerified) {
                $this->forgetUnprovenCredentials($user);
            }

            $user->save();

            if (! $wasEmailVerified) {
                $this->endEarlierAccess($user);
            }

            return ['user' => $user, 'linked' => true, 'wasEmailVerified' => $wasEmailVerified];
        });

        $user = $result['user'];

        if ($result['linked'] && $user instanceof User) {
            // AuditLog lives on the tenant connection, so it is written after
            // the default-connection transaction has committed.
            $this->audit($user, $result['wasEmailVerified'], $flow);
        }

        return $user;
    }

    /**
     * Nobody proved this address before, so whoever registered it may not
     * own it. Their password, second factor and social sign-in stop
     * working, and the address now counts as verified through Privy.
     */
    private function forgetUnprovenCredentials(User $user): void
    {
        $user->forceFill([
            'password'                  => Hash::make(Str::random(64)),
            'email_verified_at'         => now(),
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at'   => null,
            'oauth_provider'            => null,
            'oauth_id'                  => null,
        ]);
    }

    /**
     * End every sign-in that existed before the link: API tokens, OAuth
     * grants, the "remember me" token and stored sessions (the same helper
     * a password reset uses), plus mobile devices with their passkey,
     * biometric keys and push tokens, and API keys.
     */
    private function endEarlierAccess(User $user): void
    {
        UserSignIns::endAllExcept($user);

        $this->devices->removeAllDevices($user);

        ApiKey::query()
            ->where('user_uuid', $user->uuid)
            ->where('is_active', true)
            ->update(['is_active' => false]);
    }

    private function audit(User $user, bool $wasEmailVerified, string $flow): void
    {
        $request = request();

        try {
            $log = new AuditLog();
            $log->user_uuid = $user->uuid;
            $log->action = self::AUDIT_ACTION;
            $log->auditable_type = $user::class;
            $log->auditable_id = (string) $user->getKey();
            $log->new_values = ['privy_user_id' => $user->privy_user_id];
            $log->metadata = [
                'was_email_verified'   => $wasEmailVerified,
                'flow'                 => $flow,
                'earlier_access_ended' => ! $wasEmailVerified,
            ];
            $log->ip_address = $request->ip();
            $log->user_agent = Str::limit((string) $request->userAgent(), 255, '');
            $log->tags = 'auth,privy,account-link';
            $log->save();
        } catch (Throwable $e) {
            // The link has committed; a lost audit row must not fail the
            // sign-in. Keep the facts in the application log instead.
            Log::error('Privy account link audit row could not be written', [
                'user_id'            => $user->getKey(),
                'was_email_verified' => $wasEmailVerified,
                'flow'               => $flow,
            ]);
            report($e);
        }
    }

    /**
     * Create the account for a new Privy identity, subject to the API
     * registration switch (config/registration.php).
     *
     * @throws RegistrationNotAllowedException
     * @throws UniqueConstraintViolationException when a concurrent sign-in inserted the same identity or address first
     */
    private function create(string $privyUserId, string $email, ?string $name, ?string $timezone): User
    {
        $invitation = $this->registrationGate->admit($email);

        $name = $name !== null ? trim($name) : '';

        $user = new User();
        $user->forceFill([
            'name'              => $name !== '' ? $name : 'New User',
            'email'             => $email,
            'password'          => Str::random(64),  // unusable; auth gated by Privy
            'email_verified_at' => now(),  // Privy already verified
            'privy_user_id'     => $privyUserId,
            'privy_linked_at'   => now(),
        ]);
        if ($timezone !== null) {
            $user->forceFill(['timezone' => $timezone]);
        }
        $user->save();

        $this->registrationGate->accepted($invitation, $user);

        return $user;
    }
}
