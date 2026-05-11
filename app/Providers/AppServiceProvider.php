<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\ServiceProvider;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Laravel\Firebase\FirebaseProjectManager;
use OpenApi\Analysers\AttributeAnnotationFactory;
use OpenApi\Analysers\DocBlockAnnotationFactory;
use OpenApi\Analysers\ReflectionAnalyser;
use Throwable;
use URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment() !== 'testing') {
            $this->app->register(WaterlineServiceProvider::class);
        }

        // Register voting power strategies
        $this->app->bind('asset_weighted_vote', \App\Domain\Governance\Strategies\AssetWeightedVoteStrategy::class);
        $this->app->bind('one_user_one_vote', \App\Domain\Governance\Strategies\OneUserOneVoteStrategy::class);
        $this->app->bind(\App\Domain\Governance\Strategies\AssetWeightedVotingStrategy::class, \App\Domain\Governance\Strategies\AssetWeightedVotingStrategy::class);

        // Register ledger driver (default: Eloquent)
        $this->app->bind(
            \App\Domain\Ledger\Contracts\LedgerDriverInterface::class,
            \App\Domain\Ledger\Services\Drivers\EloquentDriver::class,
        );

        // Register blockchain service provider
        $this->app->register(BlockchainServiceProvider::class);

        // Override Firebase Messaging to return null when credentials are not configured
        $this->app->singleton(Messaging::class, function ($app) {
            try {
                return $app->make(FirebaseProjectManager::class)->project()->messaging();
            } catch (Throwable) {
                return null;
            }
        });

        // Register AccountFlagsService as request-scoped so the per-request cache
        // persists across a single HTTP request or console command, but is reset
        // between requests / queue jobs / Octane workers (avoids stale state).
        $this->app->scoped(\App\Domain\AccountProvisioning\Services\AccountFlagsService::class);

        // Default Guzzle client for outbound HTTP from domain services that
        // type-hint the PSR/Guzzle ClientInterface (e.g. PrivyJwtVerifier).
        // Tests bind their own mock via the container.
        $this->app->bind(\GuzzleHttp\ClientInterface::class, \GuzzleHttp\Client::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Plan B Backend-Q1: pin the Stripe API version in a single config key
        // (config/services.php:stripe.api_version, env STRIPE_API_VERSION) so
        // Cashier (subscription path) and any future Stripe Bridge clients use
        // the same version. Override the StripeClient container binding so
        // any caller that resolves it via the container honours the pinned
        // version unless they explicitly override.
        $stripeApiVersion = (string) config('services.stripe.api_version');
        if ($stripeApiVersion !== '') {
            $this->app->bind(\Stripe\StripeClient::class, function ($app, array $parameters = []) use ($stripeApiVersion) {
                /** @var array<string, mixed> $config */
                $config = isset($parameters['config']) && is_array($parameters['config']) ? $parameters['config'] : [];

                if (! isset($config['stripe_version'])) {
                    $config['stripe_version'] = $stripeApiVersion;
                }

                if (! isset($config['api_key'])) {
                    $config['api_key'] = (string) config('cashier.secret');
                }

                return new \Stripe\StripeClient($config);
            });
        }

        // L5-Swagger: inject the analyser at generation time (not in config) so
        // config:cache / optimize works. Object instances are not serializable.
        $this->app->resolving(\L5Swagger\GeneratorFactory::class, function () {
            if (config('l5-swagger.defaults.scanOptions.analyser') === null) {
                config(['l5-swagger.defaults.scanOptions.analyser' => new ReflectionAnalyser([
                    new DocBlockAnnotationFactory(),
                    new AttributeAnnotationFactory(),
                ])]);
            }
        });

        // Configure factory namespace resolution for domain models
        /**
         * @param class-string<\Illuminate\Database\Eloquent\Model> $modelName
         * @return class-string<Factory>
         */
        Factory::guessFactoryNamesUsing(function (string $modelName): string {
            // For domain models, preserve the full path structure
            if (str_starts_with($modelName, 'App\\Domain\\')) {
                // Replace App\ with Database\Factories\ and append Factory
                $factoryName = str_replace('App\\', 'Database\\Factories\\', $modelName) . 'Factory';

                /** @var class-string<Factory> */
                return $factoryName;
            }

            // For non-domain models, use the default pattern
            $modelBaseName = class_basename($modelName);

            /** @var class-string<Factory> */
            return 'Database\\Factories\\' . $modelBaseName . 'Factory';
        });

        // Treat 'demo' environment as production
        if ($this->app->environment('demo')) {
            // Force production-like settings
            config(['app.debug' => config('demo.debug', false)]);
            config(['app.debug_blacklist' => config('demo.debug_blacklist')]);

            // Force HTTPS in demo environment (but not for local development)
            $localHosts = explode(',', config('app.local_hostnames', 'localhost,127.0.0.1'));
            if (! in_array(request()->getHost(), $localHosts)) {
                URL::forceScheme('https');
            }

            // Apply demo-specific rate limits
            config(['app.rate_limits.api' => config('demo.rate_limits.api', 60)]);
            config(['app.rate_limits.transactions' => config('demo.rate_limits.transactions', 10)]);
        }

        // Register MCP-supported OAuth scopes with Passport. Without this, the OAuth
        // server rejects an /oauth/authorize call carrying any of our custom scopes
        // (accounts:read, payments:write, sms:send, ...) with `invalid_scope` before
        // the consent view is ever rendered.
        $mcpScopes = (array) config('mcp.scopes', []);
        if ($mcpScopes !== []) {
            \Laravel\Passport\Passport::tokensCan($mcpScopes);
        }

        // Override Passport's default authorization view with the branded MCP consent
        // screen. The closure receives Passport's view parameters (client, user, scopes,
        // request, authToken). We forward the parameter bag to our controller so it can
        // emit Passport's `authToken` as the form's hidden `auth_token` field — without
        // it the approve/deny POST is rejected with InvalidAuthTokenException.
        \Laravel\Passport\Passport::authorizationView(function (array $parameters): \Symfony\Component\HttpFoundation\Response {
            /** @var \App\Domain\MCP\Auth\ConsentScreenController $controller */
            $controller = app(\App\Domain\MCP\Auth\ConsentScreenController::class);
            $view = $controller(request(), $parameters);

            return response($view->render());
        });

        // Plan B Slice 4 — Cue queue event listeners (Backend-Q8).
        // OnboardingCompleted → EnqueueProTrialReminderD1 (24h delay)
        // SubscriptionTrialStarted → three trial-ending delayed jobs
        \Illuminate\Support\Facades\Event::listen(
            \App\Domain\Subscription\Events\OnboardingCompleted::class,
            \App\Domain\Subscription\Listeners\OnboardingCompletedListener::class,
        );

        \Illuminate\Support\Facades\Event::listen(
            \App\Domain\Subscription\Events\SubscriptionTrialStarted::class,
            \App\Domain\Subscription\Listeners\SubscriptionTrialStartedListener::class,
        );
    }
}
