<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Module System Configuration
    |--------------------------------------------------------------------------
    |
    | Controls the FinAegis modular domain system. When all_enabled is true,
    | every domain is active. Set to false and populate the disabled list
    | to selectively disable specific domain modules.
    |
    */

    'all_enabled' => env('MODULES_ALL_ENABLED', true),

    'disabled' => array_filter(explode(',', env('MODULES_DISABLED', ''))),

    /*
    |--------------------------------------------------------------------------
    | Runtime module and plugin management
    |--------------------------------------------------------------------------
    |
    | Gates the admin endpoints that change the module or plugin set while
    | the application runs: REST POST /api/v2/modules/{name}/enable|disable|
    | verify, the /api/v2/plugins discover/enable/disable/delete/scan
    | endpoints, the GraphQL enablePlugin/disablePlugin/scanPlugin mutations
    | and the enable/disable/discover actions of the admin panel Modules and
    | Plugin Marketplace pages. A runtime module enable or disable writes the
    | domain_manager_disabled cache key, which the module route loader and
    | the module status prefer over MODULES_DISABLED. While off, the REST
    | endpoints answer 404, the GraphQL mutations return "This operation is
    | not available." and the admin panel actions only show a notice; reads
    | (module and plugin lists, health) stay available.
    | Secure by default: on only for APP_ENV=local, testing or demo, off for
    | every other value (production, staging, unset). An explicit
    | ADMIN_RUNTIME_MANAGEMENT_ENABLED value always wins.
    |
    */

    'runtime_management_enabled' => (bool) env('ADMIN_RUNTIME_MANAGEMENT_ENABLED', in_array(env('APP_ENV', 'production'), ['local', 'testing', 'demo'], true)),

];
