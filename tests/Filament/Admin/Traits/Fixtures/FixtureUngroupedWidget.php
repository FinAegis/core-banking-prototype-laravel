<?php

declare(strict_types=1);

namespace Tests\Filament\Admin\Traits\Fixtures;

use App\Filament\Admin\Traits\WidgetRespectsModuleVisibility;
use Filament\Widgets\Widget;

class FixtureUngroupedWidget extends Widget
{
    use WidgetRespectsModuleVisibility;

    protected static string $view = 'welcome';
}
