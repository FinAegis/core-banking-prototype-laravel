<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Test fixture for ModuleRouteLoaderTest — represents a module disabled
// via MODULES_DISABLED. If this route is ever registered while the module
// is disabled, the loader's skip logic is broken.
Route::get('/_fixtures/module-loader/disabled', fn () => response()->json(['module' => 'disabled-fixture']))
    ->name('module-loader-fixture.disabled');
