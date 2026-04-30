<?php

declare(strict_types=1);

use Tests\Filament\Admin\Traits\Fixtures\FixtureBankingWidget;
use Tests\Filament\Admin\Traits\Fixtures\FixtureSystemWidget;
use Tests\Filament\Admin\Traits\Fixtures\FixtureUngroupedWidget;

uses(Tests\TestCase::class);

describe('WidgetRespectsModuleVisibility', function () {
    it('shows all widgets when ADMIN_MODULES is unset', function () {
        config(['brand.admin_modules' => null]);

        expect(FixtureBankingWidget::canView())->toBeTrue();
        expect(FixtureSystemWidget::canView())->toBeTrue();
        expect(FixtureUngroupedWidget::canView())->toBeTrue();
    });

    it('shows only widgets whose module is in the allowed list', function () {
        config(['brand.admin_modules' => ['Banking']]);

        expect(FixtureBankingWidget::canView())->toBeTrue();
        expect(FixtureSystemWidget::canView())->toBeFalse();
    });

    it('hides ungrouped widgets when ADMIN_MODULES is set', function () {
        config(['brand.admin_modules' => ['Banking', 'System']]);

        expect(FixtureUngroupedWidget::canView())->toBeFalse();
    });

    it('matches module names exactly (case-sensitive)', function () {
        config(['brand.admin_modules' => ['banking']]);

        expect(FixtureBankingWidget::canView())->toBeFalse();
    });
});
