<?php

declare(strict_types=1);

use App\Filament\Admin\Traits\WidgetRespectsModuleVisibility;
use Filament\Widgets\Widget;

uses(Tests\TestCase::class);

class FixtureBankingWidget extends Widget
{
    use WidgetRespectsModuleVisibility;

    protected static ?string $adminModule = 'Banking';

    protected static string $view = 'welcome';
}

class FixtureSystemWidget extends Widget
{
    use WidgetRespectsModuleVisibility;

    protected static ?string $adminModule = 'System';

    protected static string $view = 'welcome';
}

class FixtureUngroupedWidget extends Widget
{
    use WidgetRespectsModuleVisibility;

    protected static string $view = 'welcome';
}

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
