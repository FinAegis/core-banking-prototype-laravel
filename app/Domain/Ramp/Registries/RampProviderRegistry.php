<?php

declare(strict_types=1);

namespace App\Domain\Ramp\Registries;

use App\Domain\Ramp\Contracts\RampProviderInterface;

/**
 * Maps provider names (as used in webhook URL path segments and the
 * `provider` column of ramp_sessions) to provider instances.
 *
 * Used by RampWebhookController to resolve the correct provider for an
 * incoming webhook independently of config('ramp.default_provider'), so
 * webhooks for the non-active provider still land correctly during a swap.
 */
final class RampProviderRegistry
{
    /** @param array<string, RampProviderInterface> $providers */
    public function __construct(
        private readonly array $providers,
    ) {
    }

    public function resolve(string $name): ?RampProviderInterface
    {
        return $this->providers[$name] ?? null;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->providers);
    }
}
