<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Testing Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration specific for the testing environment.
    |
    */

    'database' => [
        /*
        |--------------------------------------------------------------------------
        | Database Transactions
        |--------------------------------------------------------------------------
        |
        | This option controls whether to use database transactions in tests.
        | When running tests in parallel, transactions can cause conflicts.
        |
        */
        'use_transactions' => env('DB_USE_TRANSACTIONS', !env('PEST_PARALLEL', false)),
    ],
];