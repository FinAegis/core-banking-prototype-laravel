<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\MachinePay\Contracts\ChallengeSignerInterface;
use App\Domain\MachinePay\Enums\PaymentRail;
use App\Domain\MachinePay\Services\MppChallengeService;
use App\Domain\MachinePay\Services\MppClientService;
use App\Domain\MachinePay\Services\MppDiscoveryService;
use App\Domain\MachinePay\Services\MppHeaderCodecService;
use App\Domain\MachinePay\Services\MppPricingService;
use App\Domain\MachinePay\Services\MppRailResolverService;
use App\Domain\MachinePay\Services\MppSettlementService;
use App\Domain\MachinePay\Services\MppVerificationService;
use App\Domain\MachinePay\Services\Rails\DemoRailAdapter;
use App\Domain\MachinePay\Services\Rails\LightningRailAdapter;
use App\Domain\MachinePay\Services\Rails\StripeRailAdapter;
use App\Domain\MachinePay\Services\Rails\TempoRailAdapter;
use App\Domain\MachinePay\Services\Rails\X402RailAdapter;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Machine Payments Protocol domain.
 *
 * Registers rail adapters, challenge signing, and core services.
 * In non-production environments, demo rail adapters are registered
 * alongside real adapters to enable full testability.
 */
class MachinePayServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../config/machinepay.php',
            'machinepay'
        );

        // Bind challenge signer
        $this->app->bind(ChallengeSignerInterface::class, MppChallengeService::class);

        // Register core services as singletons
        $this->app->singleton(MppHeaderCodecService::class);
        $this->app->singleton(MppPricingService::class);
        $this->app->singleton(MppDiscoveryService::class);
        $this->app->singleton(MppChallengeService::class);

        // Register rail resolver and adapters
        $this->app->singleton(MppRailResolverService::class, function ($app): MppRailResolverService {
            $resolver = new MppRailResolverService();

            if ($app->environment('production')) {
                // Production: real rail adapters only
                $resolver->register($app->make(StripeRailAdapter::class));
                $resolver->register($app->make(TempoRailAdapter::class));
                $resolver->register($app->make(LightningRailAdapter::class));
                $resolver->register($app->make(X402RailAdapter::class));
            } else {
                // Non-production: demo adapters for all rails
                foreach (PaymentRail::cases() as $rail) {
                    $resolver->register(new DemoRailAdapter($rail));
                }
            }

            return $resolver;
        });

        // Register verification and settlement services
        $this->app->singleton(MppVerificationService::class, function ($app): MppVerificationService {
            return new MppVerificationService(
                challengeService: $app->make(MppChallengeService::class),
                railResolver: $app->make(MppRailResolverService::class),
            );
        });

        $this->app->singleton(MppSettlementService::class, function ($app): MppSettlementService {
            return new MppSettlementService(
                verification: $app->make(MppVerificationService::class),
                railResolver: $app->make(MppRailResolverService::class),
            );
        });

        // Register client service
        $this->app->singleton(MppClientService::class, function ($app): MppClientService {
            return new MppClientService(
                railResolver: $app->make(MppRailResolverService::class),
            );
        });
    }

    public function boot(): void
    {
        //
    }
}
