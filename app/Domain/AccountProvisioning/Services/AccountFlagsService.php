<?php

declare(strict_types=1);

namespace App\Domain\AccountProvisioning\Services;

use App\Domain\AccountProvisioning\Models\AccountFlag;
use App\Models\User;

class AccountFlagsService
{
    /** @var array<int, ?AccountFlag> */
    private array $cache = [];

    public function forUser(User|int $user): ?AccountFlag
    {
        $userId = $user instanceof User ? (int) $user->id : $user;

        if (array_key_exists($userId, $this->cache)) {
            return $this->cache[$userId];
        }

        $flag = AccountFlag::where('user_id', $userId)->first();

        return $this->cache[$userId] = $flag;
    }

    public function hasReviewBypass(User|int $user, string $bypass): bool
    {
        $flag = $this->forUser($user);

        if ($flag === null || ! $flag->isActive()) {
            return false;
        }

        $column = 'bypass_' . $bypass;

        if (! in_array($column, [
            'bypass_device_attestation',
            'bypass_rate_limit',
            'bypass_sanctions_screening',
            'bypass_sms_otp',
        ], true)) {
            return false;
        }

        $hit = (bool) $flag->{$column};

        if ($hit) {
            logger()->info('bypass.fired', [
                'user_id' => $flag->user_id,
                'bypass'  => $bypass,
                'reason'  => 'review_account',
            ]);
        }

        return $hit;
    }

    public function shouldSuppressNotifications(User|int $user): bool
    {
        $flag = $this->forUser($user);

        return $flag !== null && $flag->isActive() && $flag->suppress_notifications;
    }

    public function kycOverrideLevel(User|int $user): ?int
    {
        $flag = $this->forUser($user);

        if ($flag === null || ! $flag->isActive()) {
            return null;
        }

        return $flag->kyc_override_level;
    }

    public function forget(User|int $user): void
    {
        $userId = $user instanceof User ? (int) $user->id : $user;
        unset($this->cache[$userId]);
    }
}
