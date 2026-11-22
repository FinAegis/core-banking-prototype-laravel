<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Providers;

use App\Domain\CardIssuance\Models\Cardholder;
use App\Models\User;

/**
 * Decides which card provider a user gets.
 *
 * A user who already has a cardholder keeps that cardholder's provider. A new
 * user gets the configured default, when it is enabled and the user is
 * eligible for it; otherwise cards are not available to them yet.
 */
final class CardProviderSelector
{
    public function providerFor(User $user): ?string
    {
        $stored = Cardholder::query()->where('user_id', $user->id)->whereNotNull('provider')->value('provider');
        if (is_string($stored) && $stored !== '') {
            return $stored;
        }

        $default = strtolower(trim((string) config('cardissuance.providers.default', 'lepo')));

        return $default !== '' && $this->isEligible($user, $default) ? $default : null;
    }

    public function isEligible(User $user, string $provider): bool
    {
        if (! in_array($provider, self::listFromConfig('cardissuance.providers.enabled'), true)) {
            return false;
        }

        if ($provider === 'lepo' && (bool) config('cardissuance.providers.lepo_pilot_only', true)) {
            $pilot = self::listFromConfig('cardissuance.providers.lepo_pilot_users');

            return in_array(strtolower((string) $user->uuid), $pilot, true)
                || in_array(strtolower((string) $user->email), $pilot, true);
        }

        return true;
    }

    /**
     * @return list<string>
     */
    public static function listFromConfig(string $key): array
    {
        $value = config($key, '');
        $items = is_array($value) ? $value : explode(',', (string) $value);

        return array_values(array_filter(
            array_map(static fn (mixed $item): string => strtolower(trim((string) $item)), $items),
            static fn (string $item): bool => $item !== '',
        ));
    }
}
