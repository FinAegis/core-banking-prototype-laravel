<?php

use Laravel\Jetstream\Features;
use Laravel\Jetstream\Http\Middleware\AuthenticateSession;

return [

    /*
    |--------------------------------------------------------------------------
    | Jetstream Stack
    |--------------------------------------------------------------------------
    |
    | This configuration value informs Jetstream which "stack" you will be
    | using for your application. In general, this value is set for you
    | during installation and will not need to be changed after that.
    |
    */

    'stack' => 'livewire',

    /*
     |--------------------------------------------------------------------------
     | Jetstream Route Middleware
     |--------------------------------------------------------------------------
     |
     | Here you may specify which middleware Jetstream will assign to the routes
     | that it registers with the application. When necessary, you may modify
     | these middleware; however, this default value is usually sufficient.
     |
     */

    'middleware' => ['web'],

    'auth_session' => AuthenticateSession::class,

    /*
    |--------------------------------------------------------------------------
    | Jetstream Guard
    |--------------------------------------------------------------------------
    |
    | Here you may specify the authentication guard Jetstream will use while
    | authenticating users. This value should correspond with one of your
    | guards that is already present in your "auth" configuration file.
    |
    */

    'guard' => 'sanctum',

    /*
    |--------------------------------------------------------------------------
    | Features
    |--------------------------------------------------------------------------
    |
    | Some of Jetstream's features are optional. You may disable the features
    | by removing them from this array. You're free to only remove some of
    | these features or you can even remove all of these if you need to.
    |
    */

    'features' => [
        Features::termsAndPrivacyPolicy(),
        // Features::profilePhotos(),
        Features::api(),
        Features::teams(['invitations' => true]),
        Features::accountDeletion(),
    ],

    /*
    |--------------------------------------------------------------------------
    | Profile Photo Disk
    |--------------------------------------------------------------------------
    |
    | This configuration value determines the default disk that will be used
    | when storing profile photos for your application's users. Typically
    | this will be the "public" disk but you may adjust this if needed.
    |
    */

    'profile_photo_disk' => 'public',

    /*
    |--------------------------------------------------------------------------
    | Team Invitation Limits
    |--------------------------------------------------------------------------
    |
    | Every team invitation sends an email from the platform's domain to an
    | address the inviter chooses. These caps bound that mail: per inviter
    | (across all of their teams), per recipient address within one team,
    | and platform-wide. A new inviter (an account younger than
    | new_inviter_days, or with an unverified email address) gets a smaller
    | daily share and draws on its own platform-wide pool, so throwaway
    | accounts cannot use up the pool that established users rely on.
    |
    */

    'invitation_limits' => [
        'per_inviter_hourly'        => (int) env('TEAM_INVITATIONS_PER_INVITER_HOURLY', 10),
        'per_inviter_daily'         => (int) env('TEAM_INVITATIONS_PER_INVITER_DAILY', 50),
        'per_recipient_daily'       => (int) env('TEAM_INVITATIONS_PER_RECIPIENT_DAILY', 3),
        'global_daily'              => (int) env('TEAM_INVITATIONS_GLOBAL_DAILY', 1000),
        'new_inviter_days'          => (int) env('TEAM_INVITATIONS_NEW_INVITER_DAYS', 7),
        'per_new_inviter_daily'     => (int) env('TEAM_INVITATIONS_PER_NEW_INVITER_DAILY', 20),
        'new_inviters_global_daily' => (int) env('TEAM_INVITATIONS_NEW_INVITERS_GLOBAL_DAILY', 200),
    ],

];
