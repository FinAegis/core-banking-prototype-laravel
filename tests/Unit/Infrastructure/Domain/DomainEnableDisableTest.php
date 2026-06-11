<?php

declare(strict_types=1);

use App\Infrastructure\Domain\DomainManager;
use Illuminate\Support\Facades\Cache;

uses(Tests\TestCase::class);

beforeEach(function () {
    Cache::flush();
});

describe('Domain Enable/Disable', function () {
    it('can disable an optional domain', function () {
        /** @var DomainManager $manager */
        $manager = app(DomainManager::class);

        $result = $manager->disable('exchange');

        expect($result->success)->toBeTrue();
        expect($manager->isDisabled('finaegis/exchange'))->toBeTrue();
    });

    it('cannot disable a core domain', function () {
        /** @var DomainManager $manager */
        $manager = app(DomainManager::class);

        $result = $manager->disable('account');

        expect($result->success)->toBeFalse();
        expect($result->errors)->not->toBeEmpty();
    });

    it('can enable a disabled domain', function () {
        /** @var DomainManager $manager */
        $manager = app(DomainManager::class);

        $manager->disable('exchange');
        expect($manager->isDisabled('finaegis/exchange'))->toBeTrue();

        $result = $manager->enable('exchange');
        expect($result->success)->toBeTrue();
        expect($manager->isDisabled('finaegis/exchange'))->toBeFalse();
    });

    it('returns warning when disabling already disabled domain', function () {
        /** @var DomainManager $manager */
        $manager = app(DomainManager::class);

        $manager->disable('exchange');
        $result = $manager->disable('exchange');

        expect($result->success)->toBeTrue();
        expect($result->warnings)->not->toBeEmpty();
    });

    it('returns warning when enabling already enabled domain', function () {
        /** @var DomainManager $manager */
        $manager = app(DomainManager::class);

        $result = $manager->enable('exchange');

        expect($result->success)->toBeTrue();
        expect($result->warnings)->not->toBeEmpty();
    });

    it('fails when enabling non-existent domain', function () {
        /** @var DomainManager $manager */
        $manager = app(DomainManager::class);

        $result = $manager->enable('nonexistent');

        expect($result->success)->toBeFalse();
    });

    it('fails when disabling non-existent domain', function () {
        /** @var DomainManager $manager */
        $manager = app(DomainManager::class);

        $result = $manager->disable('nonexistent');

        expect($result->success)->toBeFalse();
    });

    it('shows disabled status in available domains list', function () {
        /** @var DomainManager $manager */
        $manager = app(DomainManager::class);

        $manager->disable('exchange');

        $domains = $manager->getAvailableDomains();
        $exchange = $domains->first(fn ($d) => str_contains($d->name, 'exchange'));

        expect($exchange)->not->toBeNull();
        expect($exchange->status->value)->toBe('disabled');
    });

    it('reads disabled list from config as fallback', function () {
        config(['modules.disabled' => ['finaegis/wallet']]);

        /** @var DomainManager $manager */
        $manager = app(DomainManager::class);

        expect($manager->isDisabled('finaegis/wallet'))->toBeTrue();
    });

    it('accepts bare directory names in config (MODULES_DISABLED format)', function () {
        config(['modules.disabled' => ['Lending', ' Treasury ']]);

        /** @var DomainManager $manager */
        $manager = app(DomainManager::class);

        // ModuleRouteLoader passes the directory basename; both spellings must match.
        expect($manager->isDisabled('Lending'))->toBeTrue();
        expect($manager->isDisabled('finaegis/lending'))->toBeTrue();
        expect($manager->isDisabled('Treasury'))->toBeTrue();
        expect($manager->isDisabled('Wallet'))->toBeFalse();
    });

    it('clears disabled cache on clearCache', function () {
        /** @var DomainManager $manager */
        $manager = app(DomainManager::class);

        $manager->disable('exchange');
        expect($manager->isDisabled('finaegis/exchange'))->toBeTrue();

        $manager->clearCache();
        // After clearing, falls back to config which is empty
        config(['modules.disabled' => []]);
        expect($manager->isDisabled('finaegis/exchange'))->toBeFalse();
    });
});
