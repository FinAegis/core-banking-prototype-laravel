<?php

declare(strict_types=1);

use App\Domain\AccountProvisioning\Services\AccountFlagsService;
use App\Domain\Governance\Strategies\AssetWeightedVoteStrategy;
use App\Domain\Governance\Strategies\OneUserOneVoteStrategy;
use App\Domain\Ledger\Contracts\LedgerDriverInterface;
use App\Domain\Ledger\Services\Drivers\EloquentDriver;
use App\Providers\WaterlineServiceProvider;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use Tests\TestCase;

uses(TestCase::class);

it('binds the default Guzzle client for domain services', function () {
    // Regression guard: without this binding PrivyJwtVerifier fails to
    // resolve and /api/v1/auth/privy-login 500s (see CLAUDE.md Wallet Send).
    expect(app(ClientInterface::class))->toBeInstanceOf(Client::class);
});

it('binds governance voting strategies', function () {
    expect(app('asset_weighted_vote'))->toBeInstanceOf(AssetWeightedVoteStrategy::class)
        ->and(app('one_user_one_vote'))->toBeInstanceOf(OneUserOneVoteStrategy::class);
});

it('binds the ledger driver to the eloquent implementation', function () {
    expect(app(LedgerDriverInterface::class))->toBeInstanceOf(EloquentDriver::class);
});

it('scopes AccountFlagsService so the per-request cache is shared', function () {
    expect(app(AccountFlagsService::class))->toBe(app(AccountFlagsService::class));
});

it('does not register WaterlineServiceProvider in the testing environment', function () {
    expect(app()->providerIsLoaded(WaterlineServiceProvider::class))->toBeFalse();
});
