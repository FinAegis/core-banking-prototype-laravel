<?php

declare(strict_types=1);

namespace App\Domain\CardIssuance\Providers;

use App\Domain\CardIssuance\Providers\Contracts\CardOnboardingProvider;
use App\Domain\CardIssuance\Providers\Lepo\LepoCardOnboarding;
use Illuminate\Contracts\Container\Container;

/**
 * Resolves a provider name (as stored on the cardholder) to its
 * implementations.
 */
final class CardProviderRegistry
{
    /**
     * @var array<string, class-string<CardOnboardingProvider>>
     */
    private const ONBOARDING = [
        'lepo' => LepoCardOnboarding::class,
    ];

    public function __construct(private readonly Container $container)
    {
    }

    public function onboarding(string $provider): CardOnboardingProvider
    {
        $class = self::ONBOARDING[$provider] ?? null;
        if ($class === null) {
            throw CardProviderException::unsupported($provider);
        }

        /** @var CardOnboardingProvider $implementation */
        $implementation = $this->container->make($class);

        return $implementation;
    }
}
