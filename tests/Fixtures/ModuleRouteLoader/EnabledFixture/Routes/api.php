<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

// Test fixture for ModuleRouteLoaderTest — represents an ENABLED module.
Route::get('/_fixtures/module-loader/enabled', fn () => response()->json(['module' => 'enabled-fixture']))
    ->name('module-loader-fixture.enabled');
