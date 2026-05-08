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
];
