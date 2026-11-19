<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| API user registration
|--------------------------------------------------------------------------
|
| Governs every API path that creates a new user account:
|   - POST /api/auth/register and POST /api/v2/auth/register (password
|     sign-up, RegisterController -> CreateNewUser);
|   - first login with a new Privy identity (POST /api/v1/auth/privy-login)
|     and the web Privy email-OTP login (/login/privy/verify), which
|     auto-create the account;
|   - first social login (POST /api/auth/social/{provider}/callback).
| Existing users always keep logging in. The Fortify web registration form
| is governed separately by REGISTRATION_ENABLED (config/fortify.php).
| Not covered: admin-issued invitations accepted on the web
| (/invitation/accept), business team members added by an existing user
| (TeamMemberController) and operator CLI commands (user:create,
| account:provision-reviewer).
|
| api_enabled: secure by default — ON only for APP_ENV=local, testing or
| demo (the same allow-list as fortify.registration_enabled), OFF for every
| other value (production, staging, unset); an explicit
| REGISTRATION_API_ENABLED value always wins, and ops:verify-env fails a
| production deploy that does not set it. When off, new-account requests
| answer 403 ERR_REG_001.
|
| api_invite_only: only applies while api_enabled is on. A new account then
| needs a pending, unexpired invitation (user_invitations, created by an
| admin) for the same email address — for Privy and social login, the
| provider-verified email. Password sign-up does not verify the email, so it
| must also send the invitation's token (the `token` of the invitation link)
| as `invitation_token`. Without a match the request answers 403
| ERR_REG_002. The invitation is marked accepted when the account is
| created; its role is not applied (API sign-ups get the regular customer
| role).
|
*/

return [
    'api_enabled' => (bool) env('REGISTRATION_API_ENABLED', in_array(env('APP_ENV', 'production'), ['local', 'testing', 'demo'], true)),

    // Whether REGISTRATION_API_ENABLED was set at all. ops:verify-env reads
    // it, because after `php artisan config:cache` the .env file is no
    // longer loaded and an unset value would go unnoticed.
    'api_enabled_explicit' => env('REGISTRATION_API_ENABLED') !== null,

    'api_invite_only' => (bool) env('REGISTRATION_API_INVITE_ONLY', false),
];
