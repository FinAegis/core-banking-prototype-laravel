<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Investor inquiry notification recipient
    |--------------------------------------------------------------------------
    |
    | Email address that receives a notification each time the public /invest
    | form is submitted. Single recipient — keep this an inbox the founder
    | reads daily.
    |
    */
    'notification_email' => env('INVESTOR_NOTIFICATION_EMAIL', 'investors@finaegis.com'),

    /*
    |--------------------------------------------------------------------------
    | Investor page switch
    |--------------------------------------------------------------------------
    |
    | When false, GET /invest* answers a 301 to the site root and the form
    | POST answers 404 (route names stay registered).
    |
    | Secure by default: on only for APP_ENV=local, testing or demo, off for
    | every other value; set INVESTOR_PAGE_ENABLED=true to serve it in
    | production.
    |
    */
    'page_enabled' => (bool) env('INVESTOR_PAGE_ENABLED', in_array(env('APP_ENV', 'production'), ['local', 'testing', 'demo'], true)),
];
