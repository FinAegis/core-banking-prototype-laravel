<?php

declare(strict_types=1);

namespace App\Domain\Ramp\Registries;

use App\Domain\Ramp\Contracts\RampProviderInterface;
use Closure;

/**
 * Maps provider names (as used in webhook URL path segments and the
 * `provider` column of ramp_sessions) to provider instances.
 *
 * Used by RampWebhookController to resolve the correct provider for an
 * incoming webhook independently of config('ramp.default_provider'), so
 * webhooks for the non-active provider still land correctly during a swap.
 *
 * Providers are instantiated lazily via factory closures — booting the
 * registry does not build any provider, so a Stripe-only deployment can
 * boot cleanly even when Onramper credentials are not configured.
 */
final class RampProviderRegistry
{
    /** @var array<string, Closure(): RampProviderInterface> */
    private readonly array $factories;

    /** @var array<string, RampProviderInterface> */
    private array $resolved = [];

    /** @param array<string, Closure(): RampProviderInterface> $factories */
    public function __construct(array $factories)
    {
        $this->factories = $factories;
    }

    public function resolve(string $name): ?RampProviderInterface
    {
        if (isset($this->resolved[$name])) {
            return $this->resolved[$name];
        }

        if (! isset($this->factories[$name])) {
            return null;
        }

        $instance = ($this->factories[$name])();
        $this->resolved[$name] = $instance;

        return $instance;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_keys($this->factories);
    }
}
