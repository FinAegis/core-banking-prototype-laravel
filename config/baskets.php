<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Primary Basket Configuration
    |--------------------------------------------------------------------------
    |
    | These values configure the platform's primary currency basket.
    | This allows different deployments to have different basket configurations
    | without changing code.
    |
    */

    'primary' => env('PRIMARY_BASKET_CODE', 'PRIMARY'),
    'primary_code' => env('PRIMARY_BASKET_CODE', 'PRIMARY'),
    'primary_name' => env('PRIMARY_BASKET_NAME', 'Primary Currency Basket'),
    'primary_symbol' => env('PRIMARY_BASKET_SYMBOL', '$'),
    'primary_description' => env('PRIMARY_BASKET_DESCRIPTION', 'Platform primary currency basket'),
];