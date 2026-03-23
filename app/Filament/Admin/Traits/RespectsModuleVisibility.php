<?php

declare(strict_types=1);

namespace App\Filament\Admin\Traits;

/**
 * Hides Filament resources whose navigation group is not in brand.admin_modules.
 *
 * When ADMIN_MODULES is not set (null), all groups are visible (FinAegis full platform).
 * When set to a comma-separated list, only listed groups appear (e.g. Zelta mobile wallet).
 *
 * Usage: add `use RespectsModuleVisibility;` to any Filament Resource class.
 */
trait RespectsModuleVisibility
{
    public static function shouldRegisterNavigation(): bool
    {
        /** @var array<string>|null $allowedModules */
        $allowedModules = config('brand.admin_modules');

        // null = show all (FinAegis full platform mode)
        if ($allowedModules === null) {
            return true;
        }

        $group = static::getNavigationGroup();

        // Resources without a group are always visible (ungrouped)
        if ($group === null || $group === '') {
            return true;
        }

        return in_array($group, $allowedModules, true);
    }
}
