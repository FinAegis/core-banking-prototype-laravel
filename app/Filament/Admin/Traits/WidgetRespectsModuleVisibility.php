<?php

declare(strict_types=1);

namespace App\Filament\Admin\Traits;

use ReflectionException;
use ReflectionProperty;

/**
 * Provides a module-visibility check for Filament widgets.
 *
 * Widgets opt in by adding `use WidgetRespectsModuleVisibility;` and declaring
 * `protected static ?string $adminModule = 'Banking';` (matching one of the
 * navigation groups listed in ADMIN_MODULES).
 *
 * Widgets without their own canView() can use the default canView() implemented
 * here. Widgets that need additional permission/context checks should define
 * their own canView() and AND it with `static::adminModuleAllowsView()`.
 *
 * When ADMIN_MODULES is unset, all widgets remain visible (full platform).
 * When set, only widgets whose $adminModule is in the allowed list render.
 */
trait WidgetRespectsModuleVisibility
{
    public static function canView(): bool
    {
        return static::adminModuleAllowsView();
    }

    /**
     * Returns true when the widget's module is currently allowed in ADMIN_MODULES.
     */
    protected static function adminModuleAllowsView(): bool
    {
        /** @var array<string>|null $allowedModules */
        $allowedModules = config('brand.admin_modules');

        // null = show all (full platform mode)
        if ($allowedModules === null) {
            return true;
        }

        $module = static::resolveAdminModule();

        // Widgets without an explicit module declaration are hidden when
        // ADMIN_MODULES is set — same convention as resources without a group.
        if ($module === null || $module === '') {
            return false;
        }

        return in_array($module, $allowedModules, true);
    }

    /**
     * Reads the consuming class's $adminModule static property via reflection
     * so the trait does not have to declare the property itself (which would
     * conflict with consumer-side property defaults at composition time).
     */
    protected static function resolveAdminModule(): ?string
    {
        if (! property_exists(static::class, 'adminModule')) {
            return null;
        }

        try {
            $reflection = new ReflectionProperty(static::class, 'adminModule');

            // Bail loudly if a consumer ever declares $adminModule as a non-static
            // property — the rest of the trait assumes class-level visibility.
            if (! $reflection->isStatic()) {
                return null;
            }

            $value = $reflection->getValue();
        } catch (ReflectionException) {
            return null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
